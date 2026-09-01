<?php

namespace Donmanueldev\NativephpCharts\Components;

use Native\Mobile\Edge\Components\Native\NativeBladeComponent;

class CandlestickChart extends NativeBladeComponent
{
    protected bool $isSelfClosing = true;

    protected function elementType(): string
    {
        return 'candlestick_chart';
    }
}
