<?php

use Donmanueldev\NativephpCharts\Elements\LineChart;
use Native\Mobile\Edge\CallbackRegistry;

it('serializes normalized chart data and scalar configuration props', function () {
    $node = LineChart::make()
        ->series([
            [
                'id' => ' monthly-sales ',
                'name' => ' Monthly sales ',
                'color' => '#6366F1',
                'points' => [
                    ['label' => ' January ', 'value' => 120],
                    ['label' => 'February', 'value' => -12.5],
                    ['label' => 'March', 'value' => 0],
                ],
            ],
        ])
        ->showGrid(false)
        ->showPoints(false)
        ->beginAtZero(false)
        ->animated(false)
        ->emptyLabel('Nothing to chart')
        ->a11yLabel('Monthly sales chart')
        ->locale('es-NI')
        ->valueFormat('currency')
        ->currencyCode('NIO')
        ->minimumFractionDigits(0)
        ->maximumFractionDigits(2)
        ->style([
            'line' => ['color' => '#14B8A6', 'width' => 4, 'interpolation' => 'smooth'],
            'points' => ['visible' => true, 'size' => 6],
            'grid' => ['visible' => false, 'color' => '#E2E8F0'],
            'axis' => ['font' => 'accent', 'labelColor' => '#334155', 'labelCount' => 5],
        ])
        ->toArray(new CallbackRegistry);

    expect($node['type'])->toBe('line_chart')
        ->and($node['props'])->toBe([
            'show_grid' => false,
            'show_points' => false,
            'begin_at_zero' => false,
            'animated' => false,
            'empty_label' => 'Nothing to chart',
            'a11y_label' => 'Monthly sales chart',
            'locale' => 'es-NI',
            'value_format' => 'currency',
            'currency_code' => 'NIO',
            'minimum_fraction_digits' => 0,
            'maximum_fraction_digits' => 2,
            'style_json' => '{"line":{"color":"#14B8A6","width":4,"interpolation":"smooth"},"points":{"visible":true,"size":6},"grid":{"visible":false,"color":"#E2E8F0"},"axis":{"label_color":"#334155","font":"accent","label_count":5}}',
            'series_json' => '[{"id":"monthly-sales","name":"Monthly sales","color":"#6366F1","points":[{"label":"January","value":120},{"label":"February","value":-12.5},{"label":"March","value":0}]}]',
        ]);
});

it('renders deterministic defaults and accepts empty series', function () {
    $node = LineChart::make()->series([])->toArray(new CallbackRegistry);

    expect($node['props'])->toBe([
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
        'style_json' => '{}',
        'series_json' => '[]',
    ]);
});

it('maps Blade-style attributes to the scalar wire contract', function () {
    $chart = LineChart::make();

    $chart->applyAttributes([
        'series' => [[
            'id' => 'revenue',
            'name' => 'Revenue',
            'color' => '#6366F1',
            'points' => [['label' => 'Apr', 'value' => 18.25]],
        ]],
        'show-grid' => 'false',
        'show-points' => '0',
        'begin-at-zero' => true,
        'animated' => '1',
        'empty-label' => 'No revenue yet',
        'a11y-label' => 'Revenue chart',
        'locale' => 'en-US',
        'value-format' => 'currency',
        'currency-code' => 'USD',
        'minimum-fraction-digits' => 0,
        'maximum-fraction-digits' => 2,
        'style' => [
            'points' => ['visible' => true, 'color' => '#FFFFFF', 'size' => 5],
            'axis' => ['fontSize' => 11, 'labelCount' => 4],
        ],
    ]);

    $node = $chart->toArray(new CallbackRegistry);

    expect($node['props'])->toBe([
        'show_grid' => false,
        'show_points' => false,
        'begin_at_zero' => true,
        'animated' => true,
        'empty_label' => 'No revenue yet',
        'a11y_label' => 'Revenue chart',
        'locale' => 'en-US',
        'value_format' => 'currency',
        'currency_code' => 'USD',
        'minimum_fraction_digits' => 0,
        'maximum_fraction_digits' => 2,
        'style_json' => '{"points":{"visible":true,"color":"#FFFFFF","size":5},"axis":{"font_size":11,"label_count":4}}',
        'series_json' => '[{"id":"revenue","name":"Revenue","color":"#6366F1","points":[{"label":"Apr","value":18.25}]}]',
    ]);
});

it('rejects more than one series', function () {
    expect(fn () => LineChart::make()->series([
        ['id' => 'first', 'name' => 'First', 'color' => '#111111', 'points' => []],
        ['id' => 'second', 'name' => 'Second', 'color' => '#222222', 'points' => []],
    ]))->toThrow(InvalidArgumentException::class, 'at most one series');
});

it('normalizes CSS alpha colors to the native ARGB wire format', function () {
    $node = LineChart::make()
        ->series([[
            'id' => 'sales',
            'name' => 'Sales',
            'color' => '#14B8A680',
            'points' => [],
        ]])
        ->style(['grid' => ['color' => 'transparent']])
        ->toArray(new CallbackRegistry);

    expect($node['props']['series_json'])->toContain('"color":"#8014B8A6"')
        ->and($node['props']['style_json'])->toBe('{"grid":{"color":"#00000000"}}');
});

it('requires a currency code only for currency values', function () {
    expect(fn () => LineChart::make()->valueFormat('currency')->toArray(new CallbackRegistry))
        ->toThrow(InvalidArgumentException::class, 'currency code is required');

    expect(fn () => LineChart::make()->valueFormat('percent')->toArray(new CallbackRegistry))
        ->not->toThrow(InvalidArgumentException::class);
});

it('rejects incompatible locale, precision, and style options', function (Closure $configure, string $message) {
    expect(fn () => $configure(LineChart::make())->toArray(new CallbackRegistry))
        ->toThrow(InvalidArgumentException::class, $message);
})->with([
    'invalid locale' => [fn (LineChart $chart): LineChart => $chart->locale('spanish'), 'valid BCP-47'],
    'reversed precision' => [fn (LineChart $chart): LineChart => $chart->minimumFractionDigits(3)->maximumFractionDigits(2), 'cannot exceed'],
    'unknown style option' => [fn (LineChart $chart): LineChart => $chart->style(['line' => ['dash' => 4]]), 'not supported'],
    'unsupported style color' => [fn (LineChart $chart): LineChart => $chart->style(['axis' => ['color' => 'indigo-500']]), 'must be a CSS hex color'],
]);

it('rejects malformed chart data', function (array $series, string $message) {
    expect(fn () => LineChart::make()->series($series))
        ->toThrow(InvalidArgumentException::class, $message);
})->with([
    'missing series id' => [
        [['name' => 'Sales', 'color' => '#6366F1', 'points' => []]],
        'id for series at index 0',
    ],
    'unsupported series color' => [
        [['id' => 'sales', 'name' => 'Sales', 'color' => 'violet', 'points' => []]],
        "color for series 'sales'",
    ],
    'string point value' => [
        [['id' => 'sales', 'name' => 'Sales', 'color' => '#6366F1', 'points' => [['label' => 'Jan', 'value' => '120']]]],
        "value at index 0 for series 'sales'",
    ],
    'unordered points' => [
        [['id' => 'sales', 'name' => 'Sales', 'color' => '#6366F1', 'points' => [2 => ['label' => 'Jan', 'value' => 120]]]],
        "points for series 'sales' must be an ordered list",
    ],
]);
