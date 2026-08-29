<?php

use Donmanueldev\NativephpCharts\Elements\AreaChart;
use Donmanueldev\NativephpCharts\Elements\BarChart;
use Donmanueldev\NativephpCharts\Elements\DonutChart;
use Donmanueldev\NativephpCharts\Elements\LineChart;
use Donmanueldev\NativephpCharts\Elements\PieChart;
use Donmanueldev\NativephpCharts\Elements\ScatterChart;
use Native\Mobile\Edge\CallbackRegistry;

beforeEach(function () {
    $this->pluginPath = dirname(__DIR__);
    $this->manifest = json_decode(
        file_get_contents($this->pluginPath.'/nativephp.json'),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );
});

it('declares the stable plugin identity and supported platforms', function () {
    expect($this->manifest)
        ->toMatchArray([
            'name' => 'donmanueldev/nativephp-charts',
            'version' => '1.0.0',
            'namespace' => 'NativePHPCharts',
            'platforms' => ['android', 'ios'],
        ])
        ->and($this->manifest['android']['min_version'])->toBe(26)
        ->and($this->manifest['ios']['min_version'])->toBe('18.2');
});

it('registers all chart components with collision-safe renderers', function () {
    expect($this->manifest['components'])->toBe([
        [
            'type' => 'line_chart',
            'element' => 'Donmanueldev\\NativephpCharts\\Elements\\LineChart',
            'blade' => 'Donmanueldev\\NativephpCharts\\Components\\LineChart',
            'android_renderer' => 'com.donmanueldev.plugins.nativephp_charts.ui.NativePHPChartsLineChartRenderer',
            'ios_renderer' => 'NativePHPChartsLineChartRenderer',
            'self_closing' => true,
        ],
        [
            'type' => 'bar_chart',
            'element' => 'Donmanueldev\\NativephpCharts\\Elements\\BarChart',
            'blade' => 'Donmanueldev\\NativephpCharts\\Components\\BarChart',
            'android_renderer' => 'com.donmanueldev.plugins.nativephp_charts.ui.NativePHPChartsBarChartRenderer',
            'ios_renderer' => 'NativePHPChartsBarChartRenderer',
            'self_closing' => true,
        ],
        [
            'type' => 'area_chart',
            'element' => 'Donmanueldev\\NativephpCharts\\Elements\\AreaChart',
            'blade' => 'Donmanueldev\\NativephpCharts\\Components\\AreaChart',
            'android_renderer' => 'com.donmanueldev.plugins.nativephp_charts.ui.NativePHPChartsAreaChartRenderer',
            'ios_renderer' => 'NativePHPChartsAreaChartRenderer',
            'self_closing' => true,
        ],
        [
            'type' => 'scatter_chart',
            'element' => 'Donmanueldev\\NativephpCharts\\Elements\\ScatterChart',
            'blade' => 'Donmanueldev\\NativephpCharts\\Components\\ScatterChart',
            'android_renderer' => 'com.donmanueldev.plugins.nativephp_charts.ui.NativePHPChartsScatterChartRenderer',
            'ios_renderer' => 'NativePHPChartsScatterChartRenderer',
            'self_closing' => true,
        ],
        [
            'type' => 'pie_chart',
            'element' => 'Donmanueldev\\NativephpCharts\\Elements\\PieChart',
            'blade' => 'Donmanueldev\\NativephpCharts\\Components\\PieChart',
            'android_renderer' => 'com.donmanueldev.plugins.nativephp_charts.ui.NativePHPChartsPieChartRenderer',
            'ios_renderer' => 'NativePHPChartsPieChartRenderer',
            'self_closing' => true,
        ],
        [
            'type' => 'donut_chart',
            'element' => 'Donmanueldev\\NativephpCharts\\Elements\\DonutChart',
            'blade' => 'Donmanueldev\\NativephpCharts\\Components\\DonutChart',
            'android_renderer' => 'com.donmanueldev.plugins.nativephp_charts.ui.NativePHPChartsDonutChartRenderer',
            'ios_renderer' => 'NativePHPChartsDonutChartRenderer',
            'self_closing' => true,
        ],
    ]);
});

it('publishes the expected wire type from every PHP element', function (string $class, string $type) {
    $node = $class::make()->toArray(new CallbackRegistry);

    expect($node['type'])->toBe($type)
        ->and($node['props']['contract_version'])->toBe(1);
})->with([
    [LineChart::class, 'line_chart'],
    [AreaChart::class, 'area_chart'],
    [BarChart::class, 'bar_chart'],
    [ScatterChart::class, 'scatter_chart'],
    [PieChart::class, 'pie_chart'],
    [DonutChart::class, 'donut_chart'],
]);

