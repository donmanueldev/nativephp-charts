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
     * Parentheses inside string literals are treated as text.
     *
     * @param  string  $context  Callback name used in error messages, e.g. `selection callback`.
     *
     * @throws InvalidArgumentException When the method name, parentheses, or arguments are invalid.
     */
    public static function normalize(string $expression, string $chartName, string $context = 'selection callback'): string
    {
        $expression = trim($expression);
        if ($expression === '') {
            throw new InvalidArgumentException("The {$chartName} {$context} must be a non-empty expression.");
        }

        if (! str_contains($expression, '(')) {
            self::validateMethod($expression, $chartName, $context);

            return $expression;
        }

        $open = strpos($expression, '(');
        if ($open === false || ! str_ends_with($expression, ')') || ! self::hasSingleArgumentList($expression)) {
            throw new InvalidArgumentException("The {$chartName} {$context} expression is malformed.");
        }

        $method = trim(substr($expression, 0, $open));
        self::validateMethod($method, $chartName, $context);
        $arguments = trim(substr($expression, $open + 1, -1));
        if ($arguments !== '') {
            try {
                $decoded = json_decode('['.$arguments.']', true, flags: JSON_THROW_ON_ERROR);
                $arguments = substr(json_encode($decoded, JSON_HEX_APOS | JSON_THROW_ON_ERROR), 1, -1);
            } catch (JsonException) {
                try {
                    $decoded = json_decode('['.str_replace("'", '"', $arguments).']', true, flags: JSON_THROW_ON_ERROR);
                } catch (JsonException) {
                    throw new InvalidArgumentException(
                        "The {$chartName} {$context} arguments must be JSON-compatible literals."
                    );
                }
            }

            foreach ($decoded as $argument) {
                if (is_array($argument) || is_object($argument)) {
                    throw new InvalidArgumentException(
                        "The {$chartName} {$context} arguments must be scalar literals."
                    );
                }

                if (is_float($argument) && ! is_finite($argument)) {
                    throw new InvalidArgumentException(
                        "The {$chartName} {$context} arguments must be finite numbers."
                    );
                }
            }
        }

        return $method.'('.$arguments.')';
    }

    /**
     * Confirm the expression has exactly one `(` and one `)` outside string literals,
     * with the closing parenthesis ending the expression.
     */
    private static function hasSingleArgumentList(string $expression): bool
    {
        $opened = 0;
        $closed = 0;
        $quote = null;
        $length = strlen($expression);

        for ($index = 0; $index < $length; $index++) {
            $character = $expression[$index];

            if ($quote !== null) {
                if ($character === '\\') {
                    $index++;
                } elseif ($character === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($character === '"' || $character === "'") {
                $quote = $character;
            } elseif ($character === '(') {
                $opened++;
            } elseif ($character === ')') {
                $closed++;
                if ($opened !== 1 || $index !== $length - 1) {
                    return false;
                }
            }
        }

        return $quote === null && $opened === 1 && $closed === 1;
    }

    /** Ensure the dispatch target is a plain component method name. */
    private static function validateMethod(string $method, string $chartName, string $context): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', trim($method)) !== 1) {
            throw new InvalidArgumentException("The {$chartName} {$context} method name is invalid.");
        }
    }
}
