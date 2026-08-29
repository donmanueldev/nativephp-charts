<?php

namespace Donmanueldev\NativephpCharts\Elements;

use Donmanueldev\NativephpCharts\Support\AxisNormalizer;
use Donmanueldev\NativephpCharts\Support\ChartDataNormalizer;
use Donmanueldev\NativephpCharts\Support\WireEncoder;
use Native\Mobile\Edge\CallbackRegistry;

abstract class CartesianChart extends ChartElement
{
    /** @var array<string, bool> */
    protected array $cartesianProps = [
        'show_grid' => true,
        'show_points' => true,
        'begin_at_zero' => true,
    ];

    /** @var array<string, bool|int|string> */
    protected array $xAxis = ['type' => 'category', 'date_format' => 'medium', 'timezone' => ''];

    /** @var array<string, bool|int|string> */
    protected array $yAxis = [
        'value_format' => 'number',
        'currency_code' => '',
        'minimum_fraction_digits' => -1,
        'maximum_fraction_digits' => -1,
    ];

    /** @var array<int, mixed> */
    protected array $rawSeries = [];

    /** @var list<array{id: string, name: string, color: string, points: list<array{id: string, label: string, value: int|float, x: int|float|string}>}> */
    protected array $series = [];

    protected bool $xAxisWasConfigured = false;

    /** @param array<string, mixed> $attrs */
    public function applyAttributes(array $attrs): void
    {
        $hasXAxis = array_key_exists('x-axis', $attrs) || array_key_exists('xAxis', $attrs);
        $hasSeries = array_key_exists('series', $attrs);

        if ($hasSeries && $hasXAxis) {
            $this->rawSeries = [];
            $this->series = [];
            $this->applyArrayAttributes($attrs, ['x-axis', 'xAxis'], 'xAxis');
        }

        if ($hasSeries) {
            $this->arrayAttribute($attrs['series'], 'series', fn (array $value) => $this->series($value));
        }

        if (! $hasSeries || ! $hasXAxis) {
            $this->applyArrayAttributes($attrs, ['x-axis', 'xAxis'], 'xAxis');
        }

        $this->applyBooleanAttributes($attrs, ['show-grid', 'showGrid'], 'showGrid');
        $this->applyBooleanAttributes($attrs, ['show-points', 'showPoints'], 'showPoints');
        $this->applyBooleanAttributes($attrs, ['begin-at-zero', 'beginAtZero'], 'beginAtZero');
        $this->applyCommonAttributes($attrs);
        $this->applyArrayAttributes($attrs, ['y-axis', 'yAxis'], 'yAxis');
        $this->applyChartAttributes($attrs);
    }

    /** @param array<string, mixed> $attrs */
    protected function applyChartAttributes(array $attrs): void {}

    /** @param array<int, mixed> $series */
    public function series(array $series): static
    {
        $this->rawSeries = $series;
        $this->series = ChartDataNormalizer::normalize(
            $series,
            $this->xAxis['type'],
            $this->chartName(),
            allowDeferredTypedX: ! $this->xAxisWasConfigured,
        );

        return $this;
    }

    /** @param array<string, mixed> $axis */
    public function xAxis(array $axis): static
    {
        $this->xAxis = AxisNormalizer::x($axis, $this->chartName(), $this->xAxis);
        $this->xAxisWasConfigured = true;
        $this->series = ChartDataNormalizer::normalize($this->rawSeries, $this->xAxis['type'], $this->chartName());

        return $this;
    }

    /** @param array<string, mixed> $axis */
    public function yAxis(array $axis): static
    {
        $this->yAxis = AxisNormalizer::y($axis, $this->chartName(), $this->yAxis, validateComplete: false);
        $this->syncFormattingProps($this->yAxis);
        if (array_key_exists('begin_at_zero', $this->yAxis)) {
            $this->cartesianProps['begin_at_zero'] = $this->yAxis['begin_at_zero'];
        }

        return $this;
    }

    public function showGrid(bool $showGrid): static
    {
        $this->cartesianProps['show_grid'] = $showGrid;

        return $this;
    }

    public function showPoints(bool $showPoints): static
    {
        $this->cartesianProps['show_points'] = $showPoints;

        return $this;
    }

    public function beginAtZero(bool $beginAtZero): static
    {
        $this->cartesianProps['begin_at_zero'] = $beginAtZero;
        $this->yAxis['begin_at_zero'] = $beginAtZero;

        return $this;
    }

    public function valueFormat(string $valueFormat): static
    {
        parent::valueFormat($valueFormat);
        $this->yAxis['value_format'] = $valueFormat;

        return $this;
    }

    public function currencyCode(string $currencyCode): static
    {
        parent::currencyCode($currencyCode);
        $this->yAxis['currency_code'] = $this->chartProps['currency_code'];

        return $this;
    }

    public function minimumFractionDigits(int $digits): static
    {
        parent::minimumFractionDigits($digits);
        $this->yAxis['minimum_fraction_digits'] = $digits;

        return $this;
    }

    public function maximumFractionDigits(int $digits): static
    {
        parent::maximumFractionDigits($digits);
        $this->yAxis['maximum_fraction_digits'] = $digits;

        return $this;
    }

    protected function resolveProps(CallbackRegistry $registry): array
    {
        $this->series = ChartDataNormalizer::normalize($this->rawSeries, $this->xAxis['type'], $this->chartName());
        $yAxis = AxisNormalizer::y($this->yAxis, $this->chartName());

        return [
            ...$this->cartesianProps,
            ...$this->resolveCommonProps($registry),
            'series_json' => WireEncoder::encode($this->series, $this->chartName()),
            'x_axis_json' => WireEncoder::encode($this->xAxis, $this->chartName()),
            'y_axis_json' => WireEncoder::encode($yAxis, $this->chartName()),
            ...$this->specificProps(),
        ];
    }

    /** @return array<string, bool|float|int|string> */
    protected function specificProps(): array
    {
        return [];
    }

    protected function legendItemCount(): int
    {
        return count($this->series);
    }
}
