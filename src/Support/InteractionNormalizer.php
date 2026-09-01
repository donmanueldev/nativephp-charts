<?php

namespace Donmanueldev\NativephpCharts\Support;

use InvalidArgumentException;

final class InteractionNormalizer
{
    /** @return array{enabled: bool, mode: string, crosshair: string, tooltip: string} */
    public static function normalize(array $interaction, string $chartName): array
    {
        self::rejectUnknownKeys($interaction, ['enabled', 'mode', 'crosshair', 'tooltip'], $chartName);

        $enabled = $interaction['enabled'] ?? true;
        if (! is_bool($enabled)) {
            throw new InvalidArgumentException("The {$chartName} interaction enabled value must be a boolean.");
        }

        $mode = $interaction['mode'] ?? 'tap';
        if (! is_string($mode) || ! in_array($mode, ['tap', 'scrub'], true)) {
            throw new InvalidArgumentException("The {$chartName} interaction mode must be tap or scrub.");
        }

        $crosshair = $interaction['crosshair'] ?? 'x';
        if (! is_string($crosshair) || ! in_array($crosshair, ['none', 'x', 'y', 'both'], true)) {
            throw new InvalidArgumentException("The {$chartName} interaction crosshair must be none, x, y, or both.");
        }

        $tooltip = $interaction['tooltip'] ?? 'single';
        if (! is_string($tooltip) || ! in_array($tooltip, ['single', 'shared'], true)) {
            throw new InvalidArgumentException("The {$chartName} interaction tooltip must be single or shared.");
        }

        return compact('enabled', 'mode', 'crosshair', 'tooltip');
    }

    private static function rejectUnknownKeys(array $interaction, array $allowed, string $chartName): void
    {
        $unknown = array_diff(array_keys($interaction), $allowed);
        if ($unknown !== []) {
            throw new InvalidArgumentException("The {$chartName} interaction contains unsupported keys: ".implode(', ', $unknown).'.');
        }
    }
}
