<?php

namespace Donmanueldev\NativephpCharts\Support;

use InvalidArgumentException;

/**
 * Converts the public CSS color grammar into the canonical native wire format.
 *
 * Native renderers receive uppercase `#RRGGBB` or `#AARRGGBB`. CSS-alpha input is
 * deliberately reordered from `#RRGGBBAA` so both platforms consume the same value.
 */
final class ColorNormalizer
{
    /**
     * Normalize a named color, short hex, RGB hex, or CSS-alpha hex color.
     *
     * @throws InvalidArgumentException When the value is not part of the supported color grammar.
     */
    public static function normalize(mixed $color, string $chartName, string $context): string
    {
        if (! is_string($color)) {
            throw new InvalidArgumentException("The {$chartName} color for {$context} must be a color string.");
        }

        $color = strtolower(trim($color));
        $named = ['black' => '#000000', 'white' => '#FFFFFF', 'transparent' => '#00000000'];

        if (array_key_exists($color, $named)) {
            return $named[$color];
        }

        if (preg_match('/^#[\dA-Fa-f]{3}$/', $color) === 1) {
            return sprintf('#%1$s%1$s%2$s%2$s%3$s%3$s', $color[1], $color[2], $color[3]);
        }

        if (preg_match('/^#[\dA-Fa-f]{6}$/', $color) === 1) {
            return strtoupper($color);
        }

        if (preg_match('/^#[\dA-Fa-f]{8}$/', $color) === 1) {
            return sprintf('#%s%s', strtoupper(substr($color, 7, 2)), strtoupper(substr($color, 1, 6)));
        }

        throw new InvalidArgumentException(
            "The {$chartName} color for {$context} must be a CSS hex color, black, white, or transparent."
        );
    }
}
