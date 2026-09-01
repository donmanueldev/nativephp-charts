<?php

namespace Donmanueldev\NativephpCharts;

use Donmanueldev\NativephpCharts\Support\ChartDataNormalizer;
use InvalidArgumentException;
use JsonException;

final readonly class PointSelection
{
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

    /** @return array{version: int, chart_type: string, series_id: string, series_name: string, point_id: string, point_index: int, x_type: string, x: int|float|string, label: string, value: int|float, localized_value: string} */
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
