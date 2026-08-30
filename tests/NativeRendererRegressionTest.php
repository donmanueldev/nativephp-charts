<?php

function nativeRendererSource(string $path): string
{
    $source = file_get_contents(dirname(__DIR__).'/'.$path);

    expect($source)->not->toBeFalse();

    return $source;
}

function nativeRendererBlock(string $source, string $opening): string
{
    $start = strpos($source, $opening);

    expect($start)->not->toBeFalse();

    $openingBrace = strpos($source, '{', $start);

    expect($openingBrace)->not->toBeFalse();

    $depth = 0;
    $length = strlen($source);
    for ($offset = $openingBrace; $offset < $length; $offset++) {
        if ($source[$offset] === '{') {
            $depth++;
        } elseif ($source[$offset] === '}' && --$depth === 0) {
            return substr($source, $start, $offset - $start + 1);
        }
    }

    throw new RuntimeException("Unable to find the closing brace for {$opening}.");
}

it('sizes Android chart geometry from rendered font metrics', function () {
    $layout = nativeRendererSource('resources/android/src/core/NativePHPChartsLayout.kt');
    $drawing = nativeRendererSource('resources/android/src/rendering/NativePHPChartsDrawing.kt');

    expect($layout)
        ->toContain('measureAxisLabel: (String) -> Float')
        ->toContain('axisLabelHeight: Float')
        ->toContain('measureAxisLabel(formatting.value(it))')
        ->and($drawing)
        ->toContain('fontMetrics.descent - fontMetrics.ascent')
        ->toContain('bottom - verticalPadding - fontMetrics.bottom')
        ->not->toContain('val height = 26.dp.toPx()');
});

it('keeps Android point sizing and accessibility behavior platform neutral', function () {
    $drawing = nativeRendererSource('resources/android/src/rendering/NativePHPChartsDrawing.kt');
    $decoder = nativeRendererSource('resources/android/src/core/NativePHPChartsDecoder.kt');
    $cartesianRenderer = nativeRendererSource('resources/android/src/rendering/NativePHPChartsRenderer.kt');
    $radialRenderer = nativeRendererSource('resources/android/src/rendering/NativePHPChartsRadialRenderer.kt');
    $cartesianPlot = nativeRendererSource('resources/android/src/rendering/NativePHPChartsPlot.kt');
    $radialPlot = nativeRendererSource('resources/android/src/rendering/NativePHPChartsRadialPlot.kt');

    expect(substr_count($drawing, 'series.style?.pointSize ?: configuration.style.pointSize'))->toBe(2)
        ->and($drawing)->toContain('datum.series.style?.pointSize ?: configuration.style.pointSize')
        ->and($decoder)->toContain('pointSize = points.float("size", defaultPointSize(kind))')
        ->toContain('NativePHPChartsKind.Scatter -> 7f')
        ->and($cartesianRenderer)->toContain('.clearAndSetSemantics {')
        ->and($radialRenderer)->toContain('.clearAndSetSemantics {')
        ->and($cartesianPlot)->not->toContain('.distinctBy { it.label }')
        ->and($radialPlot)->not->toContain('.distinctBy { it.label }');
});

it('keeps iOS axes and radial gaps aligned with the public contract', function () {
    $cartesianPlot = nativeRendererSource('resources/ios/Sources/Rendering/NativePHPChartsPlot.swift');
    $radialPlot = nativeRendererSource('resources/ios/Sources/Rendering/NativePHPChartsRadialPlot.swift');

    expect($cartesianPlot)
        ->toContain('if yAxisVisible, snapshot.domain.y.contains(snapshot.domain.baseline)')
        ->and($radialPlot)
        ->toContain('angle: .value("Value", angularRange(for: segment))')
        ->toContain('-> Range<Double>')
        ->toContain(')..<(')
        ->not->toContain('-> ClosedRange<Double>')
        ->toContain('let gapDegrees = min(Double(snapshot.configuration.style.gap), rawDegrees * 0.45)')
        ->not->toContain('angularInset: snapshot.configuration.style.gap');
});

