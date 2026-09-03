<?php

namespace Donmanueldev\NativephpCharts\Support;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Canonicalizes public axis options before Swift and Kotlin decode them.
 *
 * Public camelCase compatibility aliases are accepted where documented, while the
 * returned map uses snake_case keys only. Explicit domains reuse the same numeric,
 * date, and datetime representation as series points, annotations, and viewports.
 */
final class AxisNormalizer
{
    /**
     * Normalize a category, number, date, or datetime x-axis definition.
     *
     * Date/time formatting and timezone keys are always present in the result. A
     * category axis rejects explicit domain values because it has no continuous scale.
     * Input accepts `dateFormat`, `timeZone`, and `labelCount` aliases; output uses
     * `date_format`, `timezone`, and `label_count`.
     *
     * @param  array<string, mixed>  $axis
     * @param  array<string, mixed>  $defaults  Previously normalized values to retain when omitted.
     * @return array{type: string, date_format: string, timezone: string, visible?: bool, label_count?: int, title?: string, minimum?: float|int|string, maximum?: float|int|string, baseline?: float|int|string, interval?: float}
     *
     * @throws InvalidArgumentException When an option, timezone, or domain is invalid.
     */
    public static function x(array $axis, string $chartName, array $defaults = []): array
    {
        self::rejectUnknownKeys(
            $axis,
            [
                'type', 'date_format', 'dateFormat', 'timezone', 'timeZone',
                'visible', 'label_count', 'labelCount',
                'title', 'minimum', 'maximum', 'baseline', 'interval',
            ],
            $chartName,
            'x axis',
        );

        $type = $axis['type'] ?? $defaults['type'] ?? 'category';
        if (! is_string($type) || ! in_array($type, ['category', 'number', 'date', 'datetime'], true)) {
            throw new InvalidArgumentException("The {$chartName} x axis type must be category, number, date, or datetime.");
        }

        $dateFormat = $axis['date_format'] ?? $axis['dateFormat'] ?? $defaults['date_format'] ?? 'medium';
        if (! is_string($dateFormat) || ! in_array($dateFormat, ['short', 'medium', 'long', 'full', 'time'], true)) {
            throw new InvalidArgumentException("The {$chartName} x axis date format must be short, medium, long, full, or time.");
        }

        $timezone = $axis['timezone'] ?? $axis['timeZone'] ?? $defaults['timezone'] ?? '';
        if (! is_string($timezone)) {
            throw new InvalidArgumentException("The {$chartName} x axis timezone must be an IANA timezone string.");
        }

        $timezone = trim($timezone);
        if ($timezone !== '' && ! in_array($timezone, DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC), true)) {
            throw new InvalidArgumentException("The {$chartName} x axis timezone must be a valid IANA timezone.");
        }

        $normalized = ['type' => $type, 'date_format' => $dateFormat, 'timezone' => $timezone];
        self::appendVisibilityAndLabelCount($normalized, $axis, $defaults, $chartName, 'x axis');
        self::appendTitle($normalized, $axis, $defaults, $chartName, 'x axis');
        self::appendXAxisDomain($normalized, $axis, $defaults, $type, $chartName);

