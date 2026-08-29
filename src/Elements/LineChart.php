<?php

namespace Donmanueldev\NativephpCharts\Elements;

class LineChart extends CartesianChart
{
    protected string $type = 'line_chart';

    protected function chartType(): string
    {
        return 'line';
    }
}
