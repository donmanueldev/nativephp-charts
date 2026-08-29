<?php

namespace Donmanueldev\NativephpCharts\Support;

use InvalidArgumentException;

final class ChartStyleNormalizer
{
    /** @return array<string, array<string, bool|float|int|string>> */
    public static function normalize(array $style, string $chartType, string $chartName): array
    {
        $allowed = [
            'line' => ['color', 'width', 'interpolation'],
            'area' => ['opacity', 'gradient'],
            'bar' => ['radius', 'corner_radius', 'cornerRadius', 'width'],
            'segment' => ['gap', 'corner_radius', 'cornerRadius', 'opacity'],
            'points' => ['visible', 'color', 'size'],
            'grid' => ['visible', 'color', 'width'],
            'axis' => ['visible', 'color', 'label_color', 'labelColor', 'font', 'font_size', 'fontSize', 'label_count', 'labelCount'],
        ];
        $sections = match ($chartType) {
            'line' => ['line', 'points', 'grid', 'axis'],
            'area' => ['line', 'area', 'points', 'grid', 'axis'],
            'bar' => ['bar', 'grid', 'axis'],
            'scatter' => ['points', 'grid', 'axis'],
            'pie', 'donut' => ['segment'],
            default => throw new InvalidArgumentException("The chart type '{$chartType}' is not supported."),
        };

        $normalized = [];
        foreach ($style as $section => $values) {
            if (! is_string($section) || ! in_array($section, $sections, true) || ! is_array($values)) {
                throw new InvalidArgumentException(
                    "The {$chartName} style must contain only ".implode(', ', $sections).' arrays.'
                );
            }

            foreach ($values as $key => $value) {
                if (! is_string($key) || ! in_array($key, $allowed[$section], true)) {
                    throw new InvalidArgumentException("The {$chartName} style option '{$section}.{$key}' is not supported.");
                }
            }

            $normalized[$section] = match ($section) {
                'line' => self::line($values, $chartName),
                'area' => self::area($values, $chartName),
                'bar' => self::bar($values, $chartName),
                'segment' => self::segment($values, $chartName),
                'points' => self::points($values, $chartName),
                'grid' => self::grid($values, $chartName),
                'axis' => self::axis($values, $chartName),
            };
        }

        return $normalized;
    }

    private static function line(array $style, string $chartName): array
    {
        $normalized = [];
        if (array_key_exists('color', $style)) {
            $normalized['color'] = self::color($style['color'], $chartName, 'line.color');
        }
        if (array_key_exists('width', $style)) {
            $normalized['width'] = self::positiveNumber($style['width'], $chartName, 'line.width', 16.0);
        }
        if (array_key_exists('interpolation', $style)) {
            if (! is_string($style['interpolation']) || ! in_array($style['interpolation'], ['linear', 'smooth'], true)) {
                throw new InvalidArgumentException("The {$chartName} style line.interpolation must be linear or smooth.");
            }

            $normalized['interpolation'] = $style['interpolation'];
        }

        return $normalized;
    }

    private static function area(array $style, string $chartName): array
    {
        $normalized = [];
        if (array_key_exists('opacity', $style)) {
            if ((! is_int($style['opacity']) && ! is_float($style['opacity'])) || ! is_finite((float) $style['opacity']) || $style['opacity'] < 0 || $style['opacity'] > 1) {
                throw new InvalidArgumentException("The {$chartName} style area.opacity must be between 0 and 1.");
            }

            $normalized['opacity'] = (float) $style['opacity'];
        }
        if (array_key_exists('gradient', $style)) {
            $normalized['gradient'] = self::boolean($style['gradient'], $chartName, 'style area.gradient');
        }

        return $normalized;
    }

    private static function bar(array $style, string $chartName): array
    {
        $normalized = [];
        $radius = $style['radius'] ?? $style['corner_radius'] ?? $style['cornerRadius'] ?? null;
        if ($radius !== null) {
            if ((! is_int($radius) && ! is_float($radius)) || ! is_finite((float) $radius) || $radius < 0 || $radius > 32) {
                throw new InvalidArgumentException("The {$chartName} style bar.radius must be between 0 and 32.");
            }

            $normalized['radius'] = (float) $radius;
        }
        if (array_key_exists('width', $style)) {
            $normalized['width'] = self::positiveNumber($style['width'], $chartName, 'bar.width', 128.0);
        }

        return $normalized;
    }

