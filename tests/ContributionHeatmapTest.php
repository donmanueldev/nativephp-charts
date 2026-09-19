<?php

use Donmanueldev\NativephpCharts\Components\ContributionHeatmap as ContributionHeatmapComponent;
use Donmanueldev\NativephpCharts\Elements\ContributionHeatmap;
use Native\Mobile\Edge\CallbackRegistry;

it('publishes sorted contribution values and heatmap options', function () {
    $chart = ContributionHeatmap::make();
    $chart->applyAttributes([
        'values' => [
            ['id' => 'second', 'date' => '2026-09-16', 'value' => 4, 'label' => 'Four commits'],
            ['id' => 'first', 'date' => '2026-09-15', 'value' => 0],
        ],
        'end-date' => '2026-09-30',
        'days' => 180,
        'week-starts-on' => 0,
        'colors' => ['#DCFCE7', '#16A34A'],
        'empty-color' => '#F1F5F9',
        'show-month-labels' => false,
        'show-weekday-labels' => true,
        'style' => ['cell' => ['size' => 11, 'gap' => 2, 'cornerRadius' => 3]],
    ]);
    $props = $chart->toArray(new CallbackRegistry)['props'];

    expect(json_decode($props['values_json'], true, flags: JSON_THROW_ON_ERROR))->toBe([
        ['id' => 'first', 'date' => '2026-09-15', 'value' => 0],
        ['id' => 'second', 'date' => '2026-09-16', 'value' => 4, 'label' => 'Four commits'],
    ])->and($props)->toMatchArray([
        'end_date' => '2026-09-30',
        'days' => 180,
        'week_starts_on' => 0,
        'empty_color' => '#F1F5F9',
        'show_month_labels' => false,
        'show_weekday_labels' => true,
    ])->and($props['colors_json'])->toBe('["#DCFCE7","#16A34A"]')
        ->and($props['style_json'])->toBe('{"cell":{"size":11,"gap":2,"corner_radius":3}}');
});

it('rejects duplicate dates and invalid calendar dates', function () {
    expect(fn () => ContributionHeatmap::make()->values([
        ['id' => 'one', 'date' => '2026-09-16', 'value' => 1],
        ['id' => 'two', 'date' => '2026-09-16', 'value' => 2],
    ]))->toThrow(InvalidArgumentException::class, "date '2026-09-16' must be unique")
        ->and(fn () => ContributionHeatmap::make()->endDate('2026-02-30'))
        ->toThrow(InvalidArgumentException::class, 'valid ISO date');
});

it('exposes the self-closing contribution heatmap Blade component', function () {
    $method = new ReflectionMethod(ContributionHeatmapComponent::class, 'elementType');
    expect($method->invoke(new ContributionHeatmapComponent))->toBe('contribution_heatmap');
});
