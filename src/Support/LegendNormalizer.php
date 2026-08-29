<?php

namespace Donmanueldev\NativephpCharts\Support;

use InvalidArgumentException;

final class LegendNormalizer
{
    /**
     * @return array{visible: bool, position: string, alignment: string, style: array<string, float|string>|object}
     */
    public static function normalize(array $legend, int $seriesCount, string $chartName): array
    {
        self::rejectUnknownKeys($legend, ['visible', 'position', 'alignment', 'style'], $chartName);

        $visible = $legend['visible'] ?? 'auto';
        if ($visible === 'auto') {
            $visible = $seriesCount > 1;
        } elseif (! is_bool($visible)) {
            throw new InvalidArgumentException("The {$chartName} legend visible option must be a boolean or auto.");
        }

        $position = $legend['position'] ?? 'bottom';
        if (! is_string($position) || ! in_array($position, ['top', 'bottom', 'leading', 'trailing'], true)) {
            throw new InvalidArgumentException("The {$chartName} legend position must be top, bottom, leading, or trailing.");
        }

        $alignment = $legend['alignment'] ?? 'center';
        if (! is_string($alignment) || ! in_array($alignment, ['start', 'center', 'end'], true)) {
            throw new InvalidArgumentException("The {$chartName} legend alignment must be start, center, or end.");
        }

        $style = $legend['style'] ?? [];
        if (! is_array($style)) {
            throw new InvalidArgumentException("The {$chartName} legend style must be an array.");
        }

        $normalizedStyle = self::style($style, $chartName);

        return [
            'visible' => $visible,
            'position' => $position,
            'alignment' => $alignment,
            'style' => $normalizedStyle === [] ? (object) [] : $normalizedStyle,
        ];
    }

    /** @return array<string, float|string> */
    private static function style(array $style, string $chartName): array
    {
        self::rejectUnknownKeys($style, ['font', 'font_size', 'fontSize', 'label_color', 'labelColor', 'marker_size', 'markerSize'], $chartName, ' style');

        $normalized = [];
        if (array_key_exists('font', $style)) {
            if (! is_string($style['font']) || trim($style['font']) === '') {
                throw new InvalidArgumentException("The {$chartName} legend style font must be a non-empty string.");
            }

            $normalized['font'] = trim($style['font']);
        }

        $fontSize = $style['font_size'] ?? $style['fontSize'] ?? null;
        if ($fontSize !== null) {
            $normalized['font_size'] = self::positiveNumber($fontSize, $chartName, 'font size', 32.0);
        }

        $labelColor = $style['label_color'] ?? $style['labelColor'] ?? null;
        if ($labelColor !== null) {
            $normalized['label_color'] = ColorNormalizer::normalize($labelColor, $chartName, 'legend style label color');
        }

        $markerSize = $style['marker_size'] ?? $style['markerSize'] ?? null;
        if ($markerSize !== null) {
            $normalized['marker_size'] = self::positiveNumber($markerSize, $chartName, 'marker size', 32.0);
        }

        return $normalized;
    }

    private static function positiveNumber(mixed $value, string $chartName, string $property, float $maximum): float
    {
        if ((! is_int($value) && ! is_float($value)) || ! is_finite((float) $value) || $value <= 0 || $value > $maximum) {
            throw new InvalidArgumentException(
                "The {$chartName} legend style {$property} must be greater than zero and no more than {$maximum}."
            );
        }

        return (float) $value;
    }

    /** @param list<string> $allowed */
    private static function rejectUnknownKeys(
        array $values,
        array $allowed,
        string $chartName,
        string $context = '',
    ): void {
        foreach ($values as $key => $value) {
            if (! is_string($key) || ! in_array($key, $allowed, true)) {
                throw new InvalidArgumentException("The {$chartName} legend{$context} option '{$key}' is not supported.");
            }
        }
    }
}
