<?php

namespace Donmanueldev\NativephpCharts\Elements;

class BarChart extends CartesianChart
{
    protected string $type = 'bar_chart';

    protected function chartType(): string
    {
        return 'bar';
    }

    protected function specificProps(): array
    {
        return ['bar_mode' => 'grouped'];
    }
}
