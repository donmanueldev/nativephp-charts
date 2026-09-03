<?php

namespace Donmanueldev\NativephpCharts\Elements;

use Donmanueldev\NativephpCharts\Support\AnnotationNormalizer;
use Donmanueldev\NativephpCharts\Support\AxisNormalizer;
use Donmanueldev\NativephpCharts\Support\CallbackExpression;
use Donmanueldev\NativephpCharts\Support\ChartDataNormalizer;
use Donmanueldev\NativephpCharts\Support\InteractionNormalizer;
use Donmanueldev\NativephpCharts\Support\SamplingNormalizer;
use Donmanueldev\NativephpCharts\Support\ViewportNormalizer;
use Donmanueldev\NativephpCharts\Support\WireEncoder;
use Donmanueldev\NativephpCharts\Support\WirePayloadStore;
use InvalidArgumentException;
use Native\Mobile\Edge\CallbackRegistry;

abstract class CartesianChart extends ChartElement
{
    /** @var array<string, bool> */
    protected array $cartesianProps = [
        'show_grid' => true,
        'show_points' => true,
        'begin_at_zero' => true,
    ];

    /** @var array<string, bool|float|int|string> */
    protected array $xAxis = ['type' => 'category', 'date_format' => 'medium', 'timezone' => ''];

    /** @var array<string, bool|float|int|string> */
    protected array $yAxis = [
        'value_format' => 'number',
        'currency_code' => '',
        'minimum_fraction_digits' => -1,
        'maximum_fraction_digits' => -1,
    ];

    /** @var array<int, mixed> */
    protected array $rawSeries = [];

    /** @var list<array<string, mixed>> */
    protected array $series = [];

    protected bool $xAxisWasConfigured = false;

    /** @var array<int, mixed> */
    protected array $annotations = [];

    /** @var array<int, mixed> */
    protected array $rawAnnotations = [];

    /** @var array<string, bool|string> */
    protected array $interaction = [
        'enabled' => true,
        'mode' => 'tap',
        'crosshair' => 'x',
        'tooltip' => 'single',
    ];

    /** @var array<string, mixed> */
    protected array $rawViewport = [];

    /** @var array<string, bool|float|int|string> */
    protected array $viewport = [
        'enabled' => false,
        'pan' => true,
        'zoom' => true,
    ];

    /** @var array<string, int|string> */
    protected array $sampling = [
        'mode' => 'none',
        'threshold' => 1000,
    ];

    protected ?string $viewportChangeMethod = null;

    protected bool $seriesRequiresFinalNormalization = false;

    /** @var array<string, string>|null */
    private ?array $cartesianWireSnapshot = null;

    /** @param array<string, mixed> $attrs */
    public function applyAttributes(array $attrs): void
    {
        $hasXAxis = array_key_exists('x-axis', $attrs) || array_key_exists('xAxis', $attrs);
        $hasSeries = array_key_exists('series', $attrs);

        if ($hasSeries && $hasXAxis) {
            $axisKey = array_key_exists('x-axis', $attrs) ? 'x-axis' : 'xAxis';
            $this->arrayAttribute($attrs[$axisKey], $axisKey, function (array $axis) use ($attrs): void {
                $this->arrayAttribute($attrs['series'], 'series', fn (array $series) => $this->replaceCartesianData($axis, $series));
            });
        } elseif ($hasSeries) {
            $this->arrayAttribute($attrs['series'], 'series', fn (array $series) => $this->series($series));
        } else {
            $this->applyArrayAttributes($attrs, ['x-axis', 'xAxis'], 'xAxis');
        }

        $this->applyBooleanAttributes($attrs, ['show-grid', 'showGrid'], 'showGrid');
        $this->applyBooleanAttributes($attrs, ['show-points', 'showPoints'], 'showPoints');
        $this->applyBooleanAttributes($attrs, ['begin-at-zero', 'beginAtZero'], 'beginAtZero');
        $this->applyCommonAttributes($attrs);
        $this->applyArrayAttributes($attrs, ['y-axis', 'yAxis'], 'yAxis');
        $this->applyArrayAttributes($attrs, ['annotations'], 'annotations');
        $this->applyArrayAttributes($attrs, ['interaction'], 'interaction');
        $this->applyArrayAttributes($attrs, ['viewport'], 'viewport');
        $this->applyArrayAttributes($attrs, ['sampling'], 'sampling');
        $this->applyStringAttributes($attrs, ['_viewport_change', 'on-viewport-change', 'onViewportChange'], 'onViewportChange');
        $this->applyChartAttributes($attrs);
    }

