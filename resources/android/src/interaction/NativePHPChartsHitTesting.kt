package com.donmanueldev.plugins.nativephp_charts.ui

import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Rect
import kotlin.math.max
import kotlin.math.min

/**
 * Canvas-space hit index shared by tap, scrub, and accessibility selection.
 *
 * Data is sorted by mark center x so candidates can be bounded with binary search.
 * The search expands by the widest bar/candle, uses exact rectangles or candle
 * wick/body distance where available, and rejects marks outside the plot.
 */
internal class NativePHPChartsHitIndex private constructor(
    private val dataSortedByX: List<NativePHPChartsDatum>,
    private val maximumMarkHalfWidth: Float,
) {
    fun nearest(plot: Rect, location: Offset, threshold: Float): NativePHPChartsDatum? {
        if (!plot.contains(location) || threshold <= 0f || dataSortedByX.isEmpty()) {
            return null
        }

        var result: NativePHPChartsDatum? = null
        var nearestDistance = Float.POSITIVE_INFINITY
        val horizontalReach = threshold + maximumMarkHalfWidth
        val start = lowerBound(location.x - horizontalReach)

        for (index in start until dataSortedByX.size) {
            val datum = dataSortedByX[index]
            if (datum.center.x > location.x + horizontalReach) {
                break
            }
            if (!datum.isVisibleIn(plot)) {
                continue
            }
            val distance = when {
                datum.bar?.contains(location) == true -> 0f
                datum.candlestick != null -> candlestickDistance(location, datum.candlestick)
                else -> (datum.center - location).getDistance()
            }
            if (distance <= threshold && distance < nearestDistance) {
                result = datum
                nearestDistance = distance
            }
        }

        return result
    }

    private fun candlestickDistance(
        location: Offset,
        geometry: NativePHPChartsCandlestickGeometry,
    ): Float = min(
        rectangleDistance(location, geometry.body),
        segmentDistance(location, geometry.wickStart, geometry.wickEnd),
    )

    private fun rectangleDistance(location: Offset, rectangle: Rect): Float {
        val deltaX = max(max(rectangle.left - location.x, 0f), location.x - rectangle.right)
        val deltaY = max(max(rectangle.top - location.y, 0f), location.y - rectangle.bottom)
        return Offset(deltaX, deltaY).getDistance()
    }

    private fun segmentDistance(location: Offset, start: Offset, end: Offset): Float {
        val delta = end - start
        val lengthSquared = (delta.x * delta.x) + (delta.y * delta.y)
        if (lengthSquared <= 0f) return (location - start).getDistance()

        val projection = (((location - start).x * delta.x) + ((location - start).y * delta.y)) / lengthSquared
        val fraction = projection.coerceIn(0f, 1f)
        return (location - (start + (delta * fraction))).getDistance()
    }

    private fun lowerBound(x: Float): Int {
        var low = 0
        var high = dataSortedByX.size

        while (low < high) {
            val middle = (low + high) ushr 1
            if (dataSortedByX[middle].center.x < x) {
                low = middle + 1
            } else {
                high = middle
            }
        }

        return low
    }

    companion object {
        fun build(data: List<NativePHPChartsDatum>): NativePHPChartsHitIndex = NativePHPChartsHitIndex(
            dataSortedByX = data.sortedBy { it.center.x },
            maximumMarkHalfWidth = data.maxOfOrNull {
                max(it.bar?.width?.div(2f) ?: 0f, it.candlestick?.body?.width?.div(2f) ?: 0f)
            } ?: 0f,
        )
    }
}

private fun NativePHPChartsDatum.isVisibleIn(plot: Rect): Boolean {
    bar?.let { return it.overlaps(plot) }
    candlestick?.let { geometry ->
        if (geometry.body.overlaps(plot)) return true

        val wickTop = min(geometry.wickStart.y, geometry.wickEnd.y)
        val wickBottom = max(geometry.wickStart.y, geometry.wickEnd.y)
        return geometry.x in plot.left..plot.right && wickTop <= plot.bottom && wickBottom >= plot.top
    }

    return center.x in plot.left..plot.right && center.y in plot.top..plot.bottom
}
