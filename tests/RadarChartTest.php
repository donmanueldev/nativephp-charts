<?php

use Donmanueldev\NativephpCharts\Elements\RadarChart;
use Native\Mobile\Edge\CallbackRegistry;

it('publishes normalized radar axes and ordered series values', function () {
    $props = RadarChart::make()
        ->axes([
            ['id' => 'speed', 'label' => 'Speed', 'maximum' => 100],
            ['id' => 'quality', 'label' => 'Quality', 'maximum' => 10],
            ['id' => 'cost', 'label' => 'Cost', 'maximum' => 500],
        ])
        ->series([[
            'id' => 'nativephp', 'name' => 'NativePHP', 'color' => '#6366F1',
            'values' => [
                ['axis' => 'speed', 'value' => 88],
                ['axis' => 'quality', 'value' => 9],
                ['axis' => 'cost', 'value' => 220],
            ],
        ]])
        ->gridLevels(4)
        ->fillOpacity(0.3)
        ->toArray(new CallbackRegistry)['props'];

    expect(json_decode($props['axes_json'], true, flags: JSON_THROW_ON_ERROR))->toHaveCount(3)
        ->and(json_decode($props['series_json'], true, flags: JSON_THROW_ON_ERROR)[0]['values'])->toBe([
            ['axis' => 'speed', 'value' => 88],
            ['axis' => 'quality', 'value' => 9],
            ['axis' => 'cost', 'value' => 220],
        ])->and($props['grid_levels'])->toBe(4)
        ->and($props['fill_opacity'])->toBe(0.3);
});

it('rejects incomplete out of order and out of range radar data', function (array $values, string $message) {
    $chart = RadarChart::make()->axes([
        ['id' => 'a', 'label' => 'A', 'maximum' => 10],
        ['id' => 'b', 'label' => 'B', 'maximum' => 10],
        ['id' => 'c', 'label' => 'C', 'maximum' => 10],
    ]);

    expect(fn () => $chart->series([[
        'id' => 'series', 'name' => 'Series', 'color' => '#2563EB', 'values' => $values,
    ]]))->toThrow(InvalidArgumentException::class, $message);
})->with([
    'incomplete' => [[['axis' => 'a', 'value' => 1]], 'one ordered value per axis'],
    'out of order' => [[['axis' => 'b', 'value' => 1], ['axis' => 'a', 'value' => 1], ['axis' => 'c', 'value' => 1]], 'declared axis order'],
    'out of range' => [[['axis' => 'a', 'value' => 11], ['axis' => 'b', 'value' => 1], ['axis' => 'c', 'value' => 1]], 'between zero and its maximum'],
]);

it('registers radar native renderers in the manifest', function () {
    $manifest = json_decode(file_get_contents(__DIR__.'/../nativephp.json'), true, flags: JSON_THROW_ON_ERROR);
    $component = collect($manifest['components'])->firstWhere('type', 'radar_chart');

    expect($component)->toMatchArray([
        'android_renderer' => 'com.donmanueldev.plugins.nativephp_charts.ui.NativePHPChartsRadarChartRenderer',
        'ios_renderer' => 'NativePHPChartsRadarChartRenderer',
        'self_closing' => true,
    ]);
});

it('rejects cartesian axis label counts that have no radar meaning', function () {
    expect(fn () => RadarChart::make()->style(['axis' => ['labelCount' => 6]]))
        ->toThrow(InvalidArgumentException::class, "style option 'axis.labelCount' is not supported");
});
