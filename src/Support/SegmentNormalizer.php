<?php

namespace Donmanueldev\NativephpCharts\Support;

use InvalidArgumentException;

/**
 * Validates pie and donut segments before they are serialized to native code.
 *
 * Segment order is significant for drawing and selection. Identifiers are unique,
 * values are non-negative finite numbers, and any non-empty chart must contain a
 * positive value so the renderers always have a meaningful radial domain.
 */
final class SegmentNormalizer
{
    private const int MAX_EXACT_INTEGER = 9_007_199_254_740_991;

    /**
     * Each input segment requires `id`, `label`, `value`, and `color`; no other
     * keys are accepted.
     *
     * @param  array<int, mixed>  $segments
     * @return list<array{id: string, label: string, value: int|float, color: string}>
     *
     * @throws InvalidArgumentException When the ordered segment contract is violated.
     */
    public static function normalize(array $segments, string $chartName): array
    {
        if (! array_is_list($segments)) {
            throw new InvalidArgumentException("The {$chartName} segments must be an ordered list.");
        }

        $normalized = [];
        $ids = [];
        $hasPositiveValue = false;

        foreach ($segments as $index => $segment) {
            if (! is_array($segment)) {
                throw new InvalidArgumentException("The {$chartName} segment at index {$index} must be an array.");
            }

            self::rejectUnknownKeys($segment, $index, $chartName);
            $id = self::requiredString($segment, 'id', $index, $chartName);
            if (array_key_exists($id, $ids)) {
                throw new InvalidArgumentException("The {$chartName} segment id '{$id}' must be unique.");
            }
            $ids[$id] = true;

            $value = $segment['value'] ?? null;
            if ((! is_int($value) && ! is_float($value)) || ! is_finite((float) $value) || $value < 0) {
                throw new InvalidArgumentException(
                    "The {$chartName} segment value at index {$index} must be a finite number greater than or equal to zero."
                );
            }
            if (is_int($value) && $value > self::MAX_EXACT_INTEGER) {
                throw new InvalidArgumentException(
                    "The {$chartName} segment value at index {$index} must be within the exact cross-platform integer range."
                );
            }

            $hasPositiveValue = $hasPositiveValue || $value > 0;
            $normalized[] = [
                'id' => $id,
                'label' => self::requiredString($segment, 'label', $index, $chartName),
                'value' => $value,
                'color' => ColorNormalizer::normalize(
                    self::requiredString($segment, 'color', $index, $chartName),
                    $chartName,
                    "segment '{$id}'",
                ),
            ];
        }

        if ($normalized !== [] && ! $hasPositiveValue) {
            throw new InvalidArgumentException("The {$chartName} segments must contain at least one value greater than zero.");
        }

        return $normalized;
    }

    /** @param array<string|int, mixed> $segment */
    private static function rejectUnknownKeys(array $segment, int $index, string $chartName): void
    {
        foreach ($segment as $key => $value) {
            if (! is_string($key) || ! in_array($key, ['id', 'label', 'value', 'color'], true)) {
                throw new InvalidArgumentException(
                    "The {$chartName} segment option '{$key}' at index {$index} is not supported."
                );
            }
        }
    }

    /** @param array<string|int, mixed> $segment */
    private static function requiredString(array $segment, string $key, int $index, string $chartName): string
    {
        $value = $segment[$key] ?? null;
        if (! is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException(
                "The {$chartName} segment {$key} at index {$index} must be a non-empty string."
            );
        }

        return trim($value);
    }
}
