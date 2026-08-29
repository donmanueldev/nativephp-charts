<?php

namespace Donmanueldev\NativephpCharts\Elements;

class PieChart extends RadialChart
{
    protected string $type = 'pie_chart';

    protected function chartType(): string
    {
        return 'pie';
    }
}
