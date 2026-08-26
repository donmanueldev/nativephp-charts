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
        ->toArray(new CallbackRegistry);

    expect($node['type'])->toBe('line_chart')
        ->and($node['props'])->toBe([
            'show_grid' => false,
            'show_points' => false,
            'begin_at_zero' => false,
            'animated' => false,
            'empty_label' => 'Nothing to chart',
            'a11y_label' => 'Monthly sales chart',
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
        'series_json' => '[]',
    ]);
});

it('maps Blade-style attributes to the scalar wire contract', function () {
    $chart = LineChart::make();

    $chart->applyAttributes([
        'series' => [[
            'id' => 'revenue',
            'name' => 'Revenue',
            'color' => 'indigo-500/80',
            'points' => [['label' => 'Apr', 'value' => 18.25]],
        ]],
        'show-grid' => 'false',
        'show-points' => '0',
        'begin-at-zero' => true,
        'animated' => '1',
        'empty-label' => 'No revenue yet',
        'a11y-label' => 'Revenue chart',
    ]);

    $node = $chart->toArray(new CallbackRegistry);

    expect($node['props'])->toBe([
        'show_grid' => false,
        'show_points' => false,
        'begin_at_zero' => true,
        'animated' => true,
        'empty_label' => 'No revenue yet',
        'a11y_label' => 'Revenue chart',
        'series_json' => '[{"id":"revenue","name":"Revenue","color":"indigo-500/80","points":[{"label":"Apr","value":18.25}]}]',
    ]);
});

it('rejects more than one series', function () {
    expect(fn () => LineChart::make()->series([
        ['id' => 'first', 'name' => 'First', 'color' => '#111111', 'points' => []],
        ['id' => 'second', 'name' => 'Second', 'color' => '#222222', 'points' => []],
    ]))->toThrow(InvalidArgumentException::class, 'at most one series');
});

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
