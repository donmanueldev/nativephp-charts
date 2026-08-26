<?php

namespace Donmanueldev\NativephpCharts\Elements;

use InvalidArgumentException;
use JsonException;
use Native\Mobile\Edge\CallbackRegistry;
use Native\Mobile\Edge\Element;

class LineChart extends Element
{
    protected string $type = 'line_chart';

    /** @var array<string, bool|int|string> */
    protected array $chartProps = [
        'show_grid' => true,
        'show_points' => true,
        'begin_at_zero' => true,
        'animated' => true,
        'empty_label' => 'No data',
        'a11y_label' => 'Chart',
        'locale' => '',
        'value_format' => 'number',
        'currency_code' => '',
        'minimum_fraction_digits' => -1,
        'maximum_fraction_digits' => -1,
    ];

    /** @var array<string, array<string, bool|float|int|string>> */
    protected array $style = [];

    /** @var list<array{id: string, name: string, color: string, points: list<array{label: string, value: int|float}>}> */
    protected array $series = [];

    public static function make(): static
    {
        return new static;
    }

    /** @param array<string, mixed> $attrs */
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
        $this->applyStringAttribute($attrs, 'locale', 'locale');
        $this->applyStringAttribute($attrs, 'value-format', 'valueFormat');
        $this->applyStringAttribute($attrs, 'valueFormat', 'valueFormat');
        $this->applyStringAttribute($attrs, 'currency-code', 'currencyCode');
        $this->applyStringAttribute($attrs, 'currencyCode', 'currencyCode');
        $this->applyIntegerAttribute($attrs, 'minimum-fraction-digits', 'minimumFractionDigits');
        $this->applyIntegerAttribute($attrs, 'minimumFractionDigits', 'minimumFractionDigits');
        $this->applyIntegerAttribute($attrs, 'maximum-fraction-digits', 'maximumFractionDigits');
        $this->applyIntegerAttribute($attrs, 'maximumFractionDigits', 'maximumFractionDigits');

