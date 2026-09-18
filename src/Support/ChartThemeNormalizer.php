<?php

namespace Donmanueldev\NativephpCharts\Support;

use InvalidArgumentException;

final class ChartThemeNormalizer
{
    /** @var list<string> */
    private const COLOR_KEYS = ['background', 'foreground', 'muted', 'grid', 'track', 'error'];

    /** @return array{light: array<string, mixed>, dark: array<string, mixed>} */
    public static function resolve(string $preset, string $chartName): array
    {
        $preset = trim($preset);
        if ($preset === '') {
            throw new InvalidArgumentException("The {$chartName} preset must be a non-empty string.");
        }

        $builtIns = self::builtIns();
        $application = [];
        if (function_exists('app')) {
            $app = app();
            if (is_object($app) && method_exists($app, 'bound') && $app->bound('config')) {
                $application = config('nativephp-charts.presets', []);
            }
        }
        if (! is_array($application)) {
            throw new InvalidArgumentException('The nativephp-charts preset registry must be an array.');
        }

        if (! array_key_exists($preset, $builtIns) && ! array_key_exists($preset, $application)) {
            throw new InvalidArgumentException("The {$chartName} preset '{$preset}' is not registered.");
        }

        $base = $builtIns[$preset] ?? $builtIns['default'];
        $override = $application[$preset] ?? [];
        if (! is_array($override)) {
            throw new InvalidArgumentException("The {$chartName} preset '{$preset}' must be an array.");
        }

        $resolved = $base;
        foreach (['light', 'dark'] as $appearance) {
            if (array_key_exists($appearance, $override)) {
                if (! is_array($override[$appearance])) {
                    throw new InvalidArgumentException("The {$chartName} preset '{$preset}.{$appearance}' must be an array.");
                }
                $resolved[$appearance] = array_replace($base[$appearance], $override[$appearance]);
            }
        }

        return [
            'light' => self::appearance($resolved['light'] ?? null, $chartName, $preset, 'light'),
            'dark' => self::appearance($resolved['dark'] ?? null, $chartName, $preset, 'dark'),
        ];
    }

    /** @return array<string, array{light: array<string, mixed>, dark: array<string, mixed>}> */
    public static function builtIns(): array
    {
        return [
            'default' => [
                'light' => self::tokens('#FFFFFF', '#0F172A', '#64748B', '#E2E8F0', '#E2E8F0', '#DC2626', ['#2563EB', '#0F766E', '#7C3AED', '#EA580C', '#DB2777', '#0891B2']),
                'dark' => self::tokens('#0B1220', '#F8FAFC', '#94A3B8', '#1E293B', '#334155', '#F87171', ['#60A5FA', '#2DD4BF', '#A78BFA', '#FB923C', '#F472B6', '#22D3EE']),
            ],
            'spectrum' => [
                'light' => self::tokens('#FFFFFF', '#18181B', '#71717A', '#E4E4E7', '#E4E4E7', '#E11D48', ['#6366F1', '#06B6D4', '#10B981', '#F59E0B', '#F43F5E', '#A855F7']),
                'dark' => self::tokens('#09090B', '#FAFAFA', '#A1A1AA', '#27272A', '#3F3F46', '#FB7185', ['#818CF8', '#22D3EE', '#34D399', '#FBBF24', '#FB7185', '#C084FC']),
            ],
            'contrast' => [
                'light' => self::tokens('#FFFFFF', '#000000', '#3F3F46', '#71717A', '#D4D4D8', '#B91C1C', ['#0047AB', '#007A3D', '#6B21A8', '#B45309', '#BE123C', '#075985']),
                'dark' => self::tokens('#000000', '#FFFFFF', '#D4D4D8', '#A1A1AA', '#52525B', '#FCA5A5', ['#93C5FD', '#6EE7B7', '#D8B4FE', '#FCD34D', '#FDA4AF', '#67E8F9']),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private static function appearance(mixed $value, string $chartName, string $preset, string $appearance): array
    {
        if (! is_array($value) || array_is_list($value)) {
            throw new InvalidArgumentException("The {$chartName} preset '{$preset}.{$appearance}' must be an object-shaped array.");
        }
        foreach ($value as $key => $_) {
            if (! is_string($key) || (! in_array($key, self::COLOR_KEYS, true) && $key !== 'palette')) {
                throw new InvalidArgumentException("The {$chartName} preset option '{$appearance}.{$key}' is not supported.");
            }
        }

        $normalized = [];
        foreach (self::COLOR_KEYS as $key) {
            if (! array_key_exists($key, $value)) {
                throw new InvalidArgumentException("The {$chartName} preset '{$preset}.{$appearance}.{$key}' is required.");
            }
            $normalized[$key] = ColorNormalizer::normalize($value[$key], $chartName, "preset {$appearance}.{$key}");
        }
        $palette = $value['palette'] ?? null;
        if (! is_array($palette) || ! array_is_list($palette) || $palette === [] || count($palette) > 24) {
            throw new InvalidArgumentException("The {$chartName} preset '{$preset}.{$appearance}.palette' must be a non-empty list of at most 24 colors.");
        }
        $normalized['palette'] = array_map(
            fn (mixed $color): string => ColorNormalizer::normalize($color, $chartName, "preset {$appearance}.palette"),
            $palette,
        );

        return $normalized;
    }

    /** @param list<string> $palette */
    private static function tokens(string $background, string $foreground, string $muted, string $grid, string $track, string $error, array $palette): array
    {
        return compact('background', 'foreground', 'muted', 'grid', 'track', 'error', 'palette');
    }
}
