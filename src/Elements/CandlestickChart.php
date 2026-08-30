<?php

namespace Donmanueldev\NativephpCharts\Elements;

use InvalidArgumentException;

class CandlestickChart extends CartesianChart
{
    protected string $type = 'candlestick_chart';

    /** @param array<int, mixed> $series */
    public function series(array $series): static
    {
        if (count($series) > 1) {
            throw new InvalidArgumentException('The candlestick chart accepts zero or one ordered series.');
        }

        return parent::series($series);
    }

    protected function chartType(): string
    {
        return 'candlestick';
    }
}
