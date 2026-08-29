<?php

use Donmanueldev\NativephpCharts\Components\AreaChart as AreaChartComponent;
use Donmanueldev\NativephpCharts\Elements\AreaChart;
use Native\Mobile\Edge\CallbackRegistry;

it('serializes overlay area charts by default and supports explicit stacking', function () {
    $overlay = AreaChart::make()->toArray(new CallbackRegistry);
    $stacked = AreaChart::make()->stacking('stacked')->toArray(new CallbackRegistry);

    expect($overlay['type'])->toBe('area_chart')
        ->and($overlay['props']['area_mode'])->toBe('overlay')
        ->and($stacked['props']['area_mode'])->toBe('stacked');
});

it('normalizes area and line styling for the native fill renderer', function () {
    $props = AreaChart::make()->style([
        'line' => ['width' => 3, 'interpolation' => 'smooth'],
        'area' => ['opacity' => 0.35, 'gradient' => false],
        'points' => ['visible' => false],
    ])->toArray(new CallbackRegistry)['props'];

    expect($props['style_json'])->toBe('{"line":{"width":3,"interpolation":"smooth"},"area":{"opacity":0.35,"gradient":false},"points":{"visible":false}}');
});

it('rejects invalid area gradient values', function () {
    expect(fn () => AreaChart::make()->style(['area' => ['gradient' => 'yes']]))
        ->toThrow(InvalidArgumentException::class, 'area.gradient');
});

it('rejects non-finite area opacity at the public style boundary', function (float $opacity) {
    expect(fn () => AreaChart::make()->style(['area' => ['opacity' => $opacity]]))
        ->toThrow(InvalidArgumentException::class, 'between 0 and 1');
})->with([NAN, INF, -INF]);

it('maps stacking from Blade attributes and rejects unsupported modes', function () {
    $chart = AreaChart::make();
    $chart->applyAttributes(['stacking' => 'stacked']);

    expect($chart->toArray(new CallbackRegistry)['props']['area_mode'])->toBe('stacked')
        ->and(fn () => AreaChart::make()->areaMode('normalized'))
        ->toThrow(InvalidArgumentException::class, 'overlay or stacked');
});

it('exposes the self-closing area chart Blade component type', function () {
    $method = new ReflectionMethod(AreaChartComponent::class, 'elementType');

    expect($method->invoke(new AreaChartComponent))->toBe('area_chart');
});
