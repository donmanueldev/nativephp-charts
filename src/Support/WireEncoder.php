<?php

namespace Donmanueldev\NativephpCharts\Support;

use InvalidArgumentException;
use JsonException;

/**
 * Encodes normalized PHP configuration for deterministic native consumption.
 *
 * Slashes and Unicode remain readable in the payload. Encoding failures are exposed
 * as contract errors instead of leaking a lower-level JSON exception to consumers.
 */
final class WireEncoder
{
    /**
     * Encode a normalized array, optionally preserving an empty map as `{}`.
     *
     * The object form is required for map-shaped native options because JSON `[]`
     * would otherwise be decoded as a list on Swift and Kotlin.
     *
     * @param  array<array-key, mixed>  $value
     *
     * @throws InvalidArgumentException When a nested value cannot be represented as JSON.
     */
    public static function encode(array $value, string $chartName, bool $emptyAsObject = false): string
    {
        try {
            return json_encode(
                $emptyAsObject && $value === [] ? (object) [] : $value,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            );
        } catch (JsonException $exception) {
            throw new InvalidArgumentException(
                "The {$chartName} configuration could not be encoded safely.",
                0,
                $exception,
            );
        }
    }
}