it('ships modular native sources and prefixed renderer entry points', function () {
    $androidPath = $this->pluginPath.'/resources/android/src';
    $iosPath = $this->pluginPath.'/resources/ios/Sources';

    expect($androidPath)->toBeDirectory()
        ->and($iosPath)->toBeDirectory()
        ->and($androidPath.'/core/NativePHPChartsModels.kt')->toBeFile()
        ->and($androidPath.'/core/NativePHPChartsDecoder.kt')->toBeFile()
        ->and($androidPath.'/core/NativePHPChartsLayout.kt')->toBeFile()
        ->and($androidPath.'/rendering/NativePHPChartsDrawing.kt')->toBeFile()
        ->and($androidPath.'/rendering/NativePHPChartsLegend.kt')->toBeFile()
        ->and($androidPath.'/rendering/NativePHPChartsPlot.kt')->toBeFile()
        ->and($androidPath.'/interaction/NativePHPChartsSelection.kt')->toBeFile()
        ->and($androidPath.'/interaction/NativePHPChartsHitTesting.kt')->toBeFile()
        ->and($androidPath.'/entrypoints/NativePHPChartsLineChartRenderer.kt')->toBeFile()
        ->and($androidPath.'/entrypoints/NativePHPChartsAreaChartRenderer.kt')->toBeFile()
        ->and($androidPath.'/entrypoints/NativePHPChartsBarChartRenderer.kt')->toBeFile()
        ->and($androidPath.'/entrypoints/NativePHPChartsScatterChartRenderer.kt')->toBeFile()
        ->and($androidPath.'/entrypoints/NativePHPChartsPieChartRenderer.kt')->toBeFile()
        ->and($androidPath.'/entrypoints/NativePHPChartsDonutChartRenderer.kt')->toBeFile()
        ->and($iosPath.'/Core/NativePHPChartsModels.swift')->toBeFile()
        ->and($iosPath.'/Core/NativePHPChartsConfiguration.swift')->toBeFile()
        ->and($iosPath.'/Core/NativePHPChartsDomain.swift')->toBeFile()
        ->and($iosPath.'/Interaction/NativePHPChartsSelection.swift')->toBeFile()
        ->and($iosPath.'/Interaction/NativePHPChartsSelectionOverlay.swift')->toBeFile()
        ->and($iosPath.'/Rendering/NativePHPChartsPlot.swift')->toBeFile()
        ->and($iosPath.'/LineChartRenderer.swift')->toBeFile()
        ->and($iosPath.'/AreaChartRenderer.swift')->toBeFile()
        ->and($iosPath.'/BarChartRenderer.swift')->toBeFile()
        ->and($iosPath.'/ScatterChartRenderer.swift')->toBeFile()
        ->and($iosPath.'/PieChartRenderer.swift')->toBeFile()
        ->and($iosPath.'/DonutChartRenderer.swift')->toBeFile();
});

it('keeps every renderer entry point thin', function (string $path) {
    $lines = file($this->pluginPath.'/'.$path, FILE_IGNORE_NEW_LINES);

    expect($lines)->not->toBeFalse()
        ->and(count($lines))->toBeLessThan(40);
})->with([
    'resources/android/src/entrypoints/NativePHPChartsLineChartRenderer.kt',
    'resources/android/src/entrypoints/NativePHPChartsAreaChartRenderer.kt',
    'resources/android/src/entrypoints/NativePHPChartsBarChartRenderer.kt',
    'resources/android/src/entrypoints/NativePHPChartsScatterChartRenderer.kt',
    'resources/android/src/entrypoints/NativePHPChartsPieChartRenderer.kt',
    'resources/android/src/entrypoints/NativePHPChartsDonutChartRenderer.kt',
    'resources/ios/Sources/LineChartRenderer.swift',
    'resources/ios/Sources/AreaChartRenderer.swift',
    'resources/ios/Sources/BarChartRenderer.swift',
    'resources/ios/Sources/ScatterChartRenderer.swift',
    'resources/ios/Sources/PieChartRenderer.swift',
    'resources/ios/Sources/DonutChartRenderer.swift',
]);

it('includes marketplace-facing package metadata and policy files', function () {
    $composer = json_decode(
        file_get_contents($this->pluginPath.'/composer.json'),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );

    expect($composer['name'])->toBe('donmanueldev/nativephp-charts')
        ->and($composer['type'])->toBe('nativephp-ui-plugin')
        ->and($composer['require']['php'])->toBe('^8.4')
        ->and($composer['require']['nativephp/mobile'])->toBe('^4.0')
        ->and($composer['support'])->toHaveKeys(['issues', 'source'])
        ->and($this->pluginPath.'/LICENSE.md')->toBeFile()
        ->and($this->pluginPath.'/CHANGELOG.md')->toBeFile()
        ->and($this->pluginPath.'/SECURITY.md')->toBeFile();
});
