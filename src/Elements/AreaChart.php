<?php

namespace Donmanueldev\NativephpCharts\Elements;

use InvalidArgumentException;

class AreaChart extends CartesianChart
{
    protected string $type = 'area_chart';

    protected string $areaMode = 'overlay';

    public function areaMode(string $mode): static
    {
        if (! in_array($mode, ['overlay', 'stacked'], true)) {
            throw new InvalidArgumentException('The area chart mode must be overlay or stacked.');
        }

        $this->areaMode = $mode;

        return $this;
    }

    public function stacking(string $mode): static
    {
        return $this->areaMode($mode);
    }

    protected function chartType(): string
    {
        return 'area';
    }

    protected function applyChartAttributes(array $attrs): void
    {
        foreach (['area-mode', 'areaMode', 'stacking'] as $attribute) {
            if (! array_key_exists($attribute, $attrs)) {
                continue;
            }

            if (! is_string($attrs[$attribute])) {
                throw new InvalidArgumentException("The area chart {$attribute} attribute must be a string.");
            }

            $this->areaMode($attrs[$attribute]);
        }
    }

    protected function specificProps(): array
    {
        return ['area_mode' => $this->areaMode];
    }
}