    /** @param array<string, mixed> $attrs */
    protected function applyChartAttributes(array $attrs): void {}

    /** @param array<int, mixed> $series */
    public function series(array $series): static
    {
        $normalizedSeries = ChartDataNormalizer::normalize(
            $series,
            $this->xAxis['type'],
            $this->chartName(),
            $this->chartType(),
            allowDeferredTypedX: ! $this->xAxisWasConfigured,
        );

        $this->rawSeries = $series;
        $this->series = $normalizedSeries;
        $this->seriesRequiresFinalNormalization = ! $this->xAxisWasConfigured;
        $this->invalidateCartesianWireSnapshot();

        return $this;
    }

    /** @param array<string, mixed> $axis */
    public function xAxis(array $axis): static
    {
        $normalizedAxis = AxisNormalizer::x($axis, $this->chartName(), $this->xAxis);
        $normalizedSeries = ChartDataNormalizer::normalize(
            $this->rawSeries,
            $normalizedAxis['type'],
            $this->chartName(),
            $this->chartType(),
        );
        $normalizedAnnotations = AnnotationNormalizer::normalize(
            $this->rawAnnotations,
            $normalizedAxis['type'],
            $this->chartName(),
        );
        $normalizedViewport = ViewportNormalizer::normalize(
            $this->rawViewport,
            $normalizedAxis['type'],
            $this->chartName(),
        );

        $this->xAxis = $normalizedAxis;
        $this->xAxisWasConfigured = true;
        $this->series = $normalizedSeries;
        $this->annotations = $normalizedAnnotations;
        $this->viewport = $normalizedViewport;
        $this->seriesRequiresFinalNormalization = false;
        $this->invalidateCartesianWireSnapshot();

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
        $this->invalidateCartesianWireSnapshot();

        return $this;
    }

    /** @param array<int, mixed> $annotations */
    public function annotations(array $annotations): static
    {
        $normalizedAnnotations = AnnotationNormalizer::normalize(
            $annotations,
            $this->xAxis['type'],
            $this->chartName(),
        );

        $this->rawAnnotations = $annotations;
        $this->annotations = $normalizedAnnotations;
        $this->invalidateCartesianWireSnapshot();

        return $this;
    }

    /** @param array<string, mixed> $interaction */
    public function interaction(array $interaction): static
    {
        $this->interaction = InteractionNormalizer::normalize($interaction, $this->chartName());
        $this->invalidateCartesianWireSnapshot();

        return $this;
    }

    /** @param array<string, mixed> $viewport */
    public function viewport(array $viewport): static
    {
        $normalizedViewport = ViewportNormalizer::normalize($viewport, $this->xAxis['type'], $this->chartName());

        $this->rawViewport = $viewport;
        $this->viewport = $normalizedViewport;
        $this->invalidateCartesianWireSnapshot();

        return $this;
    }

    /** @param array<string, mixed> $sampling */
    public function sampling(array $sampling): static
    {
        $this->sampling = SamplingNormalizer::normalize($sampling, $this->chartName());
        $this->invalidateCartesianWireSnapshot();

        return $this;
    }

    public function onViewportChange(string $method): static
    {
        $this->viewportChangeMethod = CallbackExpression::normalize($method, $this->chartName());

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
        $this->invalidateCartesianWireSnapshot();

        return $this;
    }

    public function valueFormat(string $valueFormat): static
    {
        parent::valueFormat($valueFormat);
        $this->yAxis['value_format'] = $valueFormat;
        $this->invalidateCartesianWireSnapshot();

        return $this;
    }

    public function currencyCode(string $currencyCode): static
    {
        parent::currencyCode($currencyCode);
        $this->yAxis['currency_code'] = $this->chartProps['currency_code'];
        $this->invalidateCartesianWireSnapshot();

        return $this;
    }

    public function minimumFractionDigits(int $digits): static
    {
        parent::minimumFractionDigits($digits);
        $this->yAxis['minimum_fraction_digits'] = $digits;
        $this->invalidateCartesianWireSnapshot();

        return $this;
    }

    public function maximumFractionDigits(int $digits): static
    {
        parent::maximumFractionDigits($digits);
        $this->yAxis['maximum_fraction_digits'] = $digits;
        $this->invalidateCartesianWireSnapshot();

        return $this;
    }

    protected function resolveProps(CallbackRegistry $registry): array
    {
        $wireSnapshot = $this->cartesianWireSnapshot();

        return [
            ...$this->cartesianProps,
            ...$this->resolveCommonProps($registry),
            ...$wireSnapshot,
            'on_viewport_change' => $this->viewportChangeMethod === null ? 0 : $registry->register($this->viewportChangeMethod),
            ...$this->specificProps(),
        ];
    }

