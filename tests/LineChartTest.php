<?php

use Donmanueldev\NativephpCharts\Elements\AreaChart;
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

it('invalidates serialized snapshots after fluent configuration changes', function () {
    $chart = LineChart::make()
        ->xAxis(['type' => 'number'])
        ->series([[
            'id' => 'signal',
            'name' => 'Signal',
            'color' => '#2563EB',
            'points' => [['id' => 'first', 'label' => 'First', 'x' => 0, 'value' => 1]],
        ]])
        ->style(['line' => ['width' => 2]]);

    $first = $chart->toArray(new CallbackRegistry)['props'];
    $unchanged = $chart->toArray(new CallbackRegistry)['props'];
    $second = $chart
        ->series([[
            'id' => 'signal',
            'name' => 'Signal',
            'color' => '#2563EB',
            'points' => [['id' => 'second', 'label' => 'Second', 'x' => 10, 'value' => 2]],
        ]])
        ->viewport(['enabled' => true, 'minimum' => 0, 'maximum' => 10])
        ->style(['line' => ['width' => 4]])
        ->toArray(new CallbackRegistry)['props'];

    expect($unchanged)->toBe($first)
        ->and($second['series_json'])->not->toBe($first['series_json'])
        ->and($second['viewport_json'])->not->toBe($first['viewport_json'])
        ->and($second['style_json'])->not->toBe($first['style_json']);
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

it('publishes explicit cartesian domains titles baselines and intervals', function () {
    $props = LineChart::make()
        ->xAxis([
            'type' => 'number',
            'title' => 'Elapsed seconds',
            'minimum' => 0,
            'maximum' => 60,
            'baseline' => 0,
            'interval' => 15,
        ])
        ->yAxis([
            'title' => 'Temperature',
            'minimum' => -20.5,
            'maximum' => 40,
            'baseline' => 0,
            'interval' => 10,
        ])
        ->toArray(new CallbackRegistry)['props'];

    expect(json_decode($props['x_axis_json'], true, flags: JSON_THROW_ON_ERROR))->toBe([
        'type' => 'number',
        'date_format' => 'medium',
        'timezone' => '',
        'title' => 'Elapsed seconds',
        'minimum' => 0,
        'maximum' => 60,
        'baseline' => 0,
        'interval' => 15,
    ])->and(json_decode($props['y_axis_json'], true, flags: JSON_THROW_ON_ERROR))->toBe([
        'value_format' => 'number',
        'currency_code' => '',
        'minimum_fraction_digits' => -1,
        'maximum_fraction_digits' => -1,
        'title' => 'Temperature',
        'minimum' => -20.5,
        'maximum' => 40,
        'baseline' => 0,
        'interval' => 10,
    ]);
});

it('publishes declarative interaction viewport sampling and a viewport callback', function () {
    $registry = new CallbackRegistry;
    $props = LineChart::make()
        ->xAxis(['type' => 'date'])
        ->interaction(['enabled' => true, 'mode' => 'scrub', 'crosshair' => 'both', 'tooltip' => 'shared'])
        ->viewport([
            'enabled' => true,
            'minimum' => '2026-08-01',
            'maximum' => '2026-08-31',
            'pan' => false,
            'zoom' => true,
            'minimumSpan' => 86_400,
        ])
        ->sampling(['mode' => 'lttb', 'threshold' => 250])
        ->onViewportChange('viewportChanged')
        ->toArray($registry)['props'];

    expect(json_decode($props['interaction_json'], true, flags: JSON_THROW_ON_ERROR))->toBe([
        'enabled' => true,
        'mode' => 'scrub',
        'crosshair' => 'both',
        'tooltip' => 'shared',
    ])->and(json_decode($props['viewport_json'], true, flags: JSON_THROW_ON_ERROR))->toBe([
        'enabled' => true,
        'pan' => false,
        'zoom' => true,
        'minimum' => '2026-08-01',
        'maximum' => '2026-08-31',
        'minimum_span' => 86400,
    ])->and(json_decode($props['sampling_json'], true, flags: JSON_THROW_ON_ERROR))->toBe([
        'mode' => 'lttb',
        'threshold' => 250,
    ])->and($props['on_viewport_change'])->toBe($registry->lookup('viewportChanged'));
});

it('accepts a datetime viewport minimum span within a subsecond range', function () {
    $props = LineChart::make()
        ->xAxis(['type' => 'datetime'])
        ->viewport([
            'enabled' => true,
            'minimum' => '2026-08-29T08:00:00.100Z',
            'maximum' => '2026-08-29T08:00:00.900Z',
            'minimum_span' => 0.5,
        ])
        ->toArray(new CallbackRegistry)['props'];

    expect(json_decode($props['viewport_json'], true, flags: JSON_THROW_ON_ERROR))->toBe([
        'enabled' => true,
        'pan' => true,
        'zoom' => true,
        'minimum' => '2026-08-29T08:00:00.100+00:00',
        'maximum' => '2026-08-29T08:00:00.900+00:00',
        'minimum_span' => 0.5,
    ]);
});

it('rejects a datetime viewport minimum span larger than its subsecond range', function () {
    expect(fn () => LineChart::make()
        ->xAxis(['type' => 'datetime'])
        ->viewport([
            'enabled' => true,
            'minimum' => '2026-08-29T08:00:00.100Z',
            'maximum' => '2026-08-29T08:00:00.400Z',
            'minimum_span' => 0.5,
        ]))
        ->toThrow(InvalidArgumentException::class, 'minimum span must not exceed the viewport range');
});

it('keeps interaction lossless and sampling opt in', function () {
    $points = array_map(
        fn (int $index): array => ['id' => "point-{$index}", 'label' => "Point {$index}", 'x' => $index, 'value' => sin($index / 10)],
        range(0, 999),
    );
    $series = [['id' => 'signal', 'name' => 'Signal', 'color' => '#2563EB', 'points' => $points]];

    $lossless = LineChart::make()->xAxis(['type' => 'number'])->series($series)->toArray(new CallbackRegistry)['props'];
    $sampled = LineChart::make()
        ->xAxis(['type' => 'number'])
        ->series($series)
        ->sampling(['mode' => 'lttb', 'threshold' => 100])
        ->toArray(new CallbackRegistry)['props'];

    $decodeSeries = static function (array $props): array {
        $wire = isset($props['series_json_file'])
            ? file_get_contents($props['series_json_file'])
            : $props['series_json'];

        return json_decode($wire, true, flags: JSON_THROW_ON_ERROR);
    };
    $losslessPoints = $decodeSeries($lossless)[0]['points'];
    $sampledPoints = $decodeSeries($sampled)[0]['points'];

    expect($losslessPoints)->toHaveCount(1000)
        ->and($sampledPoints)->toHaveCount(100)
        ->and($sampledPoints[0]['id'])->toBe('point-0')
        ->and($sampledPoints[0]['source_index'])->toBe(0)
        ->and($sampledPoints[99]['id'])->toBe('point-999');
});

it('samples category axes by their declared order instead of parsing labels as dates', function () {
    $values = [0, 0, 0, 3, 2];
    $series = function (array $labels) use ($values): array {
        return [[
            'id' => 'signal',
            'name' => 'Signal',
            'color' => '#2563EB',
            'points' => array_map(
                fn (string $label, int $index): array => [
                    'id' => "point-{$index}",
                    'label' => $label,
                    'value' => $values[$index],
                ],
                $labels,
                array_keys($labels),
            ),
        ]];
    };
    $sampledIds = function (array $labels) use ($series): array {
        $wire = LineChart::make()
            ->series($series($labels))
            ->sampling(['mode' => 'lttb', 'threshold' => 3])
            ->toArray(new CallbackRegistry)['props']['series_json'];

        return array_column(json_decode($wire, true, flags: JSON_THROW_ON_ERROR)[0]['points'], 'id');
    };

    expect($sampledIds(['A', 'B', 'C', 'D', 'E']))
        ->toBe(['point-0', 'point-3', 'point-4'])
        ->and($sampledIds(['2020-01-01', '2020-01-02', '2030-01-01', '2030-01-02', '2030-01-03']))
        ->toBe(['point-0', 'point-3', 'point-4']);
});

it('rejects sampling when related series require matching x positions', function () {
    $series = [
        ['id' => 'lower', 'name' => 'Lower', 'color' => '#0F766E', 'points' => [['id' => 'a', 'label' => 'A', 'value' => 1]]],
        ['id' => 'upper', 'name' => 'Upper', 'color' => '#6366F1', 'fill_to' => 'lower', 'points' => [['id' => 'b', 'label' => 'A', 'value' => 2]]],
    ];

    expect(fn () => LineChart::make()->series($series)->sampling(['mode' => 'lttb'])->toArray(new CallbackRegistry))
        ->toThrow(InvalidArgumentException::class, 'cannot combine LTTB sampling with related series');
});

it('rejects sampling for stacked areas', function () {
    expect(fn () => AreaChart::make()
        ->areaMode('stacked')
        ->sampling(['mode' => 'lttb'])
        ->toArray(new CallbackRegistry))
        ->toThrow(InvalidArgumentException::class, 'cannot combine LTTB sampling with related series');
});

it('accepts category annotation bands in their plotted order', function () {
    $annotations = json_decode(LineChart::make()->annotations([[
        'id' => 'period', 'type' => 'band', 'axis' => 'x', 'from' => 'Sep', 'to' => 'Oct',
    ]])->toArray(new CallbackRegistry)['props']['annotations_json'], true, flags: JSON_THROW_ON_ERROR);

    expect($annotations[0]['from'])->toBe('Sep')
        ->and($annotations[0]['to'])->toBe('Oct');
});

it('rejects grid and axis styling scoped to a series', function () {
    expect(fn () => LineChart::make()->series([[
        'id' => 'signal', 'name' => 'Signal', 'color' => '#2563EB',
        'style' => ['grid' => ['visible' => false]],
        'points' => [],
    ]]))->toThrow(InvalidArgumentException::class, 'cannot configure grid or axis options');
});

it('normalizes benchmark corpus sizes without silently sampling', function (int $pointCount) {
    $points = array_map(
        fn (int $index): array => ['id' => "point-{$index}", 'label' => (string) $index, 'x' => $index, 'value' => $index % 97],
        range(0, $pointCount - 1),
    );
    $props = LineChart::make()
        ->xAxis(['type' => 'number'])
        ->series([['id' => 'benchmark', 'name' => 'Benchmark', 'color' => '#2563EB', 'points' => $points]])
        ->toArray(new CallbackRegistry)['props'];
    $wire = isset($props['series_json_file'])
        ? file_get_contents($props['series_json_file'])
        : $props['series_json'];

    expect($wire)->not->toBeFalse()
        ->and(json_decode($wire, true, flags: JSON_THROW_ON_ERROR)[0]['points'])->toHaveCount($pointCount);
})->with([10, 100, 1_000, 10_000]);

it('externalizes oversized series before the native uint16 prop boundary', function () {
    $points = array_map(
        fn (int $index): array => ['id' => "point-{$index}", 'label' => "Point {$index}", 'x' => $index, 'value' => $index % 97],
        range(0, 9_999),
    );
    $props = LineChart::make()
        ->xAxis(['type' => 'number'])
        ->series([['id' => 'benchmark', 'name' => 'Benchmark', 'color' => '#2563EB', 'points' => $points]])
        ->toArray(new CallbackRegistry)['props'];

    expect($props['series_json'])->toBe('[]')
        ->and($props['series_transport'])->toBe('file-v1')
        ->and($props['series_json_file'])->toBeFile()
        ->and(strlen(serialize($props)))->toBeLessThan(65_535)
        ->and(json_decode(file_get_contents($props['series_json_file']), true, flags: JSON_THROW_ON_ERROR)[0]['points'])
        ->toHaveCount(10_000);
});

it('rejects invalid interaction viewport and sampling contracts', function (callable $configure, string $message) {
    expect(fn () => $configure(LineChart::make())->toArray(new CallbackRegistry))
        ->toThrow(InvalidArgumentException::class, $message);
})->with([
    'interaction mode' => [fn (LineChart $chart) => $chart->interaction(['mode' => 'hover']), 'interaction mode must be tap or scrub'],
    'interaction crosshair' => [fn (LineChart $chart) => $chart->interaction(['crosshair' => 'diagonal']), 'interaction crosshair must be none, x, y, or both'],
    'category viewport' => [fn (LineChart $chart) => $chart->viewport(['enabled' => true, 'minimum' => 0, 'maximum' => 10]), 'category x axis does not support a viewport'],
    'viewport pair' => [fn (LineChart $chart) => $chart->xAxis(['type' => 'number'])->viewport(['enabled' => true, 'minimum' => 0]), 'enabled viewport requires minimum and maximum'],
    'viewport minimum span' => [fn (LineChart $chart) => $chart->xAxis(['type' => 'number'])->viewport(['enabled' => true, 'minimum' => 0, 'maximum' => 10, 'minimum_span' => 100]), 'minimum span must not exceed the viewport range'],
    'viewport unknown key' => [fn (LineChart $chart) => $chart->xAxis(['type' => 'number'])->viewport(['enabled' => true, 'minimum' => 0, 'maximum' => 10, 'overscroll' => true]), 'unsupported keys: overscroll'],
    'scrub and pan' => [fn (LineChart $chart) => $chart->xAxis(['type' => 'number'])->interaction(['mode' => 'scrub'])->viewport(['enabled' => true, 'minimum' => 0, 'maximum' => 10]), 'scrub interaction cannot be combined with one-finger viewport panning'],
    'sampling threshold' => [fn (LineChart $chart) => $chart->sampling(['mode' => 'lttb', 'threshold' => 2]), 'sampling threshold must be an integer between 3 and 100000'],
]);

it('publishes per-series line styling fill targets and error ranges', function () {
    $series = json_decode(LineChart::make()->series([
        [
            'id' => 'lower',
            'name' => 'Lower',
            'color' => '#0F766E',
            'style' => [
                'line' => ['width' => 2, 'interpolation' => 'step_after', 'dash' => [6, 3]],
                'points' => ['size' => 4],
            ],
            'points' => [
                ['id' => 'low-a', 'label' => 'A', 'value' => 8, 'error_min' => 6, 'error_max' => 10],
            ],
        ],
        [
            'id' => 'upper',
            'name' => 'Upper',
            'color' => '#6366F1',
            'fill_to' => 'lower',
            'style' => ['line' => ['width' => 4]],
            'points' => [['id' => 'high-a', 'label' => 'A', 'value' => 14]],
        ],
    ])->toArray(new CallbackRegistry)['props']['series_json'], true, flags: JSON_THROW_ON_ERROR);

    expect($series[0]['style'])->toBe([
        'line' => ['width' => 2, 'interpolation' => 'step_after', 'dash' => [6, 3]],
        'points' => ['size' => 4],
    ])->and($series[0]['points'][0])->toMatchArray([
        'error_min' => 6,
        'error_max' => 10,
    ])->and($series[1]['fill_to'])->toBe('lower');
});

it('publishes line and band annotations on either cartesian axis', function () {
    $annotations = json_decode(LineChart::make()
        ->xAxis(['type' => 'number'])
        ->annotations([
            ['id' => 'target', 'type' => 'line', 'axis' => 'y', 'value' => 12, 'label' => 'Target', 'color' => '#DC2626', 'width' => 2],
            ['id' => 'window', 'type' => 'band', 'axis' => 'x', 'from' => 10, 'to' => 20, 'color' => '#2563EB', 'opacity' => 0.2],
        ])
        ->toArray(new CallbackRegistry)['props']['annotations_json'], true, flags: JSON_THROW_ON_ERROR);

    expect($annotations)->toBe([
        ['id' => 'target', 'type' => 'line', 'axis' => 'y', 'color' => '#DC2626', 'label' => 'Target', 'value' => 12, 'width' => 2],
        ['id' => 'window', 'type' => 'band', 'axis' => 'x', 'color' => '#2563EB', 'from' => 10, 'to' => 20, 'opacity' => 0.2],
    ]);
});

it('rejects invalid annotation ranges', function () {
    expect(fn () => LineChart::make()->annotations([
        ['id' => 'window', 'type' => 'band', 'axis' => 'y', 'from' => 20, 'to' => 10],
    ]))->toThrow(InvalidArgumentException::class, 'from must be less than to');
});

it('accepts datetime annotation bands with fractional-second bounds', function () {
    $annotations = json_decode(LineChart::make()
        ->xAxis(['type' => 'datetime'])
        ->annotations([
            [
                'id' => 'deployment',
                'type' => 'band',
                'axis' => 'x',
                'from' => '2026-08-31T09:00:00.100Z',
                'to' => '2026-08-31T09:00:00.400Z',
            ],
        ])
        ->toArray(new CallbackRegistry)['props']['annotations_json'], true, flags: JSON_THROW_ON_ERROR);

    expect($annotations[0])->toMatchArray([
        'from' => '2026-08-31T09:00:00.100+00:00',
        'to' => '2026-08-31T09:00:00.400+00:00',
    ]);
});

it('rejects invalid per-series depth contracts', function (Closure $configure, string $message) {
    expect(fn () => $configure(LineChart::make()))->toThrow(InvalidArgumentException::class, $message);
})->with([
    'unknown fill target' => [fn (LineChart $chart) => $chart->series([[
        'id' => 'one', 'name' => 'One', 'color' => '#111111', 'fill_to' => 'missing', 'points' => [],
    ]]), "fill target 'missing' must reference another series"],
    'partial error range' => [fn (LineChart $chart) => $chart->series([[
        'id' => 'one', 'name' => 'One', 'color' => '#111111',
        'points' => [['id' => 'a', 'label' => 'A', 'value' => 2, 'error_min' => 1]],
    ]]), 'must define both error_min and error_max'],
    'reversed error range' => [fn (LineChart $chart) => $chart->series([[
        'id' => 'one', 'name' => 'One', 'color' => '#111111',
        'points' => [['id' => 'a', 'label' => 'A', 'value' => 2, 'error_min' => 3, 'error_max' => 4]],
    ]]), 'must contain its value'],
    'invalid dash' => [fn (LineChart $chart) => $chart->series([[
        'id' => 'one', 'name' => 'One', 'color' => '#111111',
        'style' => ['line' => ['dash' => [4, 0]]], 'points' => [],
    ]]), 'line.dash values must be greater than zero'],
    'odd dash' => [fn (LineChart $chart) => $chart->series([[
        'id' => 'one', 'name' => 'One', 'color' => '#111111',
        'style' => ['line' => ['dash' => [4]]], 'points' => [],
    ]]), 'line.dash must be a list of 2, 4, 6, or 8 numbers'],
]);

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
    'invalid dash structure' => [fn (LineChart $chart) => $chart->style(['line' => ['dash' => 4]]), 'list of 2, 4, 6, or 8 numbers'],
]);

