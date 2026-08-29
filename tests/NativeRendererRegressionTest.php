<?php

function nativeRendererSource(string $path): string
{
    $source = file_get_contents(dirname(__DIR__).'/'.$path);

    expect($source)->not->toBeFalse();

    return $source;
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

    expect(substr_count($drawing, 'configuration.style.pointSize.dp.toPx() / 2f'))->toBe(2)
        ->and($decoder)->toContain('pointSize = points.float("size", defaultPointSize(kind))')
        ->toContain('NativePHPChartsKind.Scatter -> 7f')
        ->and($cartesianRenderer)->toContain('.clearAndSetSemantics {')
        ->and($radialRenderer)->toContain('.clearAndSetSemantics {')
        ->and($cartesianPlot)->not->toContain('.distinctBy { it.label }')
        ->and($radialPlot)->not->toContain('.distinctBy { it.label }');
});

it('keeps iOS axes radial gaps and VoiceOver semantics aligned with the public contract', function () {
    $cartesianPlot = nativeRendererSource('resources/ios/Sources/Rendering/NativePHPChartsPlot.swift');
    $radialPlot = nativeRendererSource('resources/ios/Sources/Rendering/NativePHPChartsRadialPlot.swift');

    expect($cartesianPlot)
        ->toContain('if yAxisVisible, snapshot.domain.y.contains(0)')
        ->toContain('.accessibilityElement(children: .ignore)')
        ->and($radialPlot)
        ->toContain('angle: .value("Value", angularRange(for: segment))')
        ->toContain('let gapDegrees = min(Double(snapshot.configuration.style.gap), rawDegrees * 0.45)')
        ->not->toContain('angularInset: snapshot.configuration.style.gap');
});
