<?php

namespace Donmanueldev\NativephpCharts\Support;

use InvalidArgumentException;

final class ProgressMetricNormalizer
{
    /** @param array<int, mixed> $metrics
     *  @return list<array{id: string, label: string, value: float, color?: string}>
     */
    public static function normalize(array $metrics): array
    {
        if (! array_is_list($metrics)) {
            throw new InvalidArgumentException('The progress chart metrics must be an ordered list.');
        }
        if (count($metrics) > 24) {
            throw new InvalidArgumentException('The progress chart supports at most 24 metrics.');
        }

        $normalized = [];
        $ids = [];
        foreach ($metrics as $index => $metric) {
            if (! is_array($metric)) {
                throw new InvalidArgumentException("The progress chart metric at index {$index} must be an array.");
            }
            foreach ($metric as $key => $_) {
                if (! is_string($key) || ! in_array($key, ['id', 'label', 'value', 'color'], true)) {
                    throw new InvalidArgumentException("The progress chart metric option '{$key}' at index {$index} is not supported.");
                }
            }

            $id = self::text($metric['id'] ?? null, "metric id at index {$index}");
            if (isset($ids[$id])) {
                throw new InvalidArgumentException("The progress chart metric id '{$id}' must be unique.");
            }
            $ids[$id] = true;
            $value = $metric['value'] ?? null;
            if ((! is_int($value) && ! is_float($value)) || ! is_finite((float) $value) || $value < 0 || $value > 1) {
                throw new InvalidArgumentException("The progress chart metric value at index {$index} must be between 0 and 1.");
            }

            $item = [
                'id' => $id,
                'label' => self::text($metric['label'] ?? null, "metric label at index {$index}"),
                'value' => (float) $value,
            ];
            if (array_key_exists('color', $metric)) {
                $item['color'] = ColorNormalizer::normalize($metric['color'], 'progress chart', "metric '{$id}'");
            }
            $normalized[] = $item;
        }

        return $normalized;
    }

    private static function text(mixed $value, string $property): string
    {
        if (! is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException("The progress chart {$property} must be a non-empty string.");
        }

        return trim($value);
    }
}
