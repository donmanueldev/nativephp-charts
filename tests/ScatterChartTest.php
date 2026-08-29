<?php

use Donmanueldev\NativephpCharts\Components\ScatterChart as ScatterChartComponent;
use Donmanueldev\NativephpCharts\Elements\CartesianChart;
use Donmanueldev\NativephpCharts\Elements\LineChart;
use Donmanueldev\NativephpCharts\Elements\ScatterChart;
use Native\Mobile\Edge\CallbackRegistry;

it('uses a numeric x axis and supports multiple ordered series by default', function () {
    $props = ScatterChart::make()->series([
        [
            'id' => 'observed',
            'name' => 'Observed',
            'color' => '#0F766E',
            'points' => [
                ['id' => 'a', 'label' => 'A', 'x' => 1.5, 'value' => 8],
                ['label' => 'B', 'x' => 2, 'value' => 13.5],
            ],
        ],
        [
            'id' => 'forecast',
            'name' => 'Forecast',
            'color' => '#6366F1',
            'points' => [['id' => 'c', 'label' => 'C', 'x' => 3, 'value' => 21]],
        ],
    ])->toArray(new CallbackRegistry)['props'];

    $series = json_decode($props['series_json'], true, flags: JSON_THROW_ON_ERROR);

    expect($props['x_axis_json'])->toBe('{"type":"number","date_format":"medium","timezone":""}')
        ->and($series)->toHaveCount(2)
        ->and($series[0]['points'][0]['x'])->toBe(1.5)
        ->and($series[0]['points'][1]['id'])->toStartWith('compat-')
        ->and(json_decode($props['legend_json'], true, flags: JSON_THROW_ON_ERROR)['visible'])->toBeTrue();
});

it('maps the complete cartesian contract and selection callback', function () {
    $registry = new CallbackRegistry;
    $chart = ScatterChart::make();
    $chart->applyAttributes([
        'series' => [[
            'id' => 'samples',
            'name' => 'Samples',
            'color' => '#6366F1',
            'points' => [['id' => 'one', 'label' => 'One', 'x' => 1, 'value' => 2]],
        ]],
        'show-grid' => false,
        'show-points' => true,
        'begin-at-zero' => false,
        'x-axis' => ['type' => 'number', 'visible' => false, 'labelCount' => 5],
        'y-axis' => ['format' => 'currency', 'currencyCode' => 'usd', 'visible' => true, 'label_count' => 6, 'beginAtZero' => false],
        'legend' => ['visible' => true, 'position' => 'top', 'alignment' => 'end'],
        'style' => ['points' => ['size' => 7], 'grid' => ['visible' => false], 'axis' => ['labelCount' => 5]],
        '_select' => 'selectPoint',
    ]);

    $props = $chart->toArray($registry)['props'];

    expect($props)->toMatchArray([
        'contract_version' => 1,
        'show_grid' => false,
        'show_points' => true,
        'begin_at_zero' => false,
        'value_format' => 'currency',
        'currency_code' => 'USD',
    ])->and($props['style_json'])->toBe('{"points":{"size":7},"grid":{"visible":false},"axis":{"label_count":5}}')
        ->and($props['x_axis_json'])->toBe('{"type":"number","date_format":"medium","timezone":"","visible":false,"label_count":5}')
        ->and($props['y_axis_json'])->toBe('{"value_format":"currency","currency_code":"USD","minimum_fraction_digits":-1,"maximum_fraction_digits":-1,"visible":true,"label_count":6,"begin_at_zero":false}')
        ->and($props['on_select'])->toBe($registry->lookup('selectPoint'));
});

it('validates axis visibility and label counts strictly', function (Closure $configure, string $message) {
    expect(fn () => $configure(ScatterChart::make()))->toThrow(InvalidArgumentException::class, $message);
})->with([
    'x visible' => [fn (ScatterChart $chart) => $chart->xAxis(['visible' => 1]), 'must be a boolean'],
    'y visible' => [fn (ScatterChart $chart) => $chart->yAxis(['visible' => 'false']), 'must be a boolean'],
    'x label count' => [fn (ScatterChart $chart) => $chart->xAxis(['labelCount' => 1]), 'between 2 and 12'],
    'y label count' => [fn (ScatterChart $chart) => $chart->yAxis(['label_count' => 13]), 'between 2 and 12'],
    'begin at zero' => [fn (ScatterChart $chart) => $chart->yAxis(['beginAtZero' => 0]), 'must be a boolean'],
]);

it('updates the axis and series atomically across reactive renders', function () {
    $chart = ScatterChart::make();
    $chart->applyAttributes([
        'series' => [[
            'id' => 'numeric', 'name' => 'Numeric', 'color' => '#2563EB',
            'points' => [['id' => 'numeric-one', 'label' => 'One', 'x' => 1, 'value' => 2]],
        ]],
        'x-axis' => ['type' => 'number'],
    ]);
    $chart->applyAttributes([
        'series' => [[
            'id' => 'dated', 'name' => 'Dated', 'color' => '#7C3AED',
            'points' => [['id' => 'dated-one', 'label' => 'Now', 'x' => '2026-08-28T09:00:00.125-06:00', 'value' => 3]],
        ]],
        'x-axis' => ['type' => 'datetime', 'dateFormat' => 'short'],
    ]);

    $props = $chart->toArray(new CallbackRegistry)['props'];

    expect($props['x_axis_json'])->toContain('"type":"datetime"')
        ->and($props['series_json'])->toContain('2026-08-28T09:00:00.125-06:00');
});

it('requires numeric x values by default and only accepts scatter style sections', function () {
    expect(fn () => ScatterChart::make()->series([[
        'id' => 'samples', 'name' => 'Samples', 'color' => '#111111',
        'points' => [['id' => 'one', 'label' => 'One', 'value' => 2]],
    ]]))->toThrow(InvalidArgumentException::class, 'required for a number axis')
        ->and(fn () => ScatterChart::make()->style(['line' => ['width' => 2]]))
        ->toThrow(InvalidArgumentException::class, 'only points, grid, axis arrays');
});

it('is an independent cartesian element with a self-closing Blade component', function () {
    $method = new ReflectionMethod(ScatterChartComponent::class, 'elementType');

    expect(is_subclass_of(ScatterChart::class, CartesianChart::class))->toBeTrue()
        ->and(is_subclass_of(ScatterChart::class, LineChart::class))->toBeFalse()
        ->and($method->invoke(new ScatterChartComponent))->toBe('scatter_chart');
});