    private static function segment(array $style, string $chartName): array
    {
        $normalized = [];
        if (array_key_exists('gap', $style)) {
            $normalized['gap'] = self::numberInRange($style['gap'], $chartName, 'segment.gap', 0, 12);
        }

        $cornerRadius = $style['corner_radius'] ?? $style['cornerRadius'] ?? null;
        if ($cornerRadius !== null) {
            $normalized['corner_radius'] = self::numberInRange(
                $cornerRadius,
                $chartName,
                'segment.cornerRadius',
                0,
                20,
            );
        }

        if (array_key_exists('opacity', $style)) {
            $normalized['opacity'] = self::numberInRange($style['opacity'], $chartName, 'segment.opacity', 0, 1);
        }

        return $normalized;
    }

    private static function points(array $style, string $chartName): array
    {
        $normalized = [];
        if (array_key_exists('visible', $style)) {
            $normalized['visible'] = self::boolean($style['visible'], $chartName, 'style points.visible');
        }
        if (array_key_exists('color', $style)) {
            $normalized['color'] = self::color($style['color'], $chartName, 'points.color');
        }
        if (array_key_exists('size', $style)) {
            $normalized['size'] = self::positiveNumber($style['size'], $chartName, 'points.size', 24.0);
        }

        return $normalized;
    }

    private static function grid(array $style, string $chartName): array
    {
        $normalized = [];
        if (array_key_exists('visible', $style)) {
            $normalized['visible'] = self::boolean($style['visible'], $chartName, 'style grid.visible');
        }
        if (array_key_exists('color', $style)) {
            $normalized['color'] = self::color($style['color'], $chartName, 'grid.color');
        }
        if (array_key_exists('width', $style)) {
            $normalized['width'] = self::positiveNumber($style['width'], $chartName, 'grid.width', 8.0);
        }

        return $normalized;
    }

    private static function axis(array $style, string $chartName): array
    {
        $normalized = [];
        if (array_key_exists('visible', $style)) {
            $normalized['visible'] = self::boolean($style['visible'], $chartName, 'style axis.visible');
        }
        if (array_key_exists('color', $style)) {
            $normalized['color'] = self::color($style['color'], $chartName, 'axis.color');
        }
        $labelColor = $style['label_color'] ?? $style['labelColor'] ?? null;
        if ($labelColor !== null) {
            $normalized['label_color'] = self::color($labelColor, $chartName, 'axis.labelColor');
        }
        if (array_key_exists('font', $style)) {
            if (! is_string($style['font']) || trim($style['font']) === '') {
                throw new InvalidArgumentException("The {$chartName} axis font must be a non-empty string.");
            }

            $normalized['font'] = trim($style['font']);
        }
        $fontSize = $style['font_size'] ?? $style['fontSize'] ?? null;
        if ($fontSize !== null) {
            $normalized['font_size'] = self::positiveNumber($fontSize, $chartName, 'axis.fontSize', 32.0);
        }
        $labelCount = $style['label_count'] ?? $style['labelCount'] ?? null;
        if ($labelCount !== null) {
            if (! is_int($labelCount) || $labelCount < 2 || $labelCount > 12) {
                throw new InvalidArgumentException("The {$chartName} style axis.labelCount must be between 2 and 12.");
            }

            $normalized['label_count'] = $labelCount;
        }

        return $normalized;
    }

    private static function boolean(mixed $value, string $chartName, string $property): bool
    {
        return match ($value) {
            true, 1, '1', 'true' => true,
            false, 0, '0', 'false' => false,
            default => throw new InvalidArgumentException("The {$chartName} {$property} must be a boolean."),
        };
    }

    private static function color(mixed $value, string $chartName, string $property): string
    {
        return ColorNormalizer::normalize($value, $chartName, "style {$property}");
    }

    private static function positiveNumber(mixed $value, string $chartName, string $property, float $maximum): float
    {
        if ((! is_int($value) && ! is_float($value)) || ! is_finite((float) $value) || $value <= 0 || $value > $maximum) {
            throw new InvalidArgumentException(
                "The {$chartName} style {$property} must be greater than zero and no more than {$maximum}."
            );
        }

        return (float) $value;
    }

    private static function numberInRange(
        mixed $value,
        string $chartName,
        string $property,
        float $minimum,
        float $maximum,
    ): float {
        if (
            (! is_int($value) && ! is_float($value))
            || ! is_finite((float) $value)
            || $value < $minimum
            || $value > $maximum
        ) {
            throw new InvalidArgumentException(
                "The {$chartName} style {$property} must be between {$minimum} and {$maximum}."
            );
        }

        return (float) $value;
    }
}
