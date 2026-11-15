<?php

namespace Donmanueldev\NativephpCharts\Support;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Orders and positions canonical typed-x values without depending on PHP's default timezone.
 *
 * Inputs must already be canonical (see `ChartDataNormalizer::normalizeTypedX()`):
 * dates are `YYYY-MM-DD` calendar days interpreted in UTC, and datetimes are RFC 3339
 * strings carrying an explicit offset, so neither result depends on `date.timezone`.
 *
 * @internal Shared by the axis, annotation, viewport, viewport-change, and sampling rules.
 */
final class TypedXValue
{
    /**
     * Compare two canonical number, date, or datetime values exactly.
     *
     * Datetime comparison keeps microsecond precision.
     */
    public static function compare(float|int|string $left, float|int|string $right, string $xType): int
    {
        return match ($xType) {
            'number' => (float) $left <=> (float) $right,
            'date' => strcmp((string) $left, (string) $right) <=> 0,
            'datetime' => new DateTimeImmutable((string) $left) <=> new DateTimeImmutable((string) $right),
        };
    }

    /**
     * Map a canonical number, date, or datetime value to a continuous scalar coordinate.
     *
     * Numbers keep their value, dates become UTC midnight in Unix seconds, and datetimes
     * become Unix seconds with their fractional part.
     */
    public static function position(float|int|string $value, string $xType): float
    {
        return match ($xType) {
            'number' => (float) $value,
            'date' => (float) self::utcDate((string) $value)->format('U'),
            'datetime' => (float) (new DateTimeImmutable((string) $value))->format('U.u'),
        };
    }

    private static function utcDate(string $value): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));
        if ($date === false) {
            throw new InvalidArgumentException("The date '{$value}' is not a canonical YYYY-MM-DD value.");
        }

        return $date;
    }
}
