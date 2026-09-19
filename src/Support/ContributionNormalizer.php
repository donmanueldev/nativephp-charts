<?php

namespace Donmanueldev\NativephpCharts\Support;

use DateTimeImmutable;
use InvalidArgumentException;

final class ContributionNormalizer
{
    /** @param array<int, mixed> $values
     *  @return list<array{id: string, date: string, value: int|float, label?: string}>
     */
    public static function normalize(array $values): array
    {
        if (! array_is_list($values)) {
            throw new InvalidArgumentException('The contribution heatmap values must be an ordered list.');
        }
        if (count($values) > 10_000) {
            throw new InvalidArgumentException('The contribution heatmap supports at most 10,000 values.');
        }

        $normalized = [];
        $ids = [];
        $dates = [];
        foreach ($values as $index => $item) {
            if (! is_array($item)) {
                throw new InvalidArgumentException("The contribution heatmap value at index {$index} must be an array.");
            }
            foreach ($item as $key => $_) {
                if (! is_string($key) || ! in_array($key, ['id', 'date', 'value', 'label'], true)) {
                    throw new InvalidArgumentException("The contribution heatmap option '{$key}' at index {$index} is not supported.");
                }
            }
            $id = self::text($item['id'] ?? null, "id at index {$index}");
            if (isset($ids[$id])) {
                throw new InvalidArgumentException("The contribution heatmap id '{$id}' must be unique.");
            }
            $ids[$id] = true;
            $date = self::date($item['date'] ?? null, "date at index {$index}");
            if (isset($dates[$date])) {
                throw new InvalidArgumentException("The contribution heatmap date '{$date}' must be unique.");
            }
            $dates[$date] = true;
            $value = $item['value'] ?? null;
            if ((! is_int($value) && ! is_float($value)) || ! is_finite((float) $value) || $value < 0) {
                throw new InvalidArgumentException("The contribution heatmap value at index {$index} must be a non-negative finite number.");
            }
            if (is_int($value) && $value > 9_007_199_254_740_991) {
                throw new InvalidArgumentException("The contribution heatmap value at index {$index} must be within the exact cross-platform integer range.");
            }

            $entry = ['id' => $id, 'date' => $date, 'value' => $value];
            if (array_key_exists('label', $item)) {
                $entry['label'] = self::text($item['label'], "label at index {$index}");
            }
            $normalized[] = $entry;
        }

        usort($normalized, static fn (array $left, array $right): int => $left['date'] <=> $right['date']);

        return $normalized;
    }

    public static function date(mixed $value, string $property): string
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            throw new InvalidArgumentException("The contribution heatmap {$property} must be an ISO date (YYYY-MM-DD).");
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException("The contribution heatmap {$property} must be a valid ISO date.");
        }

        return $value;
    }

    private static function text(mixed $value, string $property): string
    {
        if (! is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException("The contribution heatmap {$property} must be a non-empty string.");
        }

        return trim($value);
    }
}
