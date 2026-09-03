<?php

namespace Donmanueldev\NativephpCharts\Elements;

use Donmanueldev\NativephpCharts\Support\RadarDataNormalizer;
use Donmanueldev\NativephpCharts\Support\WireEncoder;
use InvalidArgumentException;
use Native\Mobile\Edge\CallbackRegistry;

class RadarChart extends ChartElement
{
    protected string $type = 'radar_chart';

    /** @var list<array{id: string, label: string, maximum: float|int}> */
    protected array $axes = [];

    /** @var array<int, mixed> */
    protected array $rawSeries = [];

    /** @var list<array<string, mixed>> */
    protected array $series = [];

    protected int $gridLevels = 5;

    protected float $fillOpacity = 0.22;

    public function applyAttributes(array $attrs): void
    {
        $this->applyCommonAttributes($attrs);

        $hasAxes = array_key_exists('axes', $attrs);
        $hasSeries = array_key_exists('series', $attrs);
        if ($hasAxes && $hasSeries) {
            $this->arrayAttribute($attrs['axes'], 'axes', function (array $axes) use ($attrs): void {
                $this->arrayAttribute($attrs['series'], 'series', fn (array $series) => $this->replaceData($axes, $series));
            });
        } else {
            $this->applyArrayAttributes($attrs, ['axes'], 'axes');
            $this->applyArrayAttributes($attrs, ['series'], 'series');
        }

        $this->applyIntegerAttributes($attrs, ['grid-levels', 'gridLevels'], 'gridLevels');
        foreach (['fill-opacity', 'fillOpacity'] as $key) {
            if (array_key_exists($key, $attrs)) {
                $this->fillOpacity($attrs[$key]);
            }
        }
    }

    public function axes(array $axes): static
    {
        $normalizedAxes = RadarDataNormalizer::axes($axes);
        $normalizedSeries = RadarDataNormalizer::series($this->rawSeries, $normalizedAxes);

        $this->axes = $normalizedAxes;
        $this->series = $normalizedSeries;

        return $this;
    }

    public function series(array $series): static
    {
        $normalizedSeries = $this->axes === [] ? [] : RadarDataNormalizer::series($series, $this->axes);

        $this->rawSeries = $series;
        $this->series = $normalizedSeries;

        return $this;
    }

    public function gridLevels(int $gridLevels): static
    {
        if ($gridLevels < 2 || $gridLevels > 10) {
            throw new InvalidArgumentException('The radar chart grid levels must be between 2 and 10.');
        }
        $this->gridLevels = $gridLevels;

        return $this;
    }

    public function fillOpacity(mixed $fillOpacity): static
    {
        if ((! is_int($fillOpacity) && ! is_float($fillOpacity)) || ! is_finite((float) $fillOpacity) || $fillOpacity < 0 || $fillOpacity > 1) {
            throw new InvalidArgumentException('The radar chart fill opacity must be between 0 and 1.');
        }
        $this->fillOpacity = (float) $fillOpacity;

        return $this;
    }

    protected function chartType(): string
    {
        return 'radar';
    }

    protected function resolveProps(CallbackRegistry $registry): array
    {
        if ($this->axes === []) {
            throw new InvalidArgumentException('The radar chart requires at least three axes.');
        }

        return [
            ...$this->resolveCommonProps($registry),
            'axes_json' => WireEncoder::encode($this->axes, 'radar chart'),
            'series_json' => WireEncoder::encode($this->series, 'radar chart'),
            'grid_levels' => $this->gridLevels,
            'fill_opacity' => $this->fillOpacity,
        ];
    }

    protected function legendItemCount(): int
    {
        return count($this->series);
    }

    /**
     * @param  array<int, mixed>  $axes
     * @param  array<int, mixed>  $series
     */
    private function replaceData(array $axes, array $series): void
    {
        $normalizedAxes = RadarDataNormalizer::axes($axes);
        $normalizedSeries = RadarDataNormalizer::series($series, $normalizedAxes);

        $this->axes = $normalizedAxes;
        $this->rawSeries = $series;
        $this->series = $normalizedSeries;
    }
}
