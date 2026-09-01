package com.donmanueldev.plugins.nativephp_charts.ui

import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Rect
import kotlin.math.max
import kotlin.math.min

internal data class NativePHPChartsCandlestickGeometry(
    val x: Float,
    val openY: Float,
    val highY: Float,
    val lowY: Float,
    val closeY: Float,
    val body: Rect,
) {
    val wickStart: Offset get() = Offset(x, highY)
    val wickEnd: Offset get() = Offset(x, lowY)
    val anchor: Offset get() = Offset(x, closeY)
}

internal fun nativePHPChartsCandlestickBodyWidth(
    configuredWidth: Float?,
    density: Float,
    slot: Float,
): Float = configuredWidth?.times(density) ?: (slot * 0.62f)

internal fun nativePHPChartsCandlestickGeometry(
    x: Float,
    openY: Float,
    highY: Float,
    lowY: Float,
    closeY: Float,
    bodyWidth: Float,
    density: Float,
): NativePHPChartsCandlestickGeometry {
    val bodyTop = min(openY, closeY)

    return NativePHPChartsCandlestickGeometry(
        x = x,
        openY = openY,
        highY = highY,
        lowY = lowY,
        closeY = closeY,
        body = Rect(
            left = x - bodyWidth / 2f,
            top = bodyTop,
            right = x + bodyWidth / 2f,
            bottom = max(openY, closeY).coerceAtLeast(bodyTop + (1.5f * density)),
        ),
    )
}
