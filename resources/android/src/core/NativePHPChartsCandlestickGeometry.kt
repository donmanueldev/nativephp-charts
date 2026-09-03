package com.donmanueldev.plugins.nativephp_charts.ui

import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Rect
import kotlin.math.max
import kotlin.math.min

/**
 * Pixel geometry shared by candlestick drawing and hit testing.
 *
 * Compose's y axis grows downward, so a larger market value normally has a
 * smaller y coordinate. [body] normalizes open/close ordering and [anchor] uses
 * close for selection overlays and tooltips.
 */
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

/** Resolves a configured dp width or falls back to 62% of the available x slot. */
internal fun nativePHPChartsCandlestickBodyWidth(
    configuredWidth: Float?,
    density: Float,
    slot: Float,
): Float = configuredWidth?.times(density) ?: (slot * 0.62f)

/** Builds a non-empty body, retaining a 1.5dp minimum for neutral candles. */
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
