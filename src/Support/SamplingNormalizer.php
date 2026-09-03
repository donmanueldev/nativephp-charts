<?php

namespace Donmanueldev\NativephpCharts\Support;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Validates sampling options and performs renderer-neutral series reduction.
 */
final class SamplingNormalizer
{
    /**
     * Produce the complete sampling configuration shared by both native renderers.
     *
     * @param  array<string, mixed>  $sampling
     * @return array{mode: string, threshold: int}
     *
     * @throws InvalidArgumentException When the mode or threshold is unsupported.
     */
    public static function normalize(array $sampling, string $chartName): array
    {
        $unknown = array_diff(array_keys($sampling), ['mode', 'threshold']);
        if ($unknown !== []) {
            throw new InvalidArgumentException("The {$chartName} sampling contains unsupported keys: ".implode(', ', $unknown).'.');
        }

        $mode = $sampling['mode'] ?? 'none';
        if (! is_string($mode) || ! in_array($mode, ['none', 'lttb'], true)) {
            throw new InvalidArgumentException("The {$chartName} sampling mode must be none or lttb.");
        }

        $threshold = $sampling['threshold'] ?? 1000;
        if (! is_int($threshold) || $threshold < 3 || $threshold > 100_000) {
            throw new InvalidArgumentException("The {$chartName} sampling threshold must be an integer between 3 and 100000.");
        }

        return compact('mode', 'threshold');
    }

    /**
     * Apply LTTB independently to each series while preserving its first and last point.
     *
     * Sampled points receive `source_index`, retaining the original point position used
     * by native selection callbacks. Series at or below the threshold are returned intact.
     *
     * @param  list<array<string, mixed>>  $series
     * @param  array{mode: string, threshold: int}  $sampling
     * @return list<array<string, mixed>>
     */
    public static function apply(array $series, array $sampling, string $xType): array
    {
        if ($sampling['mode'] !== 'lttb') {
            return $series;
        }

        return array_map(function (array $item) use ($sampling, $xType): array {
            if (count($item['points']) <= $sampling['threshold']) {
                return $item;
            }

            $item['points'] = array_map(
                fn (array $point, int $index): array => [...$point, 'source_index' => $index],
                $item['points'],
                array_keys($item['points']),
            );
            $item['points'] = self::largestTriangleThreeBuckets($item['points'], $sampling['threshold'], $xType);

            return $item;
        }, $series);
    }

    /**
     * Reduce one ordered series by selecting the largest visual triangle per bucket.
     *
     * @param  list<array<string, mixed>>  $points
     * @return list<array<string, mixed>>
     */
    private static function largestTriangleThreeBuckets(array $points, int $threshold, string $xType): array
    {
        $count = count($points);
        $sampled = [$points[0]];
        $bucketSize = ($count - 2) / ($threshold - 2);
        $selectedIndex = 0;

        for ($bucket = 0; $bucket < $threshold - 2; $bucket++) {
            $averageStart = (int) floor(($bucket + 1) * $bucketSize) + 1;
            $averageEnd = min((int) floor(($bucket + 2) * $bucketSize) + 1, $count);
            $averageCount = max($averageEnd - $averageStart, 1);
            $averageX = 0.0;
            $averageY = 0.0;
            for ($index = $averageStart; $index < $averageEnd; $index++) {
                $averageX += self::x($points[$index], $index, $xType);
                $averageY += (float) $points[$index]['value'];
            }
            $averageX /= $averageCount;
            $averageY /= $averageCount;

            $rangeStart = (int) floor($bucket * $bucketSize) + 1;
            $rangeEnd = min((int) floor(($bucket + 1) * $bucketSize) + 1, $count - 1);
            $anchorX = self::x($points[$selectedIndex], $selectedIndex, $xType);
            $anchorY = (float) $points[$selectedIndex]['value'];
            $largestArea = -1.0;
            $nextIndex = $rangeStart;

            for ($index = $rangeStart; $index < $rangeEnd; $index++) {
                $area = abs(
                    ($anchorX - $averageX) * ((float) $points[$index]['value'] - $anchorY)
                    - ($anchorX - self::x($points[$index], $index, $xType)) * ($averageY - $anchorY),
                );
                if ($area > $largestArea) {
                    $largestArea = $area;
                    $nextIndex = $index;
                }
            }

            $sampled[] = $points[$nextIndex];
            $selectedIndex = $nextIndex;
        }

        $sampled[] = $points[$count - 1];

        return $sampled;
    }

    /** Map category, numeric, date, and datetime x values to LTTB coordinates. */
    private static function x(array $point, int $index, string $xType): float
    {
        if ($xType === 'category') {
            return (float) $index;
        }

        $x = $point['x'] ?? $index;
        if (is_int($x) || is_float($x)) {
            return (float) $x;
        }

        if ($xType === 'date') {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $x, new DateTimeZone('UTC'));

            return $date === false ? (float) $index : (float) $date->format('U');
        }

        return (float) (new DateTimeImmutable((string) $x))->format('U.u');
    }
}
