<?php

use Donmanueldev\NativephpCharts\Elements\BarChart;
use Donmanueldev\NativephpCharts\Elements\CartesianChart;
use Donmanueldev\NativephpCharts\Elements\LineChart;
use Native\Mobile\Edge\CallbackRegistry;

it('is an independent grouped cartesian chart with multiple series', function () {
    $node = BarChart::make()->series([
        ['id' => 'actual', 'name' => 'Actual', 'color' => '#14B8A6', 'points' => [['id' => 'a', 'label' => 'Mon', 'value' => 12]]],
        ['id' => 'budget', 'name' => 'Budget', 'color' => '#6366F1', 'points' => [['id' => 'b', 'label' => 'Mon', 'value' => 15]]],
    ])->toArray(new CallbackRegistry);

    expect($node['type'])->toBe('bar_chart')
        ->and($node['props']['bar_mode'])->toBe('grouped')
        ->and(json_decode($node['props']['legend_json'], true, flags: JSON_THROW_ON_ERROR)['visible'])->toBeTrue()
        ->and(is_subclass_of(BarChart::class, LineChart::class))->toBeFalse()
        ->and(is_subclass_of(BarChart::class, CartesianChart::class))->toBeTrue();
});

it('supports bar-specific style without accepting line-only options', function () {
    $props = BarChart::make()->style([
        'bar' => ['radius' => 6, 'width' => 18],
        'grid' => ['visible' => false],
        'axis' => ['labelCount' => 4],
    ])->toArray(new CallbackRegistry)['props'];

    expect($props['style_json'])->toBe('{"bar":{"radius":6,"width":18},"grid":{"visible":false},"axis":{"label_count":4}}');

    expect(fn () => BarChart::make()->style(['line' => ['width' => 2]]))
        ->toThrow(InvalidArgumentException::class, 'bar chart style')
        ->and(fn () => BarChart::make()->style(['bar' => ['width' => 0]]))
        ->toThrow(InvalidArgumentException::class, 'bar.width');
});

it('supports grouped or stacked bars in vertical or horizontal orientation', function () {
    $props = BarChart::make()
        ->mode('stacked')
        ->orientation('horizontal')
        ->toArray(new CallbackRegistry)['props'];

    expect($props)->toMatchArray([
        'bar_mode' => 'stacked',
        'bar_orientation' => 'horizontal',
    ]);

    $fromAttributes = BarChart::make();
    $fromAttributes->applyAttributes(['mode' => 'stacked', 'orientation' => 'horizontal']);

    expect($fromAttributes->toArray(new CallbackRegistry)['props'])->toMatchArray($props)
        ->and(fn () => BarChart::make()->mode('overlay'))
        ->toThrow(InvalidArgumentException::class, 'grouped or stacked')
        ->and(fn () => BarChart::make()->orientation('radial'))
        ->toThrow(InvalidArgumentException::class, 'vertical or horizontal');
});

it('reports bar-specific validation messages', function () {
    expect(fn () => BarChart::make()->series([[
        'id' => 'orders',
        'name' => 'Orders',
        'color' => 'violet',
        'points' => [],
    ]]))->toThrow(InvalidArgumentException::class, 'The bar chart color');
});

it('preserves the v0.2 scalar and compatibility-point contract', function () {
    $props = BarChart::make()
        ->series([[
            'id' => 'orders',
            'name' => 'Orders',
            'color' => '#0F766E',
            'points' => [['label' => 'Mon', 'value' => 18]],
        ]])
        ->showGrid(false)
        ->showPoints(false)
        ->beginAtZero(false)
        ->animated(false)
        ->locale('es-NI')
        ->valueFormat('currency')
        ->currencyCode('NIO')
        ->maximumFractionDigits(0)
        ->toArray(new CallbackRegistry)['props'];

    expect($props)->toMatchArray([
        'show_grid' => false,
        'show_points' => false,
        'begin_at_zero' => false,
        'animated' => false,
        'locale' => 'es-NI',
        'value_format' => 'currency',
        'currency_code' => 'NIO',
        'maximum_fraction_digits' => 0,
        'bar_mode' => 'grouped',
    ])->and(json_decode($props['series_json'], true, flags: JSON_THROW_ON_ERROR)[0]['points'][0])
        ->toMatchArray([
            'label' => 'Mon',
            'value' => 18,
            'x' => 'Mon',
        ]);
});

it('remains extensible for consumer-defined bar chart elements', function () {
    $chart = new class extends BarChart {};

    expect($chart->toArray(new CallbackRegistry)['type'])->toBe('bar_chart');
});
