<?php

namespace Donmanueldev\NativephpCharts\Components;

use Native\Mobile\Edge\Components\Native\NativeBladeComponent;

class ProgressChart extends NativeBladeComponent
{
    protected bool $isSelfClosing = true;

    protected function elementType(): string
    {
        return 'progress_chart';
    }
}
