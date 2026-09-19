<?php

namespace Donmanueldev\NativephpCharts\Elements;

use Donmanueldev\NativephpCharts\Support\ProgressMetricNormalizer;
use Donmanueldev\NativephpCharts\Support\WireEncoder;
use Native\Mobile\Edge\CallbackRegistry;

class ProgressChart extends ChartElement
{
    protected string $type = 'progress_chart';

    /** @var list<array<string, mixed>> */
    private array $metrics = [];

    private string $centerLabel = '';

    public function applyAttributes(array $attrs): void
    {
        $this->applyCommonAttributes($attrs);
        $this->applyArrayAttributes($attrs, ['metrics'], 'metrics');
        $this->applyStringAttributes($attrs, ['center-label', 'centerLabel'], 'centerLabel');
    }

    public function metrics(array $metrics): static
    {
        $this->metrics = ProgressMetricNormalizer::normalize($metrics);
        $this->invalidateCommonWireSnapshot();

        return $this;
    }

    public function centerLabel(string $label): static
    {
        $this->centerLabel = trim($label);

        return $this;
    }

    protected function chartType(): string
    {
        return 'progress';
    }

    protected function resolveProps(CallbackRegistry $registry): array
    {
        return [
            ...$this->resolveCommonProps($registry),
            'metrics_json' => WireEncoder::encode($this->metrics, $this->chartName()),
            'center_label' => $this->centerLabel,
        ];
    }

    protected function legendItemCount(): int
    {
        return count($this->metrics);
    }
}
