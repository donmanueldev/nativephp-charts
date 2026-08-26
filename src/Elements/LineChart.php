<?php

namespace Donmanueldev\NativephpCharts\Elements;

use InvalidArgumentException;
use JsonException;
use Native\Mobile\Edge\CallbackRegistry;
use Native\Mobile\Edge\Element;

class LineChart extends Element
{
    protected string $type = 'line_chart';

    /** @var array<string, bool|string> */
    protected array $chartProps = [
        'show_grid' => true,
        'show_points' => true,
        'begin_at_zero' => true,
        'animated' => true,
        'empty_label' => 'No data',
        'a11y_label' => 'Chart',
    ];

    /** @var list<array{id: string, name: string, color: string, points: list<array{label: string, value: int|float}>}> */
    protected array $series = [];

    public static function make(): static
    {
        return new static;
    }

    public function applyAttributes(array $attrs): void
    {
        if (array_key_exists('series', $attrs)) {
            if (! is_array($attrs['series'])) {
                throw new InvalidArgumentException('The line chart series must be an array.');
            }

            $this->series($attrs['series']);
        }

        $this->applyBooleanAttribute($attrs, 'show-grid', 'showGrid');
        $this->applyBooleanAttribute($attrs, 'showGrid', 'showGrid');
        $this->applyBooleanAttribute($attrs, 'show-points', 'showPoints');
        $this->applyBooleanAttribute($attrs, 'showPoints', 'showPoints');
        $this->applyBooleanAttribute($attrs, 'begin-at-zero', 'beginAtZero');
        $this->applyBooleanAttribute($attrs, 'beginAtZero', 'beginAtZero');
        $this->applyBooleanAttribute($attrs, 'animated', 'animated');

        $this->applyStringAttribute($attrs, 'empty-label', 'emptyLabel');
        $this->applyStringAttribute($attrs, 'emptyLabel', 'emptyLabel');
        $this->applyStringAttribute($attrs, 'a11y-label', 'a11yLabel');
        $this->applyStringAttribute($attrs, 'a11yLabel', 'a11yLabel');
    }

    /** @param array<int, mixed> $series */
    public function series(array $series): static
    {
        $this->series = $this->normalizeSeries($series);

        return $this;
    }

    public function showGrid(bool $showGrid): static
    {
        $this->chartProps['show_grid'] = $showGrid;

        return $this;
    }

    public function showPoints(bool $showPoints): static
    {
        $this->chartProps['show_points'] = $showPoints;

        return $this;
    }

    public function beginAtZero(bool $beginAtZero): static
    {
        $this->chartProps['begin_at_zero'] = $beginAtZero;

        return $this;
    }

    public function animated(bool $animated): static
    {
        $this->chartProps['animated'] = $animated;

        return $this;
    }

    public function emptyLabel(string $emptyLabel): static
    {
        $this->chartProps['empty_label'] = $emptyLabel;

        return $this;
    }

    public function a11yLabel(string $a11yLabel): static
    {
        $this->chartProps['a11y_label'] = $a11yLabel;

        return $this;
    }

    protected function resolveProps(CallbackRegistry $registry): array
    {
        try {
            return [
                ...$this->chartProps,
                'series_json' => json_encode($this->series, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            ];
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('The line chart series could not be encoded safely.', 0, $exception);
        }
    }

    /**
     * @param  array<int, mixed>  $series
     * @return list<array{id: string, name: string, color: string, points: list<array{label: string, value: int|float}>}>
     */
    private function normalizeSeries(array $series): array
    {
        if (! array_is_list($series)) {
            throw new InvalidArgumentException('The line chart series must be an ordered list.');
        }

        if (count($series) > 1) {
            throw new InvalidArgumentException('The line chart supports at most one series in version 0.1.0.');
        }

        return array_map(
            fn (mixed $item, int $index): array => $this->normalizeSeriesItem($item, $index),
            $series,
            array_keys($series),
        );
    }

    /**
     * @return array{id: string, name: string, color: string, points: list<array{label: string, value: int|float}>}
     */
    private function normalizeSeriesItem(mixed $series, int $index): array
    {
        if (! is_array($series)) {
            throw new InvalidArgumentException("The line chart series at index {$index} must be an array.");
        }

        $id = $this->requiredString($series, 'id', "series at index {$index}");
        $name = $this->requiredString($series, 'name', "series '{$id}'");
        $color = $this->requiredString($series, 'color', "series '{$id}'");

        if (! $this->isSupportedColor($color)) {
            throw new InvalidArgumentException("The line chart color for series '{$id}' is not supported.");
        }

        if (! array_key_exists('points', $series) || ! is_array($series['points'])) {
            throw new InvalidArgumentException("The line chart points for series '{$id}' must be an array.");
        }

        if (! array_is_list($series['points'])) {
            throw new InvalidArgumentException("The line chart points for series '{$id}' must be an ordered list.");
        }

        return [
            'id' => $id,
            'name' => $name,
            'color' => $color,
            'points' => array_map(
                fn (mixed $point, int $pointIndex): array => $this->normalizePoint($point, $id, $pointIndex),
                $series['points'],
                array_keys($series['points']),
            ),
        ];
    }

    /** @return array{label: string, value: int|float} */
    private function normalizePoint(mixed $point, string $seriesId, int $index): array
    {
        if (! is_array($point)) {
            throw new InvalidArgumentException("The line chart point at index {$index} for series '{$seriesId}' must be an array.");
        }

        $label = $this->requiredString($point, 'label', "point at index {$index} for series '{$seriesId}'");

        if (! array_key_exists('value', $point) || ! is_int($point['value']) && ! is_float($point['value'])) {
            throw new InvalidArgumentException("The line chart value at index {$index} for series '{$seriesId}' must be an integer or float.");
        }

        if (! is_finite((float) $point['value'])) {
            throw new InvalidArgumentException("The line chart value at index {$index} for series '{$seriesId}' must be finite.");
        }

        return ['label' => $label, 'value' => $point['value']];
    }

    /** @param array<string, mixed> $attrs */
    private function applyBooleanAttribute(array $attrs, string $attribute, string $method): void
    {
        if (array_key_exists($attribute, $attrs)) {
            $this->{$method}($this->normalizeBoolean($attrs[$attribute], $attribute));
        }
    }

    /** @param array<string, mixed> $attrs */
    private function applyStringAttribute(array $attrs, string $attribute, string $method): void
    {
        if (! array_key_exists($attribute, $attrs)) {
            return;
        }

        if (! is_string($attrs[$attribute])) {
            throw new InvalidArgumentException("The line chart {$attribute} attribute must be a string.");
        }

        $this->{$method}($attrs[$attribute]);
    }

    private function normalizeBoolean(mixed $value, string $attribute): bool
    {
        return match ($value) {
            true, 1, '1', 'true' => true,
            false, 0, '0', 'false' => false,
            default => throw new InvalidArgumentException("The line chart {$attribute} attribute must be a boolean."),
        };
    }

    /** @param array<string, mixed> $values */
    private function requiredString(array $values, string $key, string $context): string
    {
        if (! array_key_exists($key, $values) || ! is_string($values[$key]) || trim($values[$key]) === '') {
            throw new InvalidArgumentException("The line chart {$key} for {$context} must be a non-empty string.");
        }

        return trim($values[$key]);
    }

    private function isSupportedColor(string $color): bool
    {
        return preg_match('/^(?:#[\dA-Fa-f]{3}|#[\dA-Fa-f]{6}(?:[\dA-Fa-f]{2})?|(?:black|white|transparent)|[a-z]+-\d{1,3})(?:\/\d{1,3})?$/', $color) === 1;
    }
}
