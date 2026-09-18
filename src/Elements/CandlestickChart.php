<?php

namespace Donmanueldev\NativephpCharts\Elements;

use InvalidArgumentException;

class CandlestickChart extends CartesianChart
{
    protected string $type = 'candlestick_chart';

    /**
     * Financial prices should use their observed OHLC range by default. Starting at zero
     * compresses ordinary price movement until candle bodies and wicks become unreadable.
     */
    protected array $cartesianProps = [
        'show_grid' => true,
        'show_points' => true,
        'begin_at_zero' => false,
    ];

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
