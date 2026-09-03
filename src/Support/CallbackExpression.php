<?php

namespace Donmanueldev\NativephpCharts\Support;

use InvalidArgumentException;
use JsonException;

/**
 * Validates callback expressions before they cross the PHP-to-native wire boundary.
 *
 * The expression is preserved for NativePHP dispatch; this class does not invoke the
 * method. Only a component method name and optional scalar, JSON-compatible literals
 * are accepted so callback arguments cannot introduce nested wire data.
 */
final class CallbackExpression
{
    /**
     * Return a trimmed callback expression with a validated method and arguments.
     *
     * Both `pointSelected` and `pointSelected('sales', 1, true)` are supported.
     * Single quotes are accepted as a convenience when validating scalar literals.
     *
     * @throws InvalidArgumentException When the method name, parentheses, or arguments are invalid.
     */
    public static function normalize(string $expression, string $chartName): string
    {
        $expression = trim($expression);
        if ($expression === '') {
            throw new InvalidArgumentException("The {$chartName} selection callback must be a non-empty expression.");
        }

        if (! str_contains($expression, '(')) {
            self::validateMethod($expression, $chartName);

            return $expression;
        }

        $open = strpos($expression, '(');
        if ($open === false || ! str_ends_with($expression, ')') || substr_count($expression, '(') !== 1 || substr_count($expression, ')') !== 1) {
            throw new InvalidArgumentException("The {$chartName} selection callback expression is malformed.");
        }

        $method = trim(substr($expression, 0, $open));
        self::validateMethod($method, $chartName);
        $arguments = trim(substr($expression, $open + 1, -1));
        if ($arguments !== '') {
            try {
                $decoded = json_decode('['.str_replace("'", '"', $arguments).']', true, flags: JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                throw new InvalidArgumentException(
                    "The {$chartName} selection callback arguments must be JSON-compatible literals."
                );
            }

            foreach ($decoded as $argument) {
                if (is_array($argument) || is_object($argument)) {
                    throw new InvalidArgumentException(
                        "The {$chartName} selection callback arguments must be scalar literals."
                    );
                }
            }
        }

        return $method.'('.$arguments.')';
    }

    /** Ensure the dispatch target is a plain component method name. */
    private static function validateMethod(string $method, string $chartName): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', trim($method)) !== 1) {
            throw new InvalidArgumentException("The {$chartName} selection callback method name is invalid.");
        }
    }
}
