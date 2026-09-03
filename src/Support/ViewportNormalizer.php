<?php

namespace Donmanueldev\NativephpCharts\Support;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Validates the initial x-axis viewport sent to interactive Cartesian renderers.
 *
 * Viewports use the same typed-x normalization as data and events. Category axes are
 * intentionally excluded because their positions are ordinal rather than continuous.
 */
final class ViewportNormalizer
{
    /**
     * Normalize pan/zoom flags, optional bounds, and the minimum zoom span.
     *
     * Bounds are all-or-nothing. Enabling the viewport additionally requires them and
     * guarantees `minimum < maximum` in the selected numeric, date, or datetime domain.
     *
     * @param  array<string, mixed>  $viewport
     * @return array<string, bool|float|int|string>
     *
     * @throws InvalidArgumentException When keys, flags, bounds, or span are incompatible.
     */
    public static function normalize(array $viewport, string $xType, string $chartName): array
    {
        self::rejectUnknownKeys($viewport, ['enabled', 'minimum', 'maximum', 'pan', 'zoom', 'minimum_span', 'minimumSpan'], $chartName);

        $enabled = $viewport['enabled'] ?? false;
        $pan = $viewport['pan'] ?? true;
        $zoom = $viewport['zoom'] ?? true;
        foreach (['enabled' => $enabled, 'pan' => $pan, 'zoom' => $zoom] as $name => $value) {
            if (! is_bool($value)) {
                throw new InvalidArgumentException("The {$chartName} viewport {$name} value must be a boolean.");
            }
        }

        $normalized = compact('enabled', 'pan', 'zoom');
        $hasMinimum = array_key_exists('minimum', $viewport);
        $hasMaximum = array_key_exists('maximum', $viewport);

        if ($enabled && $xType === 'category') {
            throw new InvalidArgumentException("The {$chartName} category x axis does not support a viewport.");
        }

        if ($enabled && (! $hasMinimum || ! $hasMaximum)) {
            throw new InvalidArgumentException("The {$chartName} enabled viewport requires minimum and maximum values.");
        }

        if ($hasMinimum !== $hasMaximum) {
            throw new InvalidArgumentException("The {$chartName} viewport minimum and maximum must be provided together.");
        }

        if ($hasMinimum) {
            $minimum = ChartDataNormalizer::normalizeTypedX($viewport['minimum'], $xType, $chartName, 'viewport minimum');
            $maximum = ChartDataNormalizer::normalizeTypedX($viewport['maximum'], $xType, $chartName, 'viewport maximum');
            if (self::comparable($minimum, $xType) >= self::comparable($maximum, $xType)) {
                throw new InvalidArgumentException("The {$chartName} viewport minimum must be less than maximum.");
            }

            $normalized['minimum'] = $minimum;
            $normalized['maximum'] = $maximum;
        }

        $minimumSpan = $viewport['minimum_span'] ?? $viewport['minimumSpan'] ?? null;
        if ($minimumSpan !== null) {
            if ((! is_int($minimumSpan) && ! is_float($minimumSpan)) || ! is_finite((float) $minimumSpan) || $minimumSpan <= 0) {
                throw new InvalidArgumentException("The {$chartName} viewport minimum span must be greater than zero.");
            }
            ChartDataNormalizer::assertExactNumber($minimumSpan, $chartName, 'viewport minimum span');
            if ($hasMinimum && (float) $minimumSpan > self::comparable($maximum, $xType) - self::comparable($minimum, $xType)) {
                throw new InvalidArgumentException("The {$chartName} viewport minimum span must not exceed the viewport range.");
            }
            $normalized['minimum_span'] = (float) $minimumSpan;
        }

        return $normalized;
    }

    /** Convert typed wire bounds to a scalar solely for ordering and span validation. */
    private static function comparable(float|int|string $value, string $xType): float
    {
        return match ($xType) {
            'number' => (float) $value,
            'date' => (float) strtotime((string) $value),
            'datetime' => (float) (new DateTimeImmutable((string) $value))->format('U.u'),
            default => 0,
        };
    }

    private static function rejectUnknownKeys(array $viewport, array $allowed, string $chartName): void
    {
        $unknown = array_diff(array_keys($viewport), $allowed);
        if ($unknown !== []) {
            throw new InvalidArgumentException("The {$chartName} viewport contains unsupported keys: ".implode(', ', $unknown).'.');
        }
    }
}
