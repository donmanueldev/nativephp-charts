<?php

namespace Donmanueldev\NativephpCharts\Support;

use InvalidArgumentException;
use JsonException;

final class WireEncoder
{
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
