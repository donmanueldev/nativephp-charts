<?php

use Donmanueldev\NativephpCharts\Elements\LineChart;
use Native\Mobile\Edge\CallbackRegistry;

it('preserves v0.2 defaults while publishing the version 1 wire contract', function () {
    $props = LineChart::make()->toArray(new CallbackRegistry)['props'];

    expect($props)->toMatchArray([
        'show_grid' => true,
        'show_points' => true,
        'begin_at_zero' => true,
        'animated' => true,
        'empty_label' => 'No data',
        'a11y_label' => 'Chart',
        'locale' => '',
        'value_format' => 'number',
        'currency_code' => '',
        'minimum_fraction_digits' => -1,
        'maximum_fraction_digits' => -1,
        'contract_version' => 1,
        'style_json' => '{}',
        'series_json' => '[]',
        'x_axis_json' => '{"type":"category","date_format":"medium","timezone":""}',
        'y_axis_json' => '{"value_format":"number","currency_code":"","minimum_fraction_digits":-1,"maximum_fraction_digits":-1}',
        'legend_json' => '{"visible":false,"position":"bottom","alignment":"center","style":{}}',
        'on_select' => 0,
    ]);
});

it('normalizes multiple ordered series with explicit and compatibility point identities', function () {
    $props = LineChart::make()->series([
        [
            'id' => ' actual ',
            'name' => ' Actual ',
            'color' => '#0F766E',
            'points' => [
                ['id' => 'jan', 'label' => ' January ', 'value' => 120],
                ['label' => 'February', 'value' => -12.5],
            ],
        ],
        [
            'id' => 'budget',
            'name' => 'Budget',
            'color' => '#6366F180',
            'points' => [['id' => 'budget-jan', 'label' => 'January', 'value' => 100]],
        ],
    ])->toArray(new CallbackRegistry)['props'];

    $series = json_decode($props['series_json'], true, flags: JSON_THROW_ON_ERROR);
    $legend = json_decode($props['legend_json'], true, flags: JSON_THROW_ON_ERROR);

    expect($series)->toHaveCount(2)
        ->and($series[0])->toMatchArray(['id' => 'actual', 'name' => 'Actual', 'color' => '#0F766E'])
        ->and($series[0]['points'][0])->toBe(['id' => 'jan', 'label' => 'January', 'value' => 120, 'x' => 'January'])
        ->and($series[0]['points'][1]['id'])->toBe('compat-3412a830375392b1')
        ->and($series[1]['color'])->toBe('#806366F1')
        ->and($legend['visible'])->toBeTrue();
});

it('maps Blade attributes, axis options, legend style, and selection callback', function () {
    $registry = new CallbackRegistry;
    $chart = LineChart::make();
    $chart->applyAttributes([
        'series' => [[
            'id' => 'revenue',
            'name' => 'Revenue',
            'color' => '#6366F1',
            'points' => [['id' => 'apr', 'label' => 'Apr', 'x' => '2026-04-01', 'value' => 18.25]],
        ]],
        'show-grid' => 'false',
        'show-points' => '0',
        'begin-at-zero' => true,
        'animated' => '1',
        'empty-label' => 'No revenue yet',
        'a11y-label' => 'Revenue chart',
        'locale' => 'es_NI',
        'x-axis' => ['type' => 'date', 'dateFormat' => 'short', 'timeZone' => 'America/Managua'],
        'y-axis' => ['format' => 'currency', 'currencyCode' => 'nio', 'minimumFractionDigits' => 0, 'maximumFractionDigits' => 2],
        'legend' => [
            'visible' => true,
            'position' => 'top',
            'alignment' => 'start',
            'style' => ['font' => 'accent', 'fontSize' => 12, 'labelColor' => '#334155', 'markerSize' => 8],
        ],
        'style' => ['axis' => ['labelCount' => 6]],
        '_select' => 'handlePoint',
    ]);

    $props = $chart->toArray($registry)['props'];

    expect($props)->toMatchArray([
        'show_grid' => false,
        'show_points' => false,
        'locale' => 'es-NI',
        'value_format' => 'currency',
        'currency_code' => 'NIO',
        'minimum_fraction_digits' => 0,
        'maximum_fraction_digits' => 2,
    ])->and(json_decode($props['x_axis_json'], true, flags: JSON_THROW_ON_ERROR))->toBe([
        'type' => 'date',
        'date_format' => 'short',
        'timezone' => 'America/Managua',
    ])->and(json_decode($props['legend_json'], true, flags: JSON_THROW_ON_ERROR))->toBe([
        'visible' => true,
        'position' => 'top',
        'alignment' => 'start',
        'style' => ['font' => 'accent', 'font_size' => 12, 'label_color' => '#334155', 'marker_size' => 8],
    ])->and($props['on_select'])->toBe($registry->lookup('handlePoint'))
        ->and($registry->resolve($props['on_select']))->toBe(['method' => 'handlePoint', 'args' => []]);
});

