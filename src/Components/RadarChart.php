<?php

namespace Donmanueldev\NativephpCharts\Components;

use Native\Mobile\Edge\Components\Native\NativeBladeComponent;

class RadarChart extends NativeBladeComponent
{
    protected bool $isSelfClosing = true;

    protected function elementType(): string
    {
        return 'radar_chart';
    }
}