it('applies explicit cartesian axis contracts in both native renderers', function () {
    $androidDecoder = nativeRendererSource('resources/android/src/core/NativePHPChartsDecoder.kt');
    $androidLayout = nativeRendererSource('resources/android/src/core/NativePHPChartsLayout.kt');
    $androidDrawing = nativeRendererSource('resources/android/src/rendering/NativePHPChartsDrawing.kt');
    $iosConfiguration = nativeRendererSource('resources/ios/Sources/Core/NativePHPChartsConfiguration.swift');
    $iosDomain = nativeRendererSource('resources/ios/Sources/Core/NativePHPChartsDomain.swift');
    $iosPlot = nativeRendererSource('resources/ios/Sources/Rendering/NativePHPChartsPlot.swift');

    expect($androidDecoder)
        ->toContain('minimum = axis?.optionalValue("minimum")')
        ->toContain('baseline = axis.doubleOrNull("baseline")')
        ->and($androidLayout)
        ->toContain('configuration.yAxis.minimum')
        ->toContain('configuration.xAxis.interval * 86_400.0')
        ->toContain('configuration.yAxis.labelCount.coerceIn(2, 12)')
        ->and($androidDrawing)
        ->toContain('if (isHorizontalBar) configuration.yAxis.title else configuration.xAxis.title')
        ->toContain('if (isHorizontalBar) configuration.xAxis.title else configuration.yAxis.title')
        ->and($iosConfiguration)
        ->toContain('let minimum: NativePHPChartsWireValue?')
        ->toContain('return type == .date ? interval * 86_400 : interval')
        ->and($iosDomain)
        ->toContain('minimum: configuration.xAxis.plotValue(configuration.xAxis.minimum, formatter: formatter)')
        ->toContain('minimum: configuration.yAxis.minimum?.numberValue')
        ->and($iosPlot)
        ->toContain('.chartXAxisLabel(position: .bottom, alignment: .center)')
        ->toContain('AxisMarks(values: .stride(by: interval))');
});

it('keeps v1.1 depth features wired through both native renderers', function () {
    $androidDecoder = nativeRendererSource('resources/android/src/core/NativePHPChartsDecoder.kt');
    $androidLayout = nativeRendererSource('resources/android/src/core/NativePHPChartsLayout.kt');
    $androidDrawing = nativeRendererSource('resources/android/src/rendering/NativePHPChartsDrawing.kt');
    $iosConfiguration = nativeRendererSource('resources/ios/Sources/Core/NativePHPChartsConfiguration.swift');
    $iosModels = nativeRendererSource('resources/ios/Sources/Core/NativePHPChartsModels.swift');
    $iosMarks = nativeRendererSource('resources/ios/Sources/Rendering/NativePHPChartsMarks.swift');
    $iosPlot = nativeRendererSource('resources/ios/Sources/Rendering/NativePHPChartsPlot.swift');

    expect($androidDecoder)
        ->toContain('style = item.optJSONObject("style")?.let(::decodeSeriesStyle)')
        ->toContain('annotations = decodeAnnotations(input.annotationsJson)')
        ->and($androidLayout)
        ->toContain('stackedHorizontalBars(')
        ->toContain('groupedHorizontalBars(')
        ->and($androidDrawing)
        ->toContain('PathEffect::dashPathEffect')
        ->toContain('nativePHPChartsBetweenPath')
        ->toContain('drawNativePHPChartsErrorRange')
        ->toContain('drawNativePHPChartsAnnotations')
        ->and($iosConfiguration)
        ->toContain('barOrientation: NativePHPChartsBarOrientation')
        ->toContain('annotations: NativePHPChartsAnnotation.decode(input.annotationsJSON)')
        ->and($iosModels)
        ->toContain('func fillTarget(for series: NativePHPChartsSeries')
        ->and($iosMarks)
        ->toContain('case "step_before": .stepStart')
        ->toContain('dash: lineStyle(for: series).dash ?? []')
        ->and($iosPlot)
        ->toContain('NativePHPChartsAnnotations(snapshot: snapshot)');
});

it('keeps unreleased interaction viewport and sampling identity wired through both native renderers', function () {
    $androidWire = nativeRendererSource('resources/android/src/core/NativePHPChartsWireInput.kt');
    $androidPlot = nativeRendererSource('resources/android/src/rendering/NativePHPChartsPlot.kt');
    $androidDrawing = nativeRendererSource('resources/android/src/rendering/NativePHPChartsDrawing.kt');
    $iosConfiguration = nativeRendererSource('resources/ios/Sources/Core/NativePHPChartsConfiguration.swift');
    $iosPlot = nativeRendererSource('resources/ios/Sources/Rendering/NativePHPChartsPlot.swift');

    expect($androidWire)
        ->toContain('interactionJson = props.getString("interaction_json", "{}")')
        ->toContain('onViewportChange = props.getCallbackId("on_viewport_change")')
        ->and($androidPlot)
        ->toContain('rememberUpdatedState(layout)')
        ->toContain('detectNativePHPChartsViewportGestures')
        ->toContain('awaitEachGesture')
        ->toContain('NativePHPChartsViewportSelection.dispatch')
        ->and($androidDrawing)
        ->toContain('interaction.tooltip == "shared"')
        ->and($iosConfiguration)
        ->toContain('selection: NativePHPChartsSelectionConfiguration.decode(input.interactionJSON)')
        ->toContain('viewport: NativePHPChartsViewportConfiguration.decode(input.viewportJSON)')
        ->and($iosPlot)
        ->toContain('NativePHPChartsViewportPayload');
});

