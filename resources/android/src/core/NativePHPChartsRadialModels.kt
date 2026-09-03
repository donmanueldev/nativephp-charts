package com.donmanueldev.plugins.nativephp_charts.ui

import androidx.compose.ui.graphics.Color

internal enum class NativePHPChartsRadialKind { Pie, Donut }

internal data class NativePHPChartsRadialSegment(
    val id: String,
    val label: String,
    val value: Double,
    val color: Color,
    val index: Int,
)

internal data class NativePHPChartsRadialStyle(
    val gap: Float = 2f,
    val cornerRadius: Float = 0f,
    val opacity: Float = 1f,
)

/**
 * Renderer-ready pie/donut state. Zero-value segments remain available to the
 * legend but are excluded from geometry and hit testing; [hasData] therefore
 * requires a positive finite total rather than merely a non-empty wire array.
 */
internal data class NativePHPChartsRadialConfiguration(
    val kind: NativePHPChartsRadialKind,
    val segments: List<NativePHPChartsRadialSegment>,
    val style: NativePHPChartsRadialStyle,
    val legend: NativePHPChartsLegend,
    val locale: String,
    val valueFormat: String,
    val currencyCode: String,
    val minimumFractionDigits: Int,
    val maximumFractionDigits: Int,
    val animated: Boolean,
    val emptyLabel: String,
    val accessibilityLabel: String,
    val onSelect: Int,
    val innerRadiusRatio: Float,
) {
    val visibleSegments: List<NativePHPChartsRadialSegment> = segments.filter { it.value > 0.0 }
    val total: Double = visibleSegments.sumOf(NativePHPChartsRadialSegment::value)
    val hasData: Boolean get() = total.isFinite() && total > 0.0
    val legendVisible: Boolean get() = legend.visible ?: (segments.size > 1)
    val animationKey: Int get() {
        var result = kind.hashCode()
        result = 31 * result + innerRadiusRatio.hashCode()
        visibleSegments.forEach { segment ->
            result = 31 * result + segment.id.hashCode()
            result = 31 * result + segment.value.hashCode()
        }
        return result
    }
}
