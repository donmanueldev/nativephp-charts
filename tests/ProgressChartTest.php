<?php

use Donmanueldev\NativephpCharts\Components\ProgressChart as ProgressChartComponent;
use Donmanueldev\NativephpCharts\Elements\ProgressChart;
use Native\Mobile\Edge\CallbackRegistry;

it('publishes normalized progress metrics and semantic presentation', function () {
    $registry = new CallbackRegistry;
    $chart = ProgressChart::make();
    $chart->applyAttributes([
        'metrics' => [
            ['id' => ' shipped ', 'label' => ' Shipped ', 'value' => .75, 'color' => '#2563EB'],
            ['id' => 'quality', 'label' => 'Quality', 'value' => 1],
        ],
        'center-label' => '75%',
        'theme' => 'dark',
        'preset' => 'spectrum',
        'error-label' => 'Metrics unavailable',
        'style' => ['ring' => ['trackColor' => '#E2E8F0', 'width' => 12, 'gap' => 4, 'cap' => 'round']],
        'legend' => ['visible' => true],
        '_select' => 'selectMetric',
    ]);
    $props = $chart->toArray($registry)['props'];

    expect(json_decode($props['metrics_json'], true, flags: JSON_THROW_ON_ERROR))->toBe([
        ['id' => 'shipped', 'label' => 'Shipped', 'value' => .75, 'color' => '#2563EB'],
        ['id' => 'quality', 'label' => 'Quality', 'value' => 1],
    ])->and($props)->toMatchArray([
        'contract_version' => 1,
        'center_label' => '75%',
        'theme_mode' => 'dark',
        'preset' => 'spectrum',
        'error_label' => 'Metrics unavailable',
    ])->and($props['style_json'])->toBe('{"ring":{"track_color":"#E2E8F0","width":12,"gap":4,"cap":"round"}}')
        ->and($props['on_select'])->toBe($registry->lookup('selectMetric'));
});

it('rejects invalid progress metrics without replacing the last valid state', function () {
    $chart = ProgressChart::make()->metrics([
        ['id' => 'one', 'label' => 'One', 'value' => .5],
    ]);
    $before = $chart->toArray(new CallbackRegistry)['props'];

    expect(fn () => $chart->metrics([['id' => 'one', 'label' => 'One', 'value' => 1.1]]))
        ->toThrow(InvalidArgumentException::class, 'between 0 and 1')
        ->and($chart->toArray(new CallbackRegistry)['props'])->toBe($before);
});

it('exposes the self-closing progress Blade component', function () {
    $method = new ReflectionMethod(ProgressChartComponent::class, 'elementType');
    expect($method->invoke(new ProgressChartComponent))->toBe('progress_chart');
});
