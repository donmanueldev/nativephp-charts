<?php

namespace Donmanueldev\NativephpCharts\Elements;

class ScatterChart extends CartesianChart
{
    protected string $type = 'scatter_chart';

    /** @var array<string, bool|int|string> */
    protected array $xAxis = ['type' => 'number', 'date_format' => 'medium', 'timezone' => ''];

    protected bool $xAxisWasConfigured = true;

    protected function chartType(): string
    {
        return 'scatter';
    }
}
