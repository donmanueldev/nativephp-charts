package com.donmanueldev.plugins.nativephp_charts.ui

import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Rect

internal class NativePHPChartsHitIndex private constructor(
    private val dataSortedByX: List<NativePHPChartsDatum>,
    private val maximumBarHalfWidth: Float,
) {
    fun nearest(plot: Rect, location: Offset, threshold: Float): NativePHPChartsDatum? {
        if (!plot.contains(location) || threshold <= 0f || dataSortedByX.isEmpty()) {
            return null
        }

        var result: NativePHPChartsDatum? = null
        var nearestDistance = Float.POSITIVE_INFINITY
        val horizontalReach = threshold + maximumBarHalfWidth
        val start = lowerBound(location.x - horizontalReach)

        for (index in start until dataSortedByX.size) {
            val datum = dataSortedByX[index]
            if (datum.center.x > location.x + horizontalReach) {
                break
            }
            val distance = if (datum.bar?.contains(location) == true) {
                0f
            } else {
                (datum.center - location).getDistance()
            }
            if (distance <= threshold && distance < nearestDistance) {
                result = datum
                nearestDistance = distance
            }
        }

        return result
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
            maximumBarHalfWidth = data.maxOfOrNull { it.bar?.width?.div(2f) ?: 0f } ?: 0f,
        )
    }
}
