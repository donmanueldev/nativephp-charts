<?php

namespace Donmanueldev\NativephpCharts\Support;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * Defines the canonical Cartesian series contract shared by PHP, Swift, and Kotlin.
 *
 * It validates stable identity, typed x values, finite/exact numeric values, optional
 * uncertainty ranges, candlestick OHLC data, series styles, and inter-series fills.
 * Every accepted value is safe to encode without platform-dependent coercion.
 */
final class ChartDataNormalizer
{
    private const int MAX_EXACT_INTEGER = 9_007_199_254_740_991;

    /**
     * Normalize ordered public series into renderer-ready maps.
     *
     * Each series requires `id`, `name`, `color`, and an ordered `points` list. A
     * regular point requires `label` and `value`, plus `x` for continuous axes;
     * candlestick points instead require `open`, `high`, `low`, and `close`.
     *
     * Output series always contain `id`, `name`, `color`, and canonical `points`.
     * Output points always contain `id`, `label`, `value`, and `x`; uncertainty,
     * candle, style, and fill keys are included only when configured.
     *
     * Points without an explicit `id` receive a deterministic compatibility ID based
     * on series ID, label, and original index. `$allowDeferredTypedX` exists only for
     * attribute application before the final x-axis type is known; final wire encoding
     * always calls this method again with strict typed-x validation.
     *
     * @param  array<int, mixed>  $series
     * @return list<array<string, mixed>>
     *
     * @throws InvalidArgumentException When series, point, style, or fill invariants are violated.
     */
    public static function normalize(
        array $series,
        string $xType,
        string $chartName,
        string $chartType,
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
            self::rejectUnknownKeys($item, self::seriesKeys($chartType), $chartName, "series at index {$index}");

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
                    $chartType,
                );
                if (array_key_exists($normalizedPoint['id'], $pointIds)) {
                    throw new InvalidArgumentException(
                        "The {$chartName} point id '{$normalizedPoint['id']}' for series '{$id}' must be unique."
                    );
                }
                $pointIds[$normalizedPoint['id']] = true;
                $points[] = $normalizedPoint;
            }

            $normalizedSeries = ['id' => $id, 'name' => $name, 'color' => $color, 'points' => $points];
            if (array_key_exists('style', $item)) {
                if (! is_array($item['style'])) {
                    throw new InvalidArgumentException("The {$chartName} style for series '{$id}' must be an array.");
                }

                if (array_intersect(array_keys($item['style']), ['grid', 'axis']) !== []) {
                    throw new InvalidArgumentException("The {$chartName} series style for '{$id}' cannot configure grid or axis options.");
                }

                $normalizedSeries['style'] = ChartStyleNormalizer::normalize($item['style'], $chartType, $chartName);
            }
            if (array_key_exists('fill_to', $item)) {
                if (! in_array($chartType, ['line', 'area'], true)) {
                    throw new InvalidArgumentException("The {$chartName} fill_to option is only supported by line and area charts.");
                }

                $normalizedSeries['fill_to'] = self::requiredString($item, 'fill_to', "series '{$id}'", $chartName);
            }

            $normalized[] = $normalizedSeries;
        }

        foreach ($normalized as $item) {
            if (! array_key_exists('fill_to', $item)) {
                continue;
            }

            if ($item['fill_to'] === $item['id'] || ! array_key_exists($item['fill_to'], $seriesIds)) {
                throw new InvalidArgumentException(
                    "The {$chartName} fill target '{$item['fill_to']}' must reference another series."
                );
            }
        }

        return $normalized;
    }

    /** @return array<string, float|int|string> */
    private static function point(
        mixed $point,
        string $seriesId,
        int $index,
        string $xType,
        string $chartName,
        bool $allowDeferredTypedX,
        string $chartType,
    ): array {
        if (! is_array($point)) {
            throw new InvalidArgumentException(
                "The {$chartName} point at index {$index} for series '{$seriesId}' must be an array."
            );
        }
        self::rejectUnknownKeys(
            $point,
            self::pointKeys($chartType),
            $chartName,
            "point at index {$index} for series '{$seriesId}'",
        );

        $label = self::requiredString($point, 'label', "point at index {$index} for series '{$seriesId}'", $chartName);

        $candle = null;
        if ($chartType === 'candlestick') {
            $candle = self::candlestick($point, $seriesId, $index, $chartName);
            $point['value'] = $candle['close'];
        }

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

        self::assertExactNumber($point['value'], $chartName, "value at index {$index} for series '{$seriesId}'");

        $id = array_key_exists('id', $point)
            ? self::requiredString($point, 'id', "point at index {$index} for series '{$seriesId}'", $chartName)
            : self::compatibilityId($seriesId, $label, $index);

        $normalized = [
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

        if ($candle !== null) {
            return [...$normalized, ...$candle, 'error_min' => $candle['low'], 'error_max' => $candle['high']];
        }

        $hasMinimumError = array_key_exists('error_min', $point);
        $hasMaximumError = array_key_exists('error_max', $point);
        if ($hasMinimumError !== $hasMaximumError) {
            throw new InvalidArgumentException(
                "The {$chartName} error range at index {$index} for series '{$seriesId}' must define both error_min and error_max."
            );
        }

        if ($hasMinimumError) {
            $errorMinimum = self::normalizeNumber(
                $point['error_min'],
                $chartName,
                "error_min at index {$index} for series '{$seriesId}'",
            );
            $errorMaximum = self::normalizeNumber(
                $point['error_max'],
                $chartName,
                "error_max at index {$index} for series '{$seriesId}'",
            );
            if ($errorMinimum > $point['value'] || $errorMaximum < $point['value'] || $errorMinimum >= $errorMaximum) {
                throw new InvalidArgumentException(
                    "The {$chartName} error range at index {$index} for series '{$seriesId}' must contain its value."
                );
            }

            $normalized['error_min'] = $errorMinimum;
            $normalized['error_max'] = $errorMaximum;
        }

        return $normalized;
    }

    /** @return array{open: float|int, high: float|int, low: float|int, close: float|int} */
    private static function candlestick(array $point, string $seriesId, int $index, string $chartName): array
    {
        $values = [];
        foreach (['open', 'high', 'low', 'close'] as $key) {
            if (! array_key_exists($key, $point)) {
                throw new InvalidArgumentException("The {$chartName} {$key} at index {$index} for series '{$seriesId}' is required.");
            }
            $values[$key] = self::normalizeNumber(
                $point[$key],
                $chartName,
                "{$key} at index {$index} for series '{$seriesId}'",
            );
        }

        if (
            $values['low'] > min($values['open'], $values['close'])
            || $values['high'] < max($values['open'], $values['close'])
            || $values['low'] >= $values['high']
        ) {
            throw new InvalidArgumentException("The {$chartName} OHLC range at index {$index} for series '{$seriesId}' is invalid.");
        }

        return $values;
    }

    /**
     * Resolve a point's canonical x value, using its label only for category compatibility.
     */
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

    /**
     * Normalize one x value according to the cross-platform wire type.
     *
     * Numbers remain numeric, dates use `YYYY-MM-DD`, and datetimes become RFC 3339
     * strings with an explicit offset. DateTime objects and `Z` input are canonicalized
     * without discarding supplied microseconds.
     *
     * @throws InvalidArgumentException When the type is unsupported or the value is invalid.
     */
    public static function normalizeTypedX(mixed $value, string $xType, string $chartName, string $context): int|float|string
    {
        if ($xType === 'number') {
            if ((! is_int($value) && ! is_float($value)) || ! is_finite((float) $value)) {
                throw new InvalidArgumentException("The {$chartName} x value for {$context} must be a finite integer or float.");
            }

            self::assertExactNumber($value, $chartName, "x value for {$context}");

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

    /**
     * Derive the stable legacy ID used when older point payloads omit `id`.
     */
    private static function compatibilityId(string $seriesId, string $label, int $index): string
    {
        return 'compat-'.substr(hash('sha256', $seriesId."\0".$label."\0".$index), 0, 16);
    }

    /**
     * Reject integers that JSON-backed Swift and Kotlin consumers cannot represent exactly.
     *
     * @throws InvalidArgumentException When an integer exceeds the IEEE-754 safe range.
     */
    public static function assertExactNumber(int|float $value, string $chartName, string $context): void
    {
        if (is_int($value) && abs($value) > self::MAX_EXACT_INTEGER) {
            throw new InvalidArgumentException(
                "The {$chartName} {$context} must be within the exact cross-platform integer range."
            );
        }
    }

    private static function normalizeNumber(mixed $value, string $chartName, string $context): int|float
    {
        if ((! is_int($value) && ! is_float($value)) || ! is_finite((float) $value)) {
            throw new InvalidArgumentException("The {$chartName} {$context} must be a finite integer or float.");
        }

        self::assertExactNumber($value, $chartName, $context);

        return $value;
    }

    /**
     * Emit a canonical RFC 3339 value while retaining meaningful fractional seconds.
     */
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

    /** @return list<string> */
    private static function seriesKeys(string $chartType): array
    {
        return [
            'id',
            'name',
            'color',
            'points',
            'style',
            ...in_array($chartType, ['line', 'area'], true) ? ['fill_to'] : [],
        ];
    }

    /** @return list<string> */
    private static function pointKeys(string $chartType): array
    {
        if ($chartType === 'candlestick') {
            return ['id', 'label', 'x', 'open', 'high', 'low', 'close'];
        }

        return ['id', 'label', 'x', 'value', 'error_min', 'error_max'];
    }

    /**
     * @param  array<string|int, mixed>  $values
     * @param  list<string>  $allowed
     */
    private static function rejectUnknownKeys(array $values, array $allowed, string $chartName, string $context): void
    {
        foreach ($values as $key => $value) {
            if (! is_string($key) || ! in_array($key, $allowed, true)) {
                throw new InvalidArgumentException("The {$chartName} {$context} option '{$key}' is not supported.");
            }
        }
    }
}