it('supports category, number, date, and datetime x axes', function (array $axis, mixed $x, mixed $expected) {
    $point = ['id' => 'point', 'label' => 'Point', 'value' => 10];
    if ($x !== null) {
        $point['x'] = $x;
    }

    $props = LineChart::make()
        ->xAxis($axis)
        ->series([['id' => 'series', 'name' => 'Series', 'color' => '#111111', 'points' => [$point]]])
        ->toArray(new CallbackRegistry)['props'];

    $series = json_decode($props['series_json'], true, flags: JSON_THROW_ON_ERROR);

    expect($series[0]['points'][0]['x'])->toBe($expected);
})->with([
    'category' => [['type' => 'category'], null, 'Point'],
    'number' => [['type' => 'number'], 10.5, 10.5],
    'date' => [['type' => 'date'], '2026-08-28', '2026-08-28'],
    'datetime Z' => [['type' => 'datetime'], '2026-08-28T14:30:00Z', '2026-08-28T14:30:00+00:00'],
    'datetime offset' => [['type' => 'datetime'], '2026-08-28T14:30:00-06:00', '2026-08-28T14:30:00-06:00'],
    'datetime fractional' => [['type' => 'datetime'], '2026-08-28T14:30:00.123456Z', '2026-08-28T14:30:00.123456+00:00'],
]);

it('publishes a compact time-only preset for dense datetime axes', function () {
    $props = LineChart::make()
        ->xAxis(['type' => 'datetime', 'dateFormat' => 'time', 'timezone' => 'America/Managua'])
        ->series([[
            'id' => 'latency',
            'name' => 'Latency',
            'color' => '#2563EB',
            'points' => [[
                'id' => 'latency-0900',
                'label' => '09:00',
                'x' => '2026-08-28T09:00:00.125-06:00',
                'value' => 184,
            ]],
        ]])
        ->toArray(new CallbackRegistry)['props'];

    expect($props['x_axis_json'])
        ->toBe('{"type":"datetime","date_format":"time","timezone":"America/Managua"}');
});

it('merges partial axis configuration regardless of fluent call order', function () {
    $first = LineChart::make()
        ->valueFormat('currency')
        ->currencyCode('USD')
        ->yAxis(['maximumFractionDigits' => 2])
        ->xAxis(['type' => 'datetime'])
        ->xAxis(['dateFormat' => 'long'])
        ->toArray(new CallbackRegistry)['props'];

    $second = LineChart::make()
        ->yAxis(['maximumFractionDigits' => 2])
        ->currencyCode('USD')
        ->valueFormat('currency')
        ->xAxis(['dateFormat' => 'long'])
        ->xAxis(['type' => 'datetime'])
        ->toArray(new CallbackRegistry)['props'];

    expect($first['y_axis_json'])->toBe($second['y_axis_json'])
        ->and($first['y_axis_json'])->toBe('{"value_format":"currency","currency_code":"USD","minimum_fraction_digits":-1,"maximum_fraction_digits":2}')
        ->and($first['x_axis_json'])->toBe($second['x_axis_json'])
        ->and($first['x_axis_json'])->toBe('{"type":"datetime","date_format":"long","timezone":""}');
});

