<?php

namespace Donmanueldev\NativephpCharts\Elements;

use Native\Mobile\Edge\Element;
use Native\Mobile\Edge\CallbackRegistry;

class NativePHPCharts extends Element
{
    protected string $type = 'nativePHPCharts.default';

    protected array $componentProps = [];

    public static function make(): static
    {
        return new static;
    }

    public function value(mixed $value): static
    {
        $this->componentProps['value'] = $value;

        return $this;
    }

    public function onChange(string $method): static
    {
        $this->componentProps['on_change'] = $method;

        return $this;
    }

    protected function resolveProps(CallbackRegistry $registry): array
    {
        $props = $this->componentProps;

        if (isset($props['on_change'])) {
            $props['on_change'] = $registry->register($props['on_change']);
        }

        return $props;
    }
}