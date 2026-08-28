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
            ->toHaveKeys(['version', 'namespace', 'components'])
            ->and($manifest['version'])
            ->toBe('0.2.0')
            ->and($manifest['namespace'])
            ->toBe('NativePHPCharts');
    });

    it('declares the chart components', function () {
        $manifest = json_decode(
            file_get_contents($this->manifestPath),
            true
        );

        expect($manifest['components'])
            ->toBeArray()
            ->toHaveCount(2);

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

        expect($manifest['components'][1])->toMatchArray([
            'type' => 'bar_chart',
            'element' => 'Donmanueldev\\NativephpCharts\\Elements\\BarChart',
            'blade' => 'Donmanueldev\\NativephpCharts\\Components\\BarChart',
            'android_renderer' => 'com.donmanueldev.plugins.nativephp_charts.ui.BarChartRenderer',
            'ios_renderer' => 'BarChartRenderer',
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

    it('contains the BarChart element and Blade component', function () {
        expect(file_get_contents($this->pluginPath.'/src/Elements/BarChart.php'))
            ->toContain('class BarChart extends LineChart')
            ->toContain("protected string \$type = 'bar_chart'");

        expect(file_get_contents($this->pluginPath.'/src/Components/BarChart.php'))
            ->toContain('class BarChart extends NativeBladeComponent')
            ->toContain("return 'bar_chart'");
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
            ->toContain('series_json')
            ->toContain('style_json')
            ->toContain('NumberFormat')
            ->toContain('Canvas(');
    });

    it('contains the iOS LineChart renderer', function () {
        $file = $this->pluginPath
            .'/resources/ios/LineChartRenderer.swift';

        expect(file_exists($file))->toBeTrue();

        $content = file_get_contents($file);

        expect($content)
            ->toContain('struct LineChartRenderer: View')
            ->toContain('import Charts')
            ->toContain('LineMark(')
            ->toContain('series_json')
            ->toContain('style_json')
            ->toContain('NumberFormatter');
    });

    it('contains native BarChart renderers', function () {
        expect(file_get_contents($this->pluginPath.'/resources/android/BarChartRenderer.kt'))
            ->toContain('object BarChartRenderer')
            ->toContain('drawRoundRect')
            ->toContain('detectTapGestures')
            ->toContain('drawTooltip')
            ->toContain('paint.fontMetrics.descent - paint.fontMetrics.ascent')
            ->toContain('maximumWidth')
            ->toContain('series_json');

        expect(file_get_contents($this->pluginPath.'/resources/ios/BarChartRenderer.swift'))
            ->toContain('struct BarChartRenderer: View')
            ->toContain('BarMark(')
            ->toContain('chartOverlay')
            ->toContain('SpatialTapGesture')
            ->toContain('proxy.position(forX: selectedPoint.label)')
            ->toContain('series_json');
    });

    it('keeps empty bar states accessible and formatter updates reactive', function () {
        $android = file_get_contents($this->pluginPath.'/resources/android/BarChartRenderer.kt');
        $ios = file_get_contents($this->pluginPath.'/resources/ios/BarChartRenderer.swift');

        expect($android)
            ->toContain('remember(locale, valueFormat, currencyCode, minimumFractionDigits, maximumFractionDigits)')
            ->and($ios)
            ->toContain('.accessibilityValue(node.props.getString("empty_label", default: "No data"))');
    });

    it('uses relative padding for non-zero one-point domains on both renderers', function () {
        $android = file_get_contents($this->pluginPath.'/resources/android/LineChartRenderer.kt');
        $ios = file_get_contents($this->pluginPath.'/resources/ios/LineChartRenderer.swift');

        expect($android)
            ->toContain('val padding = if (minimum == 0.0) 1.0 else abs(minimum) * 0.1')
            ->and($ios)
            ->toContain('let padding = span == 0 ? (upper == 0 ? 1 : abs(upper) * 0.1) : span * 0.1');
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