it('allows dependent y axis options in either fluent order', function () {
    $structuredFirst = LineChart::make()
        ->yAxis(['valueFormat' => 'currency'])
        ->currencyCode('USD')
        ->yAxis(['minimumFractionDigits' => 2])
        ->maximumFractionDigits(2)
        ->toArray(new CallbackRegistry)['props'];

    $scalarFirst = LineChart::make()
        ->maximumFractionDigits(2)
        ->yAxis(['minimumFractionDigits' => 2])
        ->currencyCode('USD')
        ->yAxis(['valueFormat' => 'currency'])
        ->toArray(new CallbackRegistry)['props'];

    expect($structuredFirst['y_axis_json'])->toBe($scalarFirst['y_axis_json'])
        ->and($structuredFirst['y_axis_json'])->toBe('{"value_format":"currency","currency_code":"USD","minimum_fraction_digits":2,"maximum_fraction_digits":2}');
});

it('uses last-call-wins precedence for conflicting fluent formatter options', function () {
    $structuredLast = LineChart::make()
        ->valueFormat('number')
        ->yAxis(['valueFormat' => 'currency', 'currencyCode' => 'USD'])
        ->toArray(new CallbackRegistry)['props'];

    $scalarLast = LineChart::make()
        ->yAxis(['valueFormat' => 'currency', 'currencyCode' => 'USD'])
        ->valueFormat('percent')
        ->toArray(new CallbackRegistry)['props'];

    expect($structuredLast['value_format'])->toBe('currency')
        ->and($structuredLast['currency_code'])->toBe('USD')
        ->and($scalarLast['value_format'])->toBe('percent')
        ->and(json_decode($scalarLast['y_axis_json'], true, flags: JSON_THROW_ON_ERROR)['value_format'])->toBe('percent');
});

it('remains extensible for consumer-defined line chart elements', function () {
    $chart = new class extends LineChart {};

    expect($chart->toArray(new CallbackRegistry)['type'])->toBe('line_chart');
});

it('allows x axis configuration after series and revalidates the original data', function () {
    $chart = LineChart::make()->series([[
        'id' => 'series',
        'name' => 'Series',
        'color' => '#111111',
        'points' => [['id' => 'point', 'label' => 'Point', 'x' => 8, 'value' => 10]],
    ]]);

    $props = $chart->xAxis(['type' => 'number'])->toArray(new CallbackRegistry)['props'];

    expect(json_decode($props['series_json'], true, flags: JSON_THROW_ON_ERROR)[0]['points'][0]['x'])->toBe(8);
});

it('accepts BCP-47 locale extensions supported by native formatters', function () {
    $props = LineChart::make()->locale('en-US-u-nu-latn')->toArray(new CallbackRegistry)['props'];

    expect($props['locale'])->toBe('en-US-u-nu-latn');
});