        if (array_key_exists('style', $attrs)) {
            if (! is_array($attrs['style'])) {
                throw new InvalidArgumentException('The line chart style must be an array.');
            }

            $this->style($attrs['style']);
        }
    }

    /** @param array<int, mixed> $series */
    public function series(array $series): static
    {
        $this->series = $this->normalizeSeries($series);

        return $this;
    }

    /** @param array<string, mixed> $style */
    public function style(array $style): static
    {
        $this->style = $this->normalizeStyle($style);

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
        $this->chartProps['empty_label'] = $this->requiredText($emptyLabel, 'empty label');

        return $this;
    }

    public function a11yLabel(string $a11yLabel): static
    {
        $this->chartProps['a11y_label'] = $this->requiredText($a11yLabel, 'accessibility label');

        return $this;
    }

    public function locale(string $locale): static
    {
        $locale = str_replace('_', '-', trim($locale));

        if ($locale !== '' && preg_match('/^[a-zA-Z]{2,3}(?:-[a-zA-Z0-9]{2,8})*$/', $locale) !== 1) {
            throw new InvalidArgumentException('The line chart locale must be a valid BCP-47 locale tag.');
        }

        $this->chartProps['locale'] = $locale;

        return $this;
    }

    public function valueFormat(string $valueFormat): static
    {
        if (! in_array($valueFormat, ['number', 'currency', 'percent'], true)) {
            throw new InvalidArgumentException('The line chart value format must be number, currency, or percent.');
        }

        $this->chartProps['value_format'] = $valueFormat;

        return $this;
    }

    public function currencyCode(string $currencyCode): static
    {
        $currencyCode = strtoupper(trim($currencyCode));

        if ($currencyCode === '') {
            $this->chartProps['currency_code'] = '';

            return $this;
        }

        if (preg_match('/^[A-Z]{3}$/', $currencyCode) !== 1) {
            throw new InvalidArgumentException('The line chart currency code must be a three-letter ISO 4217 code.');
        }

        $this->chartProps['currency_code'] = $currencyCode;

        return $this;
    }

    public function minimumFractionDigits(int $digits): static
    {
        $this->chartProps['minimum_fraction_digits'] = $this->normalizeFractionDigits($digits, 'minimum fraction digits');

        return $this;
    }

    public function maximumFractionDigits(int $digits): static
    {
        $this->chartProps['maximum_fraction_digits'] = $this->normalizeFractionDigits($digits, 'maximum fraction digits');

        return $this;
    }

    protected function resolveProps(CallbackRegistry $registry): array
    {
        if ($this->chartProps['value_format'] === 'currency' && $this->chartProps['currency_code'] === '') {
            throw new InvalidArgumentException('The line chart currency code is required when value format is currency.');
        }

        if (
            $this->chartProps['minimum_fraction_digits'] !== -1
            && $this->chartProps['maximum_fraction_digits'] !== -1
            && $this->chartProps['minimum_fraction_digits'] > $this->chartProps['maximum_fraction_digits']
        ) {
            throw new InvalidArgumentException('The line chart minimum fraction digits cannot exceed maximum fraction digits.');
        }

        try {
            return [
                ...$this->chartProps,
                'style_json' => json_encode($this->style === [] ? (object) [] : $this->style, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                'series_json' => json_encode($this->series, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            ];
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('The line chart configuration could not be encoded safely.', 0, $exception);
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
            throw new InvalidArgumentException('The line chart supports at most one series.');
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
        $color = $this->normalizeColor($this->requiredString($series, 'color', "series '{$id}'"), "series '{$id}'");

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

    /**
     * @param  array<string, mixed>  $style
     * @return array<string, array<string, bool|float|int|string>>
     */
    private function normalizeStyle(array $style): array
    {
        $allowed = [
            'line' => ['color', 'width', 'interpolation'],
            'points' => ['visible', 'color', 'size'],
            'grid' => ['visible', 'color', 'width'],
            'axis' => ['visible', 'color', 'label_color', 'labelColor', 'font', 'font_size', 'fontSize', 'label_count', 'labelCount'],
        ];
        $normalized = [];

        foreach ($style as $section => $values) {
            if (! is_string($section) || ! array_key_exists($section, $allowed) || ! is_array($values)) {
                throw new InvalidArgumentException('The line chart style must contain line, points, grid, or axis arrays.');
            }

            foreach ($values as $key => $value) {
                if (! is_string($key) || ! in_array($key, $allowed[$section], true)) {
                    throw new InvalidArgumentException("The line chart style option '{$section}.{$key}' is not supported.");
                }
            }

            $normalized[$section] = match ($section) {
                'line' => $this->normalizeLineStyle($values),
                'points' => $this->normalizePointStyle($values),
                'grid' => $this->normalizeGridStyle($values),
                'axis' => $this->normalizeAxisStyle($values),
            };
        }

        return $normalized;
    }

    /** @param array<string, mixed> $style */
    private function normalizeLineStyle(array $style): array
    {
        $normalized = [];

        if (array_key_exists('color', $style)) {
            $normalized['color'] = $this->normalizeStyleColor($style['color'], 'line.color');
        }
        if (array_key_exists('width', $style)) {
            $normalized['width'] = $this->normalizePositiveNumber($style['width'], 'line.width', 16.0);
        }
        if (array_key_exists('interpolation', $style)) {
            if (! is_string($style['interpolation']) || ! in_array($style['interpolation'], ['linear', 'smooth'], true)) {
                throw new InvalidArgumentException('The line chart style line.interpolation must be linear or smooth.');
            }

            $normalized['interpolation'] = $style['interpolation'];
        }

        return $normalized;
    }

    /** @param array<string, mixed> $style */
    private function normalizePointStyle(array $style): array
    {
        $normalized = [];

        if (array_key_exists('visible', $style)) {
            $normalized['visible'] = $this->normalizeBoolean($style['visible'], 'style points.visible');
        }
        if (array_key_exists('color', $style)) {
            $normalized['color'] = $this->normalizeStyleColor($style['color'], 'points.color');
        }
        if (array_key_exists('size', $style)) {
            $normalized['size'] = $this->normalizePositiveNumber($style['size'], 'points.size', 24.0);
        }

        return $normalized;
    }

    /** @param array<string, mixed> $style */
    private function normalizeGridStyle(array $style): array
    {
        $normalized = [];

        if (array_key_exists('visible', $style)) {
            $normalized['visible'] = $this->normalizeBoolean($style['visible'], 'style grid.visible');
        }
        if (array_key_exists('color', $style)) {
            $normalized['color'] = $this->normalizeStyleColor($style['color'], 'grid.color');
        }
        if (array_key_exists('width', $style)) {
            $normalized['width'] = $this->normalizePositiveNumber($style['width'], 'grid.width', 8.0);
        }

        return $normalized;
    }

    /** @param array<string, mixed> $style */
    private function normalizeAxisStyle(array $style): array
    {
        $normalized = [];

        if (array_key_exists('visible', $style)) {
            $normalized['visible'] = $this->normalizeBoolean($style['visible'], 'style axis.visible');
        }
        if (array_key_exists('color', $style)) {
            $normalized['color'] = $this->normalizeStyleColor($style['color'], 'axis.color');
        }
        $labelColor = $style['label_color'] ?? $style['labelColor'] ?? null;
        if ($labelColor !== null) {
            $normalized['label_color'] = $this->normalizeStyleColor($labelColor, 'axis.labelColor');
        }
        if (array_key_exists('font', $style)) {
            $normalized['font'] = $this->requiredText($style['font'], 'axis font');
        }
        $fontSize = $style['font_size'] ?? $style['fontSize'] ?? null;
        if ($fontSize !== null) {
            $normalized['font_size'] = $this->normalizePositiveNumber($fontSize, 'axis.fontSize', 32.0);
        }
        $labelCount = $style['label_count'] ?? $style['labelCount'] ?? null;
        if ($labelCount !== null) {
            if (! is_int($labelCount) || $labelCount < 2 || $labelCount > 8) {
                throw new InvalidArgumentException('The line chart style axis.labelCount must be an integer between 2 and 8.');
            }

            $normalized['label_count'] = $labelCount;
        }

        return $normalized;
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

    /** @param array<string, mixed> $attrs */
    private function applyIntegerAttribute(array $attrs, string $attribute, string $method): void
    {
        if (! array_key_exists($attribute, $attrs)) {
            return;
        }

        if (! is_int($attrs[$attribute])) {
            throw new InvalidArgumentException("The line chart {$attribute} attribute must be an integer.");
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

    private function normalizeStyleColor(mixed $color, string $property): string
    {
        if (! is_string($color)) {
            throw new InvalidArgumentException("The line chart style {$property} must be a color string.");
        }

        return $this->normalizeColor($color, "style {$property}");
    }

    private function normalizeColor(string $color, string $context): string
    {
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

        throw new InvalidArgumentException("The line chart color for {$context} must be a CSS hex color, black, white, or transparent.");
    }

    private function normalizePositiveNumber(mixed $value, string $property, float $maximum): float
    {
        if ((! is_int($value) && ! is_float($value)) || ! is_finite((float) $value) || $value <= 0 || $value > $maximum) {
            throw new InvalidArgumentException("The line chart style {$property} must be a number greater than zero and no more than {$maximum}.");
        }

        return (float) $value;
    }

    private function normalizeFractionDigits(int $digits, string $property): int
    {
        if ($digits < 0 || $digits > 8) {
            throw new InvalidArgumentException("The line chart {$property} must be between 0 and 8.");
        }

        return $digits;
    }

    /** @param array<string, mixed> $values */
    private function requiredString(array $values, string $key, string $context): string
    {
        if (! array_key_exists($key, $values) || ! is_string($values[$key])) {
            throw new InvalidArgumentException("The line chart {$key} for {$context} must be a non-empty string.");
        }

        return $this->requiredText($values[$key], "{$key} for {$context}");
    }

    private function requiredText(mixed $value, string $context): string
    {
        if (! is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException("The line chart {$context} must be a non-empty string.");
        }

        return trim($value);
    }
}