it('rejects invalid explicit cartesian domains', function (Closure $configure, string $message) {
    expect(fn () => $configure(LineChart::make())->toArray(new CallbackRegistry))
        ->toThrow(InvalidArgumentException::class, $message);
})->with([
    'category domain' => [fn (LineChart $chart) => $chart->xAxis(['minimum' => 0]), 'category x axis does not support an explicit domain'],
    'reversed x domain' => [fn (LineChart $chart) => $chart->xAxis(['type' => 'number', 'minimum' => 2, 'maximum' => 1]), 'minimum must be less than maximum'],
    'reversed y domain' => [fn (LineChart $chart) => $chart->yAxis(['minimum' => 2, 'maximum' => 1]), 'minimum must be less than maximum'],
    'x baseline outside domain' => [fn (LineChart $chart) => $chart->xAxis(['type' => 'number', 'minimum' => 0, 'maximum' => 10, 'baseline' => 12]), 'baseline must be within the explicit domain'],
    'y baseline outside domain' => [fn (LineChart $chart) => $chart->yAxis(['minimum' => 0, 'maximum' => 10, 'baseline' => -1]), 'baseline must be within the explicit domain'],
    'zero x interval' => [fn (LineChart $chart) => $chart->xAxis(['type' => 'number', 'interval' => 0]), 'interval must be greater than zero'],
    'negative y interval' => [fn (LineChart $chart) => $chart->yAxis(['interval' => -1]), 'interval must be greater than zero'],
    'empty x title' => [fn (LineChart $chart) => $chart->xAxis(['title' => '  ']), 'title must be a non-empty string'],
    'non-finite y maximum' => [fn (LineChart $chart) => $chart->yAxis(['maximum' => INF]), 'maximum must be a finite integer or float'],
]);