    protected function invalidateCartesianWireSnapshot(): void
    {
        $this->cartesianWireSnapshot = null;
        $this->invalidateCommonWireSnapshot();
    }

    /**
     * @param  array<string, mixed>  $axis
     * @param  array<int, mixed>  $series
     */
    private function replaceCartesianData(array $axis, array $series): void
    {
        $normalizedAxis = AxisNormalizer::x($axis, $this->chartName(), $this->xAxis);
        $normalizedSeries = ChartDataNormalizer::normalize(
            $series,
            $normalizedAxis['type'],
            $this->chartName(),
            $this->chartType(),
        );
        $normalizedAnnotations = AnnotationNormalizer::normalize(
            $this->rawAnnotations,
            $normalizedAxis['type'],
            $this->chartName(),
        );
        $normalizedViewport = ViewportNormalizer::normalize(
            $this->rawViewport,
            $normalizedAxis['type'],
            $this->chartName(),
        );

        $this->xAxis = $normalizedAxis;
        $this->xAxisWasConfigured = true;
        $this->rawSeries = $series;
        $this->series = $normalizedSeries;
        $this->annotations = $normalizedAnnotations;
        $this->viewport = $normalizedViewport;
        $this->seriesRequiresFinalNormalization = false;
        $this->invalidateCartesianWireSnapshot();
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

    /** @return array<string, string> */
    private function cartesianWireSnapshot(): array
    {
        if ($this->cartesianWireSnapshot !== null && $this->hasAvailableSeriesPayload($this->cartesianWireSnapshot)) {
            return $this->cartesianWireSnapshot;
        }

        $this->cartesianWireSnapshot = null;

        if ($this->seriesRequiresFinalNormalization) {
            $this->series = ChartDataNormalizer::normalize(
                $this->rawSeries,
                $this->xAxis['type'],
                $this->chartName(),
                $this->chartType(),
            );
            $this->seriesRequiresFinalNormalization = false;
        }

        if ($this->sampling['mode'] === 'lttb' && ! in_array($this->chartType(), ['line', 'area', 'scatter'], true)) {
            throw new InvalidArgumentException("The {$this->chartName()} does not support LTTB sampling because reducing its points would change the chart's meaning.");
        }
        if ($this->sampling['mode'] === 'lttb' && $this->samplingChangesRelationalGeometry()) {
            throw new InvalidArgumentException("The {$this->chartName()} cannot combine LTTB sampling with related series.");
        }
        if ($this->interaction['mode'] === 'scrub' && $this->viewport['enabled'] && $this->viewport['pan']) {
            throw new InvalidArgumentException("The {$this->chartName()} scrub interaction cannot be combined with one-finger viewport panning.");
        }

        $yAxis = AxisNormalizer::y($this->yAxis, $this->chartName());
        $wireSeries = SamplingNormalizer::apply($this->series, $this->sampling, $this->xAxis['type']);

        $seriesJSON = WireEncoder::encode($wireSeries, $this->chartName());

        return $this->cartesianWireSnapshot = [
            ...WirePayloadStore::series($seriesJSON, $this->chartName()),
            'x_axis_json' => WireEncoder::encode($this->xAxis, $this->chartName()),
            'y_axis_json' => WireEncoder::encode($yAxis, $this->chartName()),
            'annotations_json' => WireEncoder::encode($this->annotations, $this->chartName()),
            'interaction_json' => WireEncoder::encode($this->interaction, $this->chartName(), emptyAsObject: true),
            'viewport_json' => WireEncoder::encode($this->viewport, $this->chartName(), emptyAsObject: true),
            'sampling_json' => WireEncoder::encode($this->sampling, $this->chartName(), emptyAsObject: true),
        ];
    }

    private function samplingChangesRelationalGeometry(): bool
    {
        if (array_filter($this->series, fn (array $series): bool => array_key_exists('fill_to', $series)) !== []) {
            return true;
        }

        return $this->chartType() === 'area' && ($this->specificProps()['area_mode'] ?? null) === 'stacked';
    }

    /** @param array<string, string> $snapshot */
    private function hasAvailableSeriesPayload(array $snapshot): bool
    {
        if (($snapshot['series_transport'] ?? null) !== 'file-v1') {
            return true;
        }

        return isset($snapshot['series_json_file']) && is_readable($snapshot['series_json_file']);
    }
}
