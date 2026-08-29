<?php

namespace Donmanueldev\NativephpCharts\Support;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

final class ChartDataNormalizer
{
    private const int MAX_EXACT_INTEGER = 9_007_199_254_740_991;

    /**
     * @param  array<int, mixed>  $series
     * @return list<array{id: string, name: string, color: string, points: list<array{id: string, label: string, value: int|float, x: int|float|string}>}>
     */
    public static function normalize(
        array $series,
        string $xType,
        string $chartName,
        bool $allowDeferredTypedX = false,
    ): array {
        if (! array_is_list($series)) {
            throw new InvalidArgumentException("The {$chartName} series must be an ordered list.");
        }

        $normalized = [];
        $seriesIds = [];

        foreach ($series as $index => $item) {
            if (! is_array($item)) {
                throw new InvalidArgumentException("The {$chartName} series at index {$index} must be an array.");
            }

            $id = self::requiredString($item, 'id', "series at index {$index}", $chartName);
            if (array_key_exists($id, $seriesIds)) {
                throw new InvalidArgumentException("The {$chartName} series id '{$id}' must be unique.");
            }
            $seriesIds[$id] = true;

            $name = self::requiredString($item, 'name', "series '{$id}'", $chartName);
            $color = ColorNormalizer::normalize(
                self::requiredString($item, 'color', "series '{$id}'", $chartName),
                $chartName,
                "series '{$id}'",
            );

            if (! array_key_exists('points', $item) || ! is_array($item['points'])) {
                throw new InvalidArgumentException("The {$chartName} points for series '{$id}' must be an array.");
            }

            if (! array_is_list($item['points'])) {
                throw new InvalidArgumentException("The {$chartName} points for series '{$id}' must be an ordered list.");
            }

            $pointIds = [];
            $points = [];
            foreach ($item['points'] as $pointIndex => $point) {
                $normalizedPoint = self::point(
                    $point,
                    $id,
                    $pointIndex,
                    $xType,
                    $chartName,
                    $allowDeferredTypedX,
                );
                if (array_key_exists($normalizedPoint['id'], $pointIds)) {
                    throw new InvalidArgumentException(
                        "The {$chartName} point id '{$normalizedPoint['id']}' for series '{$id}' must be unique."
                    );
                }
                $pointIds[$normalizedPoint['id']] = true;
                $points[] = $normalizedPoint;
            }

            $normalized[] = ['id' => $id, 'name' => $name, 'color' => $color, 'points' => $points];
        }

        return $normalized;
    }

    /** @return array{id: string, label: string, value: int|float, x: int|float|string} */
    private static function point(
        mixed $point,
        string $seriesId,
        int $index,
        string $xType,
        string $chartName,
        bool $allowDeferredTypedX,
    ): array {
        if (! is_array($point)) {
            throw new InvalidArgumentException(
                "The {$chartName} point at index {$index} for series '{$seriesId}' must be an array."
            );
        }

        $label = self::requiredString($point, 'label', "point at index {$index} for series '{$seriesId}'", $chartName);

        if (! array_key_exists('value', $point) || ! is_int($point['value']) && ! is_float($point['value'])) {
            throw new InvalidArgumentException(
                "The {$chartName} value at index {$index} for series '{$seriesId}' must be an integer or float."
            );
        }

        if (! is_finite((float) $point['value'])) {
            throw new InvalidArgumentException(
                "The {$chartName} value at index {$index} for series '{$seriesId}' must be finite."
            );
        }

        self::assertExactInteger($point['value'], $chartName, "value at index {$index} for series '{$seriesId}'");

        $id = array_key_exists('id', $point)
            ? self::requiredString($point, 'id', "point at index {$index} for series '{$seriesId}'", $chartName)
            : self::compatibilityId($seriesId, $label, $index);

        return [
            'id' => $id,
            'label' => $label,
            'value' => $point['value'],
            'x' => self::xValue(
                $point,
                $label,
                $seriesId,
                $index,
                $xType,
                $chartName,
                $allowDeferredTypedX,
            ),
        ];
    }