it('clips every Android Cartesian viewport layer while leaving axes outside', function () {
    $plot = nativeRendererSource('resources/android/src/rendering/NativePHPChartsPlot.kt');
    $clipOpening = 'clipRect(layout.plot.left, layout.plot.top, layout.plot.right, layout.plot.bottom) {';
    $clip = nativeRendererBlock($plot, $clipOpening);
    $clippedDrawCalls = [
        'drawNativePHPChartsAnnotations(',
        'NativePHPChartsKind.Line -> drawNativePHPChartsLines(',
        'NativePHPChartsKind.Area -> drawNativePHPChartsLines(',
        'NativePHPChartsKind.Bar -> drawNativePHPChartsBars(',
        'NativePHPChartsKind.Scatter -> drawNativePHPChartsScatter(',
        'NativePHPChartsKind.Candlestick -> drawNativePHPChartsCandlesticks(',
        'drawNativePHPChartsSelectionOverlay(',
        'drawNativePHPChartsTooltip(',
    ];

    expect($plot)
        ->toContain("drawNativePHPChartsAxes(configuration, layout, drawingResources)\n        {$clipOpening}")
        ->and($clip)->not->toContain('drawNativePHPChartsAxes(');

    foreach ($clippedDrawCalls as $drawCall) {
        expect($clip)->toContain($drawCall);
    }
});

it('uses the shared iOS bar geometry for rendering and selection', function () {
    $domain = nativeRendererSource('resources/ios/Sources/Core/NativePHPChartsDomain.swift');
    $marks = nativeRendererSource('resources/ios/Sources/Rendering/NativePHPChartsMarks.swift');
    $selection = nativeRendererSource('resources/ios/Sources/Interaction/NativePHPChartsSelection.swift');
    $selectionOverlay = nativeRendererSource('resources/ios/Sources/Interaction/NativePHPChartsSelectionOverlay.swift');

    expect($domain)
        ->toContain('struct NativePHPChartsBarGeometry: Equatable')
        ->toContain('func barGeometry(')
        ->toContain('var anchor: NativePHPChartsPlottedPosition')
        ->and($marks)->toContain('let geometry = snapshot.domain.barGeometry(')
        ->and($selectionOverlay)
        ->toContain('private func selectionDistance(')
        ->toContain('NativePHPChartsSelection.barDistance(')
        ->toContain('geometry: snapshot.domain.barGeometry(')
        ->toContain('private func plottedPosition(for point: NativePHPChartsPoint) -> NativePHPChartsPlottedPosition')
        ->toContain('return snapshot.domain.barGeometry(')
        ->toContain(').anchor')
        ->and($selection)
        ->toContain('static func barDistance(')
        ->toContain('static func candidateAxis(')
        ->toContain('kind == .bar && barOrientation == .horizontal ? .y : .x');
});

it('keeps candlestick and radar contracts wired through both native platforms', function () {
    $manifest = nativeRendererSource('nativephp.json');
    $androidCandlestick = nativeRendererSource('resources/android/src/entrypoints/NativePHPChartsCandlestickChartRenderer.kt');
    $androidDrawing = nativeRendererSource('resources/android/src/rendering/NativePHPChartsDrawing.kt');
    $androidRadar = nativeRendererSource('resources/android/src/rendering/NativePHPChartsRadarRenderer.kt');
    $iosCandlestick = nativeRendererSource('resources/ios/Sources/CandlestickChartRenderer.swift');
    $iosMarks = nativeRendererSource('resources/ios/Sources/Rendering/NativePHPChartsMarks.swift');
    $iosRadar = nativeRendererSource('resources/ios/Sources/RadarChartRenderer.swift');

    expect($manifest)
        ->toContain('"type": "candlestick_chart"')
        ->toContain('"type": "radar_chart"')
        ->and($androidCandlestick)
        ->toContain('NativePHPChartsKind.Candlestick')
        ->and($androidDrawing)
        ->toContain('drawNativePHPChartsCandlesticks')
        ->and($androidRadar)
        ->toContain('NativePHPChartsRadarSelection')
        ->toContain('"chart_type", "radar"')
        ->and($iosCandlestick)
        ->toContain('kind: .candlestick')
        ->and($iosMarks)
        ->toContain('func candlestick(point: NativePHPChartsPoint)')
        ->toContain('BarMark(')
        ->and($iosRadar)
        ->toContain('NativePHPChartsRadarSelection')
        ->toContain('chartType: "radar"');
});
