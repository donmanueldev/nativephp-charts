<?php

namespace Donmanueldev\NativephpCharts\Elements;

use InvalidArgumentException;

class BarChart extends CartesianChart
{
    protected string $type = 'bar_chart';

    protected string $barMode = 'grouped';

    protected string $barOrientation = 'vertical';

    public function mode(string $mode): static
    {
        if (! in_array($mode, ['grouped', 'stacked'], true)) {
            throw new InvalidArgumentException('The bar chart mode must be grouped or stacked.');
        }

        $this->barMode = $mode;

        return $this;
    }

    public function orientation(string $orientation): static
    {
        if (! in_array($orientation, ['vertical', 'horizontal'], true)) {
            throw new InvalidArgumentException('The bar chart orientation must be vertical or horizontal.');
        }

        $this->barOrientation = $orientation;

        return $this;
    }

    protected function chartType(): string
    {
        return 'bar';
    }

    protected function applyChartAttributes(array $attrs): void
    {
        $this->applyStringAttributes($attrs, ['mode', 'bar-mode', 'barMode'], 'mode');
        $this->applyStringAttributes($attrs, ['orientation', 'bar-orientation', 'barOrientation'], 'orientation');
    }

    protected function specificProps(): array
    {
        return [
            'bar_mode' => $this->barMode,
            'bar_orientation' => $this->barOrientation,
        ];
    }
}
