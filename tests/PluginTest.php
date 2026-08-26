<?php

beforeEach(function () {
    $this->pluginPath = dirname(__DIR__);
    $this->manifestPath = $this->pluginPath.'/nativephp.json';
});

describe('Plugin Manifest', function () {
    it('contains valid JSON', function () {
        expect(file_exists($this->manifestPath))->toBeTrue();

        $manifest = json_decode(
            file_get_contents($this->manifestPath),
            true
        );

        expect(json_last_error())->toBe(JSON_ERROR_NONE)
            ->and($manifest)->toBeArray();
    });

    it('declares the plugin namespace', function () {
        $manifest = json_decode(
            file_get_contents($this->manifestPath),
            true
        );

        expect($manifest)
            ->toHaveKeys(['namespace', 'components'])
            ->and($manifest['namespace'])
            ->toBe('NativePHPCharts');
    });

    it('declares the line chart component', function () {
        $manifest = json_decode(
            file_get_contents($this->manifestPath),
            true
        );

        expect($manifest['components'])
            ->toBeArray()
            ->toHaveCount(1);

        $component = $manifest['components'][0];

        expect($component)
            ->toMatchArray([
                'type' => 'line_chart',
                'element' => 'Donmanueldev\\NativephpCharts\\Elements\\LineChart',
                'blade' => 'Donmanueldev\\NativephpCharts\\Components\\LineChart',
                'android_renderer' => 'com.donmanueldev.plugins.nativephp_charts.ui.LineChartRenderer',
                'ios_renderer' => 'LineChartRenderer',
                'self_closing' => true,
            ]);
    });

    it('declares supported platform versions', function () {
        $manifest = json_decode(
            file_get_contents($this->manifestPath),
            true
        );

        expect($manifest['android']['min_version'])->toBe(26)
            ->and($manifest['ios']['min_version'])->toBe('18.2');
    });
});

describe('PHP Classes', function () {
    it('contains the LineChart Element', function () {
        $file = $this->pluginPath.'/src/Elements/LineChart.php';

        expect(file_exists($file))->toBeTrue();

        $content = file_get_contents($file);

        expect($content)
            ->toContain('class LineChart extends Element')
            ->toContain("protected string \$type = 'line_chart'");
    });

    it('contains the LineChart Blade component', function () {
        $file = $this->pluginPath.'/src/Components/LineChart.php';

        expect(file_exists($file))->toBeTrue();

        $content = file_get_contents($file);

        expect($content)
            ->toContain('class LineChart extends NativeBladeComponent')
            ->toContain("return 'line_chart'");
    });

    it('contains the service provider', function () {
        $file = $this->pluginPath
            .'/src/NativePHPChartsServiceProvider.php';

        expect(file_exists($file))->toBeTrue();
    });
});

describe('Native Renderers', function () {
    it('contains the Android LineChart renderer', function () {
        $file = $this->pluginPath
            .'/resources/android/LineChartRenderer.kt';

        expect(file_exists($file))->toBeTrue();

        $content = file_get_contents($file);

        expect($content)
            ->toContain('object LineChartRenderer')
            ->toContain('@Composable')
            ->toContain('fun Render(')
            ->toContain('NativePHP Line Chart');
    });

    it('contains the iOS LineChart renderer', function () {
        $file = $this->pluginPath
            .'/resources/ios/LineChartRenderer.swift';

        expect(file_exists($file))->toBeTrue();

        $content = file_get_contents($file);

        expect($content)
            ->toContain('struct LineChartRenderer: View')
            ->toContain('NativePHP Line Chart');
    });
});

describe('Composer Configuration', function () {
    it('is configured as a NativePHP UI plugin', function () {
        $composerPath = $this->pluginPath.'/composer.json';

        expect(file_exists($composerPath))->toBeTrue();

        $composer = json_decode(
            file_get_contents($composerPath),
            true
        );

        expect(json_last_error())->toBe(JSON_ERROR_NONE)
            ->and($composer['name'])
            ->toBe('donmanueldev/nativephp-charts')
            ->and($composer['type'])
            ->toBe('nativephp-ui-plugin')
            ->and($composer['require']['nativephp/mobile'])
            ->toBe('^4.0');
    });
});