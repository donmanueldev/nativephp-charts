<?php

namespace Donmanueldev\NativephpCharts\Support;

use InvalidArgumentException;

/**
 * Normalizes radar-specific axes and values into an order-stable wire contract.
 *
 * Unlike Cartesian series, radar values reference a declared axis and must repeat
 * the declaration order exactly so polygon vertices align on iOS and Android.
 */
final class RadarDataNormalizer
{
    /**
     * Validate the 3-to-24 unique axes that define the radar domain.
     *
     * @param  array<int, mixed>  $axes
     * @return list<array{id: string, label: string, maximum: float|int}>
     *
     * @throws InvalidArgumentException When an axis is malformed, duplicated, or has a non-positive maximum.
     */
    public static function axes(array $axes): array
    {
        if (! array_is_list($axes) || count($axes) < 3 || count($axes) > 24) {
            throw new InvalidArgumentException('The radar chart axes must be an ordered list of 3 to 24 axes.');
        }

        $ids = [];

        return array_map(function (mixed $axis, int $index) use (&$ids): array {
            if (! is_array($axis)) {
                throw new InvalidArgumentException("The radar chart axis at index {$index} must be an array.");
            }
            $id = self::text($axis['id'] ?? null, "axis at index {$index} id");
            if (isset($ids[$id])) {
                throw new InvalidArgumentException("The radar chart axis id '{$id}' must be unique.");
            }
            $ids[$id] = true;
            $maximum = self::number($axis['maximum'] ?? null, "axis '{$id}' maximum");
            if ($maximum <= 0) {
                throw new InvalidArgumentException("The radar chart axis '{$id}' maximum must be greater than zero.");
            }

            return ['id' => $id, 'label' => self::text($axis['label'] ?? null, "axis '{$id}' label"), 'maximum' => $maximum];
        }, $axes, array_keys($axes));
    }

    /**
     * Normalize series whose values cover every declared axis once and in order.
     *
     * @param  array<int, mixed>  $series
     * @param  list<array{id: string, label: string, maximum: float|int}>  $axes
     * @return list<array<string, mixed>>
     *
     * @throws InvalidArgumentException When series identity, order, color, or axis values are invalid.
     */
    public static function series(array $series, array $axes): array
    {
        if (! array_is_list($series)) {
            throw new InvalidArgumentException('The radar chart series must be an ordered list.');
        }
        $axisById = array_column($axes, null, 'id');
        $seriesIds = [];

        return array_map(function (mixed $item, int $index) use ($axisById, &$seriesIds): array {
            if (! is_array($item)) {
                throw new InvalidArgumentException("The radar chart series at index {$index} must be an array.");
            }
            $id = self::text($item['id'] ?? null, "series at index {$index} id");
            if (isset($seriesIds[$id])) {
                throw new InvalidArgumentException("The radar chart series id '{$id}' must be unique.");
            }
            $seriesIds[$id] = true;
            $values = $item['values'] ?? null;
            if (! is_array($values) || ! array_is_list($values) || count($values) !== count($axisById)) {
                throw new InvalidArgumentException("The radar chart series '{$id}' must define one ordered value per axis.");
            }

            $seen = [];
            $normalizedValues = array_map(function (mixed $value, int $valueIndex) use ($axisById, $id, &$seen): array {
                if (! is_array($value)) {
                    throw new InvalidArgumentException("The radar chart value at index {$valueIndex} for series '{$id}' must be an array.");
                }
                $axis = self::text($value['axis'] ?? null, "value at index {$valueIndex} axis");
                if (! isset($axisById[$axis]) || isset($seen[$axis])) {
                    throw new InvalidArgumentException("The radar chart series '{$id}' must reference each declared axis exactly once.");
                }
                $seen[$axis] = true;
                $number = self::number($value['value'] ?? null, "series '{$id}' axis '{$axis}' value");
                if ($number < 0 || $number > $axisById[$axis]['maximum']) {
                    throw new InvalidArgumentException("The radar chart series '{$id}' axis '{$axis}' value must be between zero and its maximum.");
                }

                return ['axis' => $axis, 'value' => $number];
            }, $values, array_keys($values));

            if (array_keys($seen) !== array_keys($axisById)) {
                throw new InvalidArgumentException("The radar chart series '{$id}' values must follow the declared axis order.");
            }

            return [
                'id' => $id,
                'name' => self::text($item['name'] ?? null, "series '{$id}' name"),
                'color' => ColorNormalizer::normalize($item['color'] ?? null, 'radar chart', "series '{$id}'"),
                'values' => $normalizedValues,
            ];
        }, $series, array_keys($series));
    }

    private static function text(mixed $value, string $context): string
    {
        if (! is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException("The radar chart {$context} must be a non-empty string.");
        }

        return trim($value);
    }

    private static function number(mixed $value, string $context): float|int
    {
        if ((! is_int($value) && ! is_float($value)) || ! is_finite((float) $value)) {
            throw new InvalidArgumentException("The radar chart {$context} must be a finite number.");
        }
        ChartDataNormalizer::assertExactNumber($value, 'radar chart', $context);

        return $value;
    }
}
