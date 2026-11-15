<?php

namespace Donmanueldev\NativephpCharts\Elements;

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

    protected function chartType(): string
    {
        return 'candlestick';
    }
}
