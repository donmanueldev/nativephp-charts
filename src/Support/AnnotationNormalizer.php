<?php

namespace Donmanueldev\NativephpCharts\Support;

use DateTimeImmutable;
use InvalidArgumentException;

final class AnnotationNormalizer
{
    /** @return list<array<string, float|int|string>> */
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
                if (self::compare($from, $to, $axis === 'x' ? $xType : 'number') >= 0) {
                    throw new InvalidArgumentException("The {$chartName} band annotation '{$id}' from must be less than to.");
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
}