it('rejects invalid identities and typed point data', function (Closure $configure, string $message) {
    expect(fn () => $configure(LineChart::make()))->toThrow(InvalidArgumentException::class, $message);
})->with([
    'duplicate series ids' => [fn (LineChart $chart) => $chart->series([
        ['id' => 'same', 'name' => 'One', 'color' => '#111111', 'points' => []],
        ['id' => 'same', 'name' => 'Two', 'color' => '#222222', 'points' => []],
    ]), "series id 'same' must be unique"],
    'duplicate point ids' => [fn (LineChart $chart) => $chart->series([[
        'id' => 'series', 'name' => 'Series', 'color' => '#111111', 'points' => [
            ['id' => 'same', 'label' => 'One', 'value' => 1],
            ['id' => 'same', 'label' => 'Two', 'value' => 2],
        ],
    ]]), "point id 'same'"],
    'missing numeric x' => [fn (LineChart $chart) => $chart->xAxis(['type' => 'number'])->series([[
        'id' => 'series', 'name' => 'Series', 'color' => '#111111',
        'points' => [['id' => 'point', 'label' => 'Point', 'value' => 1]],
    ]]), 'is required for a number axis'],
    'string numeric x' => [fn (LineChart $chart) => $chart->xAxis(['type' => 'number'])->series([[
        'id' => 'series', 'name' => 'Series', 'color' => '#111111',
        'points' => [['id' => 'point', 'label' => 'Point', 'x' => '1', 'value' => 1]],
    ]]), 'finite integer or float'],
    'numeric category x at serialization' => [fn (LineChart $chart) => $chart->series([[
        'id' => 'series', 'name' => 'Series', 'color' => '#111111',
        'points' => [['id' => 'point', 'label' => 'Point', 'x' => 1, 'value' => 1]],
    ]])->toArray(new CallbackRegistry), 'category x value'],
    'invalid date' => [fn (LineChart $chart) => $chart->xAxis(['type' => 'date'])->series([[
        'id' => 'series', 'name' => 'Series', 'color' => '#111111',
        'points' => [['id' => 'point', 'label' => 'Point', 'x' => '2026-02-30', 'value' => 1]],
    ]]), 'valid calendar date'],
    'datetime without offset' => [fn (LineChart $chart) => $chart->xAxis(['type' => 'datetime'])->series([[
        'id' => 'series', 'name' => 'Series', 'color' => '#111111',
        'points' => [['id' => 'point', 'label' => 'Point', 'x' => '2026-08-28T14:30:00', 'value' => 1]],
    ]]), 'offset or Z'],
    'invalid datetime calendar value' => [fn (LineChart $chart) => $chart->xAxis(['type' => 'datetime'])->series([[
        'id' => 'series', 'name' => 'Series', 'color' => '#111111',
        'points' => [['id' => 'point', 'label' => 'Point', 'x' => '2026-02-30T14:30:00Z', 'value' => 1]],
    ]]), 'valid datetime'],
    'integer value outside exact range' => [fn (LineChart $chart) => $chart->series([[
        'id' => 'series', 'name' => 'Series', 'color' => '#111111',
        'points' => [['id' => 'point', 'label' => 'Point', 'value' => 9_007_199_254_740_992]],
    ]]), 'exact cross-platform integer range'],
    'integer x outside exact range' => [fn (LineChart $chart) => $chart->xAxis(['type' => 'number'])->series([[
        'id' => 'series', 'name' => 'Series', 'color' => '#111111',
        'points' => [['id' => 'point', 'label' => 'Point', 'x' => -9_007_199_254_740_992, 'value' => 1]],
    ]]), 'exact cross-platform integer range'],
]);

it('rejects invalid axes, legends, callbacks, and line styles', function (Closure $configure, string $message) {
    expect(fn () => $configure(LineChart::make())->toArray(new CallbackRegistry))
        ->toThrow(InvalidArgumentException::class, $message);
})->with([
    'locale' => [fn (LineChart $chart) => $chart->locale('en-u'), 'valid BCP-47'],
    'timezone' => [fn (LineChart $chart) => $chart->xAxis(['timezone' => 'Nowhere/Invalid']), 'valid IANA'],
    'date format' => [fn (LineChart $chart) => $chart->xAxis(['dateFormat' => 'compact']), 'date format'],
    'currency without code' => [fn (LineChart $chart) => $chart->valueFormat('currency'), 'currency code is required'],
    'reversed precision' => [fn (LineChart $chart) => $chart->minimumFractionDigits(3)->maximumFractionDigits(2), 'cannot exceed'],
    'legend position' => [fn (LineChart $chart) => $chart->legend(['position' => 'inside']), 'legend position'],
    'callback method' => [fn (LineChart $chart) => $chart->onSelect('handle-point'), 'method name is invalid'],
    'callback arguments' => [fn (LineChart $chart) => $chart->onSelect('handlePoint($value)'), 'JSON-compatible literals'],
    'unknown style' => [fn (LineChart $chart) => $chart->style(['line' => ['dash' => 4]]), 'not supported'],
]);
