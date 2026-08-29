<?php

namespace Donmanueldev\NativephpCharts\Elements;

use InvalidArgumentException;

class DonutChart extends RadialChart
{
    protected string $type = 'donut_chart';

    protected float $innerRadiusRatio = 0.6;

    public function innerRadiusRatio(float $ratio): static
    {
        if (! is_finite($ratio) || $ratio < 0.2 || $ratio > 0.85) {
            throw new InvalidArgumentException('The donut chart inner radius ratio must be between 0.2 and 0.85.');
        }

        $this->innerRadiusRatio = $ratio;

        return $this;
    }

    public function cutout(float $ratio): static
    {
        return $this->innerRadiusRatio($ratio);
    }

    protected function chartType(): string
    {
        return 'donut';
    }

    protected function applyRadialAttributes(array $attrs): void
    {
        foreach (['inner-radius-ratio', 'innerRadiusRatio', 'cutout'] as $attribute) {
            if (! array_key_exists($attribute, $attrs)) {
                continue;
            }

            $value = $attrs[$attribute];
            if ((! is_int($value) && ! is_float($value)) || ! is_finite((float) $value)) {
                throw new InvalidArgumentException("The donut chart {$attribute} attribute must be a finite number.");
            }

            $this->innerRadiusRatio((float) $value);
        }
    }
}
