<?php

namespace Donmanueldev\NativephpCharts;

use DateTimeImmutable;
use Donmanueldev\NativephpCharts\Support\ChartDataNormalizer;
use InvalidArgumentException;
use JsonException;

/**
 * Immutable PHP representation of a completed native viewport gesture.
 *
 * The renderers emit one versioned callback after pan/zoom interaction settles; this
 * decoder validates that its continuous x-axis bounds use the same canonical types as
 * chart data. Category and y-axis viewport events are not part of version 1.
 */
final readonly class ViewportChange
{
    /**
     * Create an event from values that have already passed the version 1 contract.
     * Prefer `fromJson()` for native callback data or any other untrusted input.
     *
     * @param  int  $version  Wire contract version; currently always `1`.
     * @param  string  $chartType  Cartesian chart family that emitted the event.
     * @param  string  $axis  Changed axis; version 1 supports only `x`.
     * @param  string  $reason  One of `pan`, `zoom`, or `pan_zoom`.
     * @param  string  $xType  One of `number`, `date`, or `datetime`.
     * @param  int|float|string  $minimum  Canonical inclusive lower viewport bound.
     * @param  int|float|string  $maximum  Canonical inclusive upper viewport bound.
     */
    public function __construct(
        public int $version,
        public string $chartType,
        public string $axis,
        public string $reason,
        public string $xType,
        public int|float|string $minimum,
        public int|float|string $maximum,
    ) {}

    /**
     * Decode and validate a version 1 viewport-change callback payload.
     *
     * Expected wire keys are `version`, `chart_type`, `axis`, `reason`, `x_type`,
     * `minimum`, and `maximum`. The decoded bounds always satisfy `minimum < maximum`.
     *
     * @throws InvalidArgumentException When JSON, required fields, types, or bounds are invalid.
     */
    public static function fromJson(string $json): self
    {
        try {
            $payload = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('The viewport change payload must be valid JSON.', 0, $exception);
        }

        if (! is_array($payload) || array_is_list($payload)) {
            throw new InvalidArgumentException('The viewport change payload must be a JSON object.');
        }

        $version = self::integer($payload, 'version');
        if ($version !== 1) {
            throw new InvalidArgumentException("The viewport change payload version '{$version}' is not supported.");
        }

        $chartType = self::string($payload, 'chart_type');
        if (! in_array($chartType, ['line', 'area', 'bar', 'scatter', 'candlestick'], true)) {
            throw new InvalidArgumentException("The viewport change chart type '{$chartType}' is not supported.");
        }

        $axis = self::string($payload, 'axis');
        if ($axis !== 'x') {
            throw new InvalidArgumentException("The viewport change axis '{$axis}' is not supported.");
        }

        $reason = self::string($payload, 'reason');
        if (! in_array($reason, ['pan', 'zoom', 'pan_zoom'], true)) {
            throw new InvalidArgumentException("The viewport change reason '{$reason}' is not supported.");
        }

        $xType = self::string($payload, 'x_type');
        if (! in_array($xType, ['number', 'date', 'datetime'], true)) {
            throw new InvalidArgumentException("The viewport change x type '{$xType}' is not supported.");
        }

        $minimum = self::boundary($payload, 'minimum', $xType);
        $maximum = self::boundary($payload, 'maximum', $xType);
        if (self::comparable($minimum, $xType) >= self::comparable($maximum, $xType)) {
            throw new InvalidArgumentException('The viewport change minimum must be less than maximum.');
        }

        return new self(
            version: $version,
            chartType: $chartType,
            axis: $axis,
            reason: $reason,
            xType: $xType,
            minimum: $minimum,
            maximum: $maximum,
        );
    }

    /**
     * Return the canonical snake_case event payload for logging or forwarding.
     *
     * @return array{version: int, chart_type: string, axis: string, reason: string, x_type: string, minimum: int|float|string, maximum: int|float|string}
     */
    public function toArray(): array
    {
        return [
            'version' => $this->version,
            'chart_type' => $this->chartType,
            'axis' => $this->axis,
            'reason' => $this->reason,
            'x_type' => $this->xType,
            'minimum' => $this->minimum,
            'maximum' => $this->maximum,
        ];
    }

    /** @param array<string, mixed> $payload */
    private static function boundary(array $payload, string $key, string $xType): int|float|string
    {
        if (! array_key_exists($key, $payload)) {
            throw new InvalidArgumentException("The viewport change property '{$key}' is required.");
        }

        return ChartDataNormalizer::normalizeTypedX(
            $payload[$key],
            $xType,
            'viewport change',
            $key,
        );
    }

    /** Compare canonical bounds without discarding datetime microseconds. */
    private static function comparable(int|float|string $value, string $xType): float|string
    {
        return match ($xType) {
            'number' => (float) $value,
            'date' => $value,
            'datetime' => (float) (new DateTimeImmutable($value))->format('U.u'),
        };
    }

    /** @param array<string, mixed> $payload */
    private static function string(array $payload, string $key): string
    {
        if (! array_key_exists($key, $payload) || ! is_string($payload[$key]) || trim($payload[$key]) === '') {
            throw new InvalidArgumentException("The viewport change property '{$key}' must be a non-empty string.");
        }

        return trim($payload[$key]);
    }

    /** @param array<string, mixed> $payload */
    private static function integer(array $payload, string $key): int
    {
        if (! array_key_exists($key, $payload) || ! is_int($payload[$key])) {
            throw new InvalidArgumentException("The viewport change property '{$key}' must be an integer.");
        }

        return $payload[$key];
    }
}