    private static function xValue(
        array $point,
        string $label,
        string $seriesId,
        int $index,
        string $xType,
        string $chartName,
        bool $allowDeferredTypedX,
    ): int|float|string {
        if ($xType === 'category') {
            if (array_key_exists('x', $point) && ! is_string($point['x'])) {
                if ($allowDeferredTypedX) {
                    return $label;
                }

                throw new InvalidArgumentException(
                    "The {$chartName} category x value at index {$index} for series '{$seriesId}' must be a non-empty string."
                );
            }

            if (array_key_exists('x', $point) && trim($point['x']) === '') {
                throw new InvalidArgumentException(
                    "The {$chartName} category x value at index {$index} for series '{$seriesId}' must be a non-empty string."
                );
            }

            return array_key_exists('x', $point) && is_string($point['x']) ? trim($point['x']) : $label;
        }

        if (! array_key_exists('x', $point)) {
            throw new InvalidArgumentException(
                "The {$chartName} x value at index {$index} for series '{$seriesId}' is required for a {$xType} axis."
            );
        }

        return self::normalizeTypedX($point['x'], $xType, $chartName, "point at index {$index} for series '{$seriesId}'");
    }

    public static function normalizeTypedX(mixed $value, string $xType, string $chartName, string $context): int|float|string
    {
        if ($xType === 'number') {
            if ((! is_int($value) && ! is_float($value)) || ! is_finite((float) $value)) {
                throw new InvalidArgumentException("The {$chartName} x value for {$context} must be a finite integer or float.");
            }

            self::assertExactInteger($value, $chartName, "x value for {$context}");

            return $value;
        }

        if ($xType === 'date') {
            if ($value instanceof DateTimeInterface) {
                return $value->format('Y-m-d');
            }

            if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
                throw new InvalidArgumentException("The {$chartName} x value for {$context} must use YYYY-MM-DD.");
            }

            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
            if ($date === false || $date->format('Y-m-d') !== $value) {
                throw new InvalidArgumentException("The {$chartName} x value for {$context} must be a valid calendar date.");
            }

            return $value;
        }

        if ($xType === 'datetime') {
            if ($value instanceof DateTimeInterface) {
                return self::formatDateTime($value);
            }

            if (
                ! is_string($value)
                || preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(?::\d{2}(?:\.\d{1,6})?)?(?:Z|[+-]\d{2}:\d{2})$/', $value) !== 1
            ) {
                throw new InvalidArgumentException(
                    "The {$chartName} x value for {$context} must be an RFC 3339 datetime with an offset or Z."
                );
            }

            try {
                $datetime = new DateTimeImmutable($value);
                $errors = DateTimeImmutable::getLastErrors();
                if (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
                    throw new InvalidArgumentException(
                        "The {$chartName} x value for {$context} must be a valid datetime."
                    );
                }

                return self::formatDateTime($datetime, $value);
            } catch (InvalidArgumentException $exception) {
                throw $exception;
            } catch (\Exception) {
                throw new InvalidArgumentException("The {$chartName} x value for {$context} must be a valid datetime.");
            }
        }

        throw new InvalidArgumentException("The {$chartName} x axis type '{$xType}' is not supported.");
    }

    private static function compatibilityId(string $seriesId, string $label, int $index): string
    {
        return 'compat-'.substr(hash('sha256', $seriesId."\0".$label."\0".$index), 0, 16);
    }

    private static function assertExactInteger(int|float $value, string $chartName, string $context): void
    {
        if (is_int($value) && abs($value) > self::MAX_EXACT_INTEGER) {
            throw new InvalidArgumentException(
                "The {$chartName} {$context} must be within the exact cross-platform integer range."
            );
        }
    }

    private static function formatDateTime(DateTimeInterface $datetime, ?string $source = null): string
    {
        $fraction = null;
        if ($source !== null && preg_match('/\.(\d{1,6})(?:Z|[+-]\d{2}:\d{2})$/', $source, $matches) === 1) {
            $fraction = $matches[1];
        } elseif ($datetime->format('u') !== '000000') {
            $fraction = rtrim($datetime->format('u'), '0');
        }

        $base = $datetime->format('Y-m-d\TH:i:s');

        return $base.($fraction === null ? '' : '.'.$fraction).$datetime->format('P');
    }

    /** @param array<string, mixed> $values */
    private static function requiredString(array $values, string $key, string $context, string $chartName): string
    {
        if (! array_key_exists($key, $values) || ! is_string($values[$key]) || trim($values[$key]) === '') {
            throw new InvalidArgumentException("The {$chartName} {$key} for {$context} must be a non-empty string.");
        }

        return trim($values[$key]);
    }
}
