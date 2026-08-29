<?php

namespace Donmanueldev\NativephpCharts\Components;

use Native\Mobile\Edge\Components\Native\NativeBladeComponent;

final class AreaChart extends NativeBladeComponent
{
    protected bool $isSelfClosing = true;

    protected function elementType(): string
    {
        return 'area_chart';
    }
}
