<?php

namespace Donmanueldev\NativephpCharts\Support;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Normalizes line and band annotations against the chart's declared axis types.
 *
 * X annotations share the chart data's typed-x representation; y annotations remain
 * numeric. This keeps annotation geometry comparable with series and viewport values.
 */
final class AnnotationNormalizer
{
    /**
     * Validate an ordered annotation list and return renderer-ready canonical keys.
     *
     * Line input: `id`, `type: line`, `axis`, `value`, and optional `label`, `color`,
     * `width`. Band input replaces `value`/`width` with `from`, `to`, and optional
     * `opacity`.
     *
     * Line annotations contain `value` and `width`; bands contain `from`, `to`, and
     * `opacity`. IDs are unique and non-category ranges must be strictly increasing.
     *
     * @param  array<int, mixed>  $annotations
     * @return list<array<string, float|int|string>>
     *
     * @throws InvalidArgumentException When identity, axis values, ranges, or styling are invalid.
     */
    public static function normalize(array $annotations, string $xType, string $chartName): array
    {
        if (! array_is_list($annotations)) {
            throw new InvalidArgumentException("The {$chartName} annotations must be an ordered list.");
        }

        $normalized = [];
        $ids = [];

        foreach ($annotations as $index => $annotation) {
            if (! is_array($annotation)) {
                throw new InvalidArgumentException("The {$chartName} annotation at index {$index} must be an array.");
            }

            $id = self::text($annotation['id'] ?? null, $chartName, "annotation at index {$index} id");
            if (isset($ids[$id])) {
                throw new InvalidArgumentException("The {$chartName} annotation id '{$id}' must be unique.");
            }
            $ids[$id] = true;

            $type = $annotation['type'] ?? null;
            if (! is_string($type) || ! in_array($type, ['line', 'band'], true)) {
                throw new InvalidArgumentException("The {$chartName} annotation '{$id}' type must be line or band.");
            }
            self::rejectUnknownKeys(
                $annotation,
                $type === 'line'
                    ? ['id', 'type', 'axis', 'value', 'label', 'color', 'width']
                    : ['id', 'type', 'axis', 'from', 'to', 'label', 'color', 'opacity'],
                $chartName,
                $id,
            );

            $axis = $annotation['axis'] ?? null;
            if (! is_string($axis) || ! in_array($axis, ['x', 'y'], true)) {
                throw new InvalidArgumentException("The {$chartName} annotation '{$id}' axis must be x or y.");
            }

            $item = [
                'id' => $id,
                'type' => $type,
                'axis' => $axis,
                'color' => ColorNormalizer::normalize(
                    $annotation['color'] ?? '#6366F1',
                    $chartName,
                    "annotation '{$id}'",
                ),
            ];

            if (array_key_exists('label', $annotation)) {
                $item['label'] = self::text($annotation['label'], $chartName, "annotation '{$id}' label");
            }

            if ($type === 'line') {
                if (! array_key_exists('value', $annotation)) {
                    throw new InvalidArgumentException("The {$chartName} line annotation '{$id}' requires a value.");
                }

                $item['value'] = self::axisValue($annotation['value'], $axis, $xType, $chartName, $id);
                $item['width'] = self::number($annotation['width'] ?? 1, $chartName, "annotation '{$id}' width", 0.000_001, 16);
            } else {
                if (! array_key_exists('from', $annotation) || ! array_key_exists('to', $annotation)) {
                    throw new InvalidArgumentException("The {$chartName} band annotation '{$id}' requires from and to values.");
                }

                $from = self::axisValue($annotation['from'], $axis, $xType, $chartName, $id);
                $to = self::axisValue($annotation['to'], $axis, $xType, $chartName, $id);
                if ($axis !== 'x' || $xType !== 'category') {
                    $comparisonType = $axis === 'x' ? $xType : 'number';
                    if (self::compare($from, $to, $comparisonType) >= 0) {
                        throw new InvalidArgumentException("The {$chartName} band annotation '{$id}' from must be less than to.");
                    }
                }

                $item['from'] = $from;
                $item['to'] = $to;
                $item['opacity'] = self::number(
                    $annotation['opacity'] ?? 0.12,
                    $chartName,
                    "annotation '{$id}' opacity",
                    0,
                    1,
                );
            }

            $normalized[] = $item;
        }

        return $normalized;
    }

    /** Normalize a value using the y-number or typed-x contract selected by the annotation axis. */
    private static function axisValue(
        mixed $value,
        string $axis,
        string $xType,
        string $chartName,
        string $id,
    ): float|int|string {
        if ($axis === 'y') {
            return self::number($value, $chartName, "annotation '{$id}' value");
        }

        if ($xType === 'category') {
            return self::text($value, $chartName, "annotation '{$id}' category value");
        }

        return ChartDataNormalizer::normalizeTypedX($value, $xType, $chartName, "annotation '{$id}'");
    }

    private static function text(mixed $value, string $chartName, string $property): string
    {
        if (! is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException("The {$chartName} {$property} must be a non-empty string.");
        }

        return trim($value);
    }

    private static function number(
        mixed $value,
        string $chartName,
        string $property,
        float $minimum = -INF,
        float $maximum = INF,
    ): float|int {
        if ((! is_int($value) && ! is_float($value)) || ! is_finite((float) $value) || $value < $minimum || $value > $maximum) {
            throw new InvalidArgumentException("The {$chartName} {$property} must be a finite number in the supported range.");
        }

        ChartDataNormalizer::assertExactNumber($value, $chartName, $property);

        return $value;
    }

    /** Compare canonical number, date, datetime, or category values without changing them. */
    private static function compare(float|int|string $left, float|int|string $right, string $type): int
    {
        if ($type === 'number') {
            return (float) $left <=> (float) $right;
        }

        if ($type === 'date' || $type === 'category') {
            return $left <=> $right;
        }

        return new DateTimeImmutable((string) $left) <=> new DateTimeImmutable((string) $right);
    }

    /**
     * @param  array<string|int, mixed>  $annotation
     * @param  list<string>  $allowed
     */
    private static function rejectUnknownKeys(array $annotation, array $allowed, string $chartName, string $id): void
    {
        foreach ($annotation as $key => $value) {
            if (! is_string($key) || ! in_array($key, $allowed, true)) {
                throw new InvalidArgumentException("The {$chartName} annotation '{$id}' option '{$key}' is not supported.");
            }
        }
    }
}
