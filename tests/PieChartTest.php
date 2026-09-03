<?php

use Donmanueldev\NativephpCharts\Components\PieChart as PieChartComponent;
use Donmanueldev\NativephpCharts\Elements\PieChart;
use Native\Mobile\Edge\CallbackRegistry;

it('publishes stable empty-state pie defaults', function () {
    $props = PieChart::make()->toArray(new CallbackRegistry)['props'];

    expect($props)->toBe([
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
        'legend_json' => '{"visible":false,"position":"bottom","alignment":"center","style":{}}',
        'on_select' => 0,
        'segments_json' => '[]',
        'inner_radius_ratio' => 0.0,
    ]);
});

it('normalizes segments and maps formatting legend style and callbacks', function () {
    $registry = new CallbackRegistry;
    $chart = PieChart::make();
    $chart->applyAttributes([
        'segments' => [
            ['id' => ' web ', 'label' => ' Web ', 'value' => 70, 'color' => '#6366F180'],
            ['id' => 'store', 'label' => 'Store', 'value' => 30.5, 'color' => 'white'],
        ],
        'locale' => 'es_NI',
        'value-format' => 'currency',
        'currency-code' => 'nio',
        'minimum-fraction-digits' => 0,
        'maximum-fraction-digits' => 2,
        'legend' => ['position' => 'trailing', 'alignment' => 'start'],
        'style' => ['segment' => ['gap' => 2, 'cornerRadius' => 4, 'opacity' => .8]],
        '_select' => 'selectSegment',
    ]);

    $props = $chart->toArray($registry)['props'];
    $segments = json_decode($props['segments_json'], true, flags: JSON_THROW_ON_ERROR);

    expect($segments)->toBe([
        ['id' => 'web', 'label' => 'Web', 'value' => 70, 'color' => '#806366F1'],
        ['id' => 'store', 'label' => 'Store', 'value' => 30.5, 'color' => '#FFFFFF'],
    ])->and($props)->toMatchArray([
        'locale' => 'es-NI',
        'value_format' => 'currency',
        'currency_code' => 'NIO',
        'minimum_fraction_digits' => 0,
        'maximum_fraction_digits' => 2,
    ])->and($props['style_json'])->toBe('{"segment":{"gap":2,"corner_radius":4,"opacity":0.8}}')
        ->and(json_decode($props['legend_json'], true, flags: JSON_THROW_ON_ERROR))->toMatchArray([
            'visible' => true, 'position' => 'trailing', 'alignment' => 'start',
        ])->and($props['on_select'])->toBe($registry->lookup('selectSegment'));
});

it('accepts radial style boundaries', function () {
    $style = PieChart::make()->style([
        'segment' => ['gap' => 12, 'corner_radius' => 20, 'opacity' => 0],
    ])->toArray(new CallbackRegistry)['props']['style_json'];

    expect($style)->toBe('{"segment":{"gap":12,"corner_radius":20,"opacity":0}}');
});

it('rejects malformed segment collections', function (Closure $configure, string $message) {
    expect(fn () => $configure(PieChart::make()))->toThrow(InvalidArgumentException::class, $message);
})->with([
    'associative collection' => [fn (PieChart $chart) => $chart->segments(['one' => []]), 'ordered list'],
    'non-array segment' => [fn (PieChart $chart) => $chart->segments(['segment']), 'must be an array'],
    'missing id' => [fn (PieChart $chart) => $chart->segments([['label' => 'One', 'value' => 1, 'color' => '#111']]), 'segment id'],
    'blank label' => [fn (PieChart $chart) => $chart->segments([['id' => 'one', 'label' => ' ', 'value' => 1, 'color' => '#111']]), 'segment label'],
    'invalid color' => [fn (PieChart $chart) => $chart->segments([['id' => 'one', 'label' => 'One', 'value' => 1, 'color' => 'red']]), 'CSS hex color'],
    'negative value' => [fn (PieChart $chart) => $chart->segments([['id' => 'one', 'label' => 'One', 'value' => -1, 'color' => '#111']]), 'greater than or equal to zero'],
    'non-finite value' => [fn (PieChart $chart) => $chart->segments([['id' => 'one', 'label' => 'One', 'value' => INF, 'color' => '#111']]), 'finite number'],
    'all zero' => [fn (PieChart $chart) => $chart->segments([['id' => 'one', 'label' => 'One', 'value' => 0, 'color' => '#111']]), 'at least one value greater than zero'],
    'duplicate id' => [fn (PieChart $chart) => $chart->segments([
        ['id' => 'same', 'label' => 'One', 'value' => 1, 'color' => '#111'],
        ['id' => 'same', 'label' => 'Two', 'value' => 2, 'color' => '#222'],
    ]), "segment id 'same' must be unique"],
    'unknown option' => [fn (PieChart $chart) => $chart->segments([[
        'id' => 'one', 'label' => 'One', 'value' => 1, 'color' => '#111', 'selected' => true,
    ]]), 'selected'],
    'unsafe integer' => [fn (PieChart $chart) => $chart->segments([[
        'id' => 'one', 'label' => 'One', 'value' => 9_007_199_254_740_992, 'color' => '#111',
    ]]), 'exact cross-platform integer range'],
]);

it('retains normalized segments after a rejected replacement', function () {
    $chart = PieChart::make()->segments([
        ['id' => 'web', 'label' => 'Web', 'value' => 70, 'color' => '#AbC'],
        ['id' => 'store', 'label' => 'Store', 'value' => 30, 'color' => '#FFFFFF'],
    ]);
    $before = $chart->toArray(new CallbackRegistry)['props'];

    expect(fn () => $chart->segments([
        ['id' => 'web', 'label' => 'Web', 'value' => -1, 'color' => '#AABBCC'],
    ]))->toThrow(InvalidArgumentException::class, 'greater than or equal to zero')
        ->and($chart->toArray(new CallbackRegistry)['props'])->toBe($before);
});

it('rejects unsupported or out-of-range radial styles', function (array $style, string $message) {
    expect(fn () => PieChart::make()->style($style))->toThrow(InvalidArgumentException::class, $message);
})->with([
    'unsupported section' => [['line' => ['width' => 2]], 'only segment arrays'],
    'gap' => [['segment' => ['gap' => 12.1]], 'between 0 and 12'],
    'corner radius' => [['segment' => ['cornerRadius' => 21]], 'between 0 and 20'],
    'opacity' => [['segment' => ['opacity' => NAN]], 'between 0 and 1'],
]);

it('exposes the self-closing pie chart Blade component type', function () {
    $method = new ReflectionMethod(PieChartComponent::class, 'elementType');

    expect($method->invoke(new PieChartComponent))->toBe('pie_chart');
});
