<?php

namespace Donmanueldev\NativephpCharts\Support;

use InvalidArgumentException;
use JsonException;

final class CallbackExpression
{
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

    private static function validateMethod(string $method, string $chartName): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', trim($method)) !== 1) {
            throw new InvalidArgumentException("The {$chartName} selection callback method name is invalid.");
        }
    }
}
