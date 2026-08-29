<?php

use Donmanueldev\NativephpCharts\Components\DonutChart as DonutChartComponent;
use Donmanueldev\NativephpCharts\Elements\DonutChart;
use Native\Mobile\Edge\CallbackRegistry;

it('publishes a production-safe default inner radius', function () {
    $props = DonutChart::make()->segments([
        ['id' => 'one', 'label' => 'One', 'value' => 1, 'color' => '#6366F1'],
    ])->toArray(new CallbackRegistry)['props'];

    expect($props['inner_radius_ratio'])->toBe(0.6)
        ->and($props['contract_version'])->toBe(1)
        ->and($props['segments_json'])->toContain('"id":"one"');
});

it('supports fluent inner radius and cutout aliases at inclusive boundaries', function () {
    $minimum = DonutChart::make()->innerRadiusRatio(0.2)->toArray(new CallbackRegistry)['props'];
    $maximum = DonutChart::make()->cutout(0.85)->toArray(new CallbackRegistry)['props'];

    expect($minimum['inner_radius_ratio'])->toBe(0.2)
        ->and($maximum['inner_radius_ratio'])->toBe(0.85);
});

it('maps both Blade aliases for the inner radius ratio', function (string $attribute) {
    $chart = DonutChart::make();
    $chart->applyAttributes([$attribute => 0.7]);

    expect($chart->toArray(new CallbackRegistry)['props']['inner_radius_ratio'])->toBe(0.7);
})->with(['inner-radius-ratio', 'innerRadiusRatio', 'cutout']);

it('rejects invalid inner radius values', function (Closure $configure, string $message) {
    expect(fn () => $configure(DonutChart::make()))->toThrow(InvalidArgumentException::class, $message);
})->with([
    'below minimum' => [fn (DonutChart $chart) => $chart->cutout(0.19), 'between 0.2 and 0.85'],
    'above maximum' => [fn (DonutChart $chart) => $chart->innerRadiusRatio(0.86), 'between 0.2 and 0.85'],
    'non finite' => [fn (DonutChart $chart) => $chart->innerRadiusRatio(INF), 'between 0.2 and 0.85'],
    'string attribute' => [function (DonutChart $chart) {
        $chart->applyAttributes(['cutout' => '0.6']);
    }, 'finite number'],
]);

it('exposes the self-closing donut chart Blade component type', function () {
    $method = new ReflectionMethod(DonutChartComponent::class, 'elementType');

    expect($method->invoke(new DonutChartComponent))->toBe('donut_chart');
});