        return $normalized;
    }

    /**
     * Normalize numeric formatting, visibility, and domain options for the y axis.
     *
     * Set `$validateComplete` to false only while merging fluent configuration. The
     * final wire snapshot must validate again so currency and fraction-digit rules are
     * checked against the complete state.
     * Input accepts the public camelCase aliases and legacy `format`; output always
     * uses canonical snake_case keys.
     *
     * @param  array<string, mixed>  $axis
     * @param  array<string, mixed>  $defaults  Previously normalized values to retain when omitted.
     * @return array{value_format: string, currency_code: string, minimum_fraction_digits: int, maximum_fraction_digits: int, visible?: bool, label_count?: int, title?: string, minimum?: float|int, maximum?: float|int, baseline?: float|int, interval?: float, begin_at_zero?: bool}
     *
     * @throws InvalidArgumentException When formatting, visibility, or domain values are invalid.
     */
    public static function y(array $axis, string $chartName, array $defaults = [], bool $validateComplete = true): array
    {
        self::rejectUnknownKeys(
            $axis,
            [
                'value_format', 'valueFormat', 'format',
                'currency_code', 'currencyCode',
                'minimum_fraction_digits', 'minimumFractionDigits',
                'maximum_fraction_digits', 'maximumFractionDigits',
                'visible', 'label_count', 'labelCount', 'begin_at_zero', 'beginAtZero',
                'title', 'minimum', 'maximum', 'baseline', 'interval',
            ],
            $chartName,
            'y axis',
        );

        $valueFormat = $axis['value_format']
            ?? $axis['valueFormat']
            ?? $axis['format']
            ?? $defaults['value_format']
            ?? 'number';
        if (! is_string($valueFormat) || ! in_array($valueFormat, ['number', 'currency', 'percent'], true)) {
            throw new InvalidArgumentException("The {$chartName} y axis value format must be number, currency, or percent.");
        }

        $currencyCode = $axis['currency_code']
            ?? $axis['currencyCode']
            ?? $defaults['currency_code']
            ?? '';
        if (! is_string($currencyCode)) {
            throw new InvalidArgumentException("The {$chartName} y axis currency code must be a string.");
        }

        $currencyCode = strtoupper(trim($currencyCode));
        if ($currencyCode !== '' && preg_match('/^[A-Z]{3}$/', $currencyCode) !== 1) {
            throw new InvalidArgumentException("The {$chartName} currency code must be a three-letter code.");
        }

        $minimum = self::fractionDigits(
            $axis['minimum_fraction_digits']
                ?? $axis['minimumFractionDigits']
                ?? $defaults['minimum_fraction_digits']
                ?? -1,
            $chartName,
            'minimum fraction digits',
        );
        $maximum = self::fractionDigits(
            $axis['maximum_fraction_digits']
                ?? $axis['maximumFractionDigits']
                ?? $defaults['maximum_fraction_digits']
                ?? -1,
            $chartName,
            'maximum fraction digits',
        );

        if ($validateComplete && $valueFormat === 'currency' && $currencyCode === '') {
            throw new InvalidArgumentException("The {$chartName} currency code is required when value format is currency.");
        }

        if ($validateComplete && $minimum !== -1 && $maximum !== -1 && $minimum > $maximum) {
            throw new InvalidArgumentException("The {$chartName} minimum fraction digits cannot exceed maximum fraction digits.");
        }

        $normalized = [
            'value_format' => $valueFormat,
            'currency_code' => $currencyCode,
            'minimum_fraction_digits' => $minimum,
            'maximum_fraction_digits' => $maximum,
        ];
        self::appendVisibilityAndLabelCount($normalized, $axis, $defaults, $chartName, 'y axis');
        self::appendTitle($normalized, $axis, $defaults, $chartName, 'y axis');
        self::appendNumericDomain($normalized, $axis, $defaults, $chartName, 'y axis');

        $beginAtZero = $axis['begin_at_zero'] ?? $axis['beginAtZero'] ?? $defaults['begin_at_zero'] ?? null;
        if ($beginAtZero !== null) {
            $normalized['begin_at_zero'] = self::strictBoolean($beginAtZero, $chartName, 'y axis beginAtZero');
        }

        return $normalized;
    }

    /** @param array<string, bool|float|int|string> $normalized */
    private static function appendVisibilityAndLabelCount(
        array &$normalized,
        array $axis,
        array $defaults,
        string $chartName,
        string $axisName,
    ): void {
        $visible = $axis['visible'] ?? $defaults['visible'] ?? null;
        if ($visible !== null) {
            $normalized['visible'] = self::strictBoolean($visible, $chartName, "{$axisName} visible");
        }

        $labelCount = $axis['label_count'] ?? $axis['labelCount'] ?? $defaults['label_count'] ?? null;
        if ($labelCount !== null) {
            if (! is_int($labelCount) || $labelCount < 2 || $labelCount > 12) {
                throw new InvalidArgumentException("The {$chartName} {$axisName} label count must be between 2 and 12.");
            }

            $normalized['label_count'] = $labelCount;
        }
    }

    /** @param array<string, bool|float|int|string> $normalized */
    private static function appendTitle(
        array &$normalized,
        array $axis,
        array $defaults,
        string $chartName,
        string $axisName,
    ): void {
        $title = $axis['title'] ?? $defaults['title'] ?? null;
        if ($title === null) {
            return;
        }

        if (! is_string($title) || trim($title) === '') {
            throw new InvalidArgumentException("The {$chartName} {$axisName} title must be a non-empty string.");
        }

        $normalized['title'] = trim($title);
    }

    /**
     * Normalize a continuous x domain and keep its bounds in their declared x type.
     *
     * @param  array<string, bool|float|int|string>  $normalized
     */
    private static function appendXAxisDomain(
        array &$normalized,
        array $axis,
        array $defaults,
        string $type,
        string $chartName,
    ): void {
        $domainKeys = ['minimum', 'maximum', 'baseline', 'interval'];
        $hasDomain = array_any($domainKeys, fn (string $key): bool => array_key_exists($key, $axis) || array_key_exists($key, $defaults));

        if (! $hasDomain) {
            return;
        }

        if ($type === 'category') {
            throw new InvalidArgumentException("The {$chartName} category x axis does not support an explicit domain.");
        }

        foreach (['minimum', 'maximum', 'baseline'] as $key) {
            if (array_key_exists($key, $axis) || array_key_exists($key, $defaults)) {
                $normalized[$key] = ChartDataNormalizer::normalizeTypedX(
                    $axis[$key] ?? $defaults[$key],
                    $type,
                    $chartName,
                    "x axis {$key}",
                );
            }
        }

        self::appendInterval($normalized, $axis, $defaults, $chartName, 'x axis');
        self::validateDomain($normalized, $type, $chartName, 'x axis');
    }

    /** @param array<string, bool|float|int|string> $normalized */
    private static function appendNumericDomain(
        array &$normalized,
        array $axis,
        array $defaults,
        string $chartName,
        string $axisName,
    ): void {
        foreach (['minimum', 'maximum', 'baseline'] as $key) {
            if (array_key_exists($key, $axis) || array_key_exists($key, $defaults)) {
                $value = $axis[$key] ?? $defaults[$key];
                if ((! is_int($value) && ! is_float($value)) || ! is_finite((float) $value)) {
                    throw new InvalidArgumentException("The {$chartName} {$axisName} {$key} must be a finite integer or float.");
                }

                ChartDataNormalizer::assertExactNumber($value, $chartName, "{$axisName} {$key}");
                $normalized[$key] = $value;
            }
        }

        self::appendInterval($normalized, $axis, $defaults, $chartName, $axisName);
        self::validateDomain($normalized, 'number', $chartName, $axisName);
    }

    /** @param array<string, bool|float|int|string> $normalized */
    private static function appendInterval(
        array &$normalized,
        array $axis,
        array $defaults,
        string $chartName,
        string $axisName,
    ): void {
        if (! array_key_exists('interval', $axis) && ! array_key_exists('interval', $defaults)) {
            return;
        }

        $interval = $axis['interval'] ?? $defaults['interval'];
        if ((! is_int($interval) && ! is_float($interval)) || ! is_finite((float) $interval) || $interval <= 0) {
            throw new InvalidArgumentException("The {$chartName} {$axisName} interval must be greater than zero.");
        }

        ChartDataNormalizer::assertExactNumber($interval, $chartName, "{$axisName} interval");
        $normalized['interval'] = (float) $interval;
    }

    /**
     * Enforce ordered bounds and keep an optional baseline inside the explicit range.
     *
     * @param  array<string, bool|float|int|string>  $normalized
     */
    private static function validateDomain(array $normalized, string $type, string $chartName, string $axisName): void
    {
        if (
            array_key_exists('minimum', $normalized)
            && array_key_exists('maximum', $normalized)
            && self::compareDomainValues($normalized['minimum'], $normalized['maximum'], $type) >= 0
        ) {
            throw new InvalidArgumentException("The {$chartName} {$axisName} minimum must be less than maximum.");
        }

        if (! array_key_exists('baseline', $normalized)) {
            return;
        }

        $belowMinimum = array_key_exists('minimum', $normalized)
            && self::compareDomainValues($normalized['baseline'], $normalized['minimum'], $type) < 0;
        $aboveMaximum = array_key_exists('maximum', $normalized)
            && self::compareDomainValues($normalized['baseline'], $normalized['maximum'], $type) > 0;

        if ($belowMinimum || $aboveMaximum) {
            throw new InvalidArgumentException("The {$chartName} {$axisName} baseline must be within the explicit domain.");
        }
    }

    /** Compare canonical domain values without losing datetime sub-second precision. */
    private static function compareDomainValues(float|int|string $left, float|int|string $right, string $type): int
    {
        if ($type === 'number') {
            return (float) $left <=> (float) $right;
        }

        if ($type === 'date') {
            return $left <=> $right;
        }

        return (new DateTimeImmutable((string) $left))->format('U.u')
            <=> (new DateTimeImmutable((string) $right))->format('U.u');
    }

    private static function strictBoolean(mixed $value, string $chartName, string $property): bool
    {
        if (! is_bool($value)) {
            throw new InvalidArgumentException("The {$chartName} {$property} must be a boolean.");
        }

        return $value;
    }

    private static function fractionDigits(mixed $digits, string $chartName, string $property): int
    {
        if (! is_int($digits) || $digits < -1 || $digits > 8) {
            throw new InvalidArgumentException("The {$chartName} {$property} must be -1 or between 0 and 8.");
        }

        return $digits;
    }

    /** @param list<string> $allowed */
    private static function rejectUnknownKeys(array $values, array $allowed, string $chartName, string $context): void
    {
        foreach ($values as $key => $value) {
            if (! is_string($key) || ! in_array($key, $allowed, true)) {
                throw new InvalidArgumentException("The {$chartName} {$context} option '{$key}' is not supported.");
            }
        }
    }
}
