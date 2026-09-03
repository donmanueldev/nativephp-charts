<?php

namespace Donmanueldev\NativephpCharts;

use Donmanueldev\NativephpCharts\Support\ChartDataNormalizer;
use InvalidArgumentException;
use JsonException;

/**
 * Immutable PHP representation of the native point-selection event contract.
 *
 * `fromJson()` is the trust boundary for callback data emitted by Swift and Kotlin.
 * It currently accepts version 1 for all chart families and normalizes typed x values
 * before application code receives them. Radial selections additionally preserve the
 * segment identity mapping used by the shared event schema.
 */
final readonly class PointSelection
{
    /**
     * Create an event from values that have already passed the version 1 contract.
     * Prefer `fromJson()` for native callback data or any other untrusted input.
     *
     * @param  int  $version  Wire contract version; currently always `1`.
     * @param  string  $chartType  Canonical chart family name.
     * @param  string  $seriesId  Stable series ID, or the segment ID for radial charts.
     * @param  string  $seriesName  Display name, or the segment label for radial charts.
     * @param  string  $pointId  Stable point or segment ID.
     * @param  int  $pointIndex  Original source index, including after LTTB sampling.
     * @param  string  $xType  One of `category`, `number`, `date`, or `datetime`.
     * @param  int|float|string  $x  Canonical x value for the declared x type.
     * @param  string  $label  Human-readable point label.
     * @param  int|float  $value  Raw numeric value, independent of locale.
     * @param  string  $localizedValue  Renderer-formatted value for display.
     */
    public function __construct(
        public int $version,
        public string $chartType,
        public string $seriesId,
        public string $seriesName,
        public string $pointId,
        public int $pointIndex,
        public string $xType,
        public int|float|string $x,
        public string $label,
        public int|float $value,
        public string $localizedValue,
    ) {}

    /**
     * Decode and validate a version 1 point-selection callback payload.
     *
     * Expected wire keys are `version`, `chart_type`, `series_id`, `series_name`,
     * `point_id`, `point_index`, `x_type`, `x`, `label`, `value`, and
     * `localized_value`. Datetime x values are returned in canonical RFC 3339 form.
     *
     * @throws InvalidArgumentException When JSON, required fields, types, or chart invariants are invalid.
     */
    public static function fromJson(string $json): self
    {
        try {
            $payload = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('The point selection payload must be valid JSON.', 0, $exception);
        }

        if (! is_array($payload) || array_is_list($payload)) {
            throw new InvalidArgumentException('The point selection payload must be a JSON object.');
        }

        $version = self::integer($payload, 'version');
        if ($version !== 1) {
            throw new InvalidArgumentException("The point selection payload version '{$version}' is not supported.");
        }

        $chartType = self::string($payload, 'chart_type');
        if (! in_array($chartType, ['line', 'area', 'bar', 'scatter', 'pie', 'donut', 'radar', 'candlestick'], true)) {
            throw new InvalidArgumentException("The point selection chart type '{$chartType}' is not supported.");
        }

        $xType = self::string($payload, 'x_type');
        if (! in_array($xType, ['category', 'number', 'date', 'datetime'], true)) {
            throw new InvalidArgumentException("The point selection x type '{$xType}' is not supported.");
        }

        if (! array_key_exists('x', $payload)) {
            throw new InvalidArgumentException("The point selection property 'x' is required.");
        }

        $x = $xType === 'category'
            ? self::nonEmptyStringValue($payload['x'], 'x')
            : ChartDataNormalizer::normalizeTypedX($payload['x'], $xType, 'point selection', 'payload');

        $value = $payload['value'] ?? null;
        if ((! is_int($value) && ! is_float($value)) || ! is_finite((float) $value)) {
            throw new InvalidArgumentException("The point selection property 'value' must be a finite integer or float.");
        }

        $pointIndex = self::integer($payload, 'point_index');
        if ($pointIndex < 0) {
            throw new InvalidArgumentException("The point selection property 'point_index' must not be negative.");
        }

        $seriesId = self::string($payload, 'series_id');
        $seriesName = self::string($payload, 'series_name');
        $pointId = self::string($payload, 'point_id');
        $label = self::string($payload, 'label');
        $localizedValue = self::string($payload, 'localized_value');

        if (in_array($chartType, ['pie', 'donut'], true)) {
            if ($xType !== 'category') {
                throw new InvalidArgumentException('A radial point selection must use the category x type.');
            }

            if ($seriesId !== $pointId) {
                throw new InvalidArgumentException('A radial point selection must use the segment id as both series_id and point_id.');
            }

            if ($seriesName !== $x || $seriesName !== $label) {
                throw new InvalidArgumentException('A radial point selection must use the segment label as series_name, x, and label.');
            }
        }

        return new self(
            version: $version,
            chartType: $chartType,
            seriesId: $seriesId,
            seriesName: $seriesName,
            pointId: $pointId,
            pointIndex: $pointIndex,
            xType: $xType,
            x: $x,
            label: $label,
            value: $value,
            localizedValue: $localizedValue,
        );
    }

    /**
     * Return the canonical snake_case event payload for logging or forwarding.
     *
     * @return array{version: int, chart_type: string, series_id: string, series_name: string, point_id: string, point_index: int, x_type: string, x: int|float|string, label: string, value: int|float, localized_value: string}
     */
    public function toArray(): array
    {
        return [
            'version' => $this->version,
            'chart_type' => $this->chartType,
            'series_id' => $this->seriesId,
            'series_name' => $this->seriesName,
            'point_id' => $this->pointId,
            'point_index' => $this->pointIndex,
            'x_type' => $this->xType,
            'x' => $this->x,
            'label' => $this->label,
            'value' => $this->value,
            'localized_value' => $this->localizedValue,
        ];
    }

    /** @param array<string, mixed> $payload */
    private static function string(array $payload, string $key): string
    {
        if (! array_key_exists($key, $payload)) {
            throw new InvalidArgumentException("The point selection property '{$key}' is required.");
        }

        return self::nonEmptyStringValue($payload[$key], $key);
    }

    private static function nonEmptyStringValue(mixed $value, string $key): string
    {
        if (! is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException("The point selection property '{$key}' must be a non-empty string.");
        }

        return trim($value);
    }

    /** @param array<string, mixed> $payload */
    private static function integer(array $payload, string $key): int
    {
        if (! array_key_exists($key, $payload) || ! is_int($payload[$key])) {
            throw new InvalidArgumentException("The point selection property '{$key}' must be an integer.");
        }

        return $payload[$key];
    }
}
