<?php

namespace Donmanueldev\NativephpCharts\Elements;

use Donmanueldev\NativephpCharts\Support\SegmentNormalizer;
use Donmanueldev\NativephpCharts\Support\WireEncoder;
use Native\Mobile\Edge\CallbackRegistry;

abstract class RadialChart extends ChartElement
{
    /** @var array<int, mixed> */
    protected array $rawSegments = [];

    /** @var list<array{id: string, label: string, value: int|float, color: string}> */
    protected array $segments = [];

    protected float $innerRadiusRatio = 0.0;

    /** @param array<string, mixed> $attrs */
    public function applyAttributes(array $attrs): void
    {
        if (array_key_exists('segments', $attrs)) {
            $this->arrayAttribute($attrs['segments'], 'segments', fn (array $value) => $this->segments($value));
        }

        $this->applyCommonAttributes($attrs);
        $this->applyRadialAttributes($attrs);
    }

    /** @param array<int, mixed> $segments */
    public function segments(array $segments): static
    {
        $this->rawSegments = $segments;
        $this->segments = SegmentNormalizer::normalize($segments, $this->chartName());

        return $this;
    }

    /** @param array<string, mixed> $attrs */
    protected function applyRadialAttributes(array $attrs): void {}

    protected function resolveProps(CallbackRegistry $registry): array
    {
        $this->segments = SegmentNormalizer::normalize($this->rawSegments, $this->chartName());

        return [
            ...$this->resolveCommonProps($registry),
            'segments_json' => WireEncoder::encode($this->segments, $this->chartName()),
            'inner_radius_ratio' => $this->innerRadiusRatio,
        ];
    }

    protected function legendItemCount(): int
    {
        return count($this->segments);
    }
}
