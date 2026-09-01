package com.donmanueldev.plugins.nativephp_charts.ui

import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Rect

/**
 * Immutable output of the Cartesian layout pass. Rendering and interactions
 * consume this model without recalculating chart geometry.
 */
internal data class NativePHPChartsDomain(val minimum: Double, val maximum: Double) {
    val span: Double get() = maximum - minimum
}

internal data class NativePHPChartsSelectionIdentity(
    val seriesId: String,
    val pointId: String,
)

internal data class NativePHPChartsAnnotationGeometry(
    val annotation: NativePHPChartsAnnotation,
    val physicalAxis: String,
    val start: Float,
    val end: Float,
)

internal data class NativePHPChartsDatum(
    val series: NativePHPChartsSeries,
    val point: NativePHPChartsPoint,
    val center: Offset,
    val bar: Rect? = null,
    val candlestick: NativePHPChartsCandlestickGeometry? = null,
    val areaBaseY: Float? = null,
    val errorMinY: Float? = null,
    val errorMaxY: Float? = null,
    val errorMinX: Float? = null,
    val errorMaxX: Float? = null,
) {
    val selectionIdentity = NativePHPChartsSelectionIdentity(series.id, point.id)
}

internal data class NativePHPChartsLayout(
    val plot: Rect,
    val domain: NativePHPChartsDomain,
    val xDomain: NativePHPChartsDomain?,
    val baselineX: Float?,
    val valueBaselineX: Float?,
    val baselineY: Float,
    val data: List<NativePHPChartsDatum>,
    val dataBySeries: Map<String, List<NativePHPChartsDatum>>,
    val xLabels: List<Pair<Float, String>>,
    val yLabels: List<Pair<Float, String>>,
    val annotations: List<NativePHPChartsAnnotationGeometry>,
    val hitIndex: NativePHPChartsHitIndex,
) {
    fun nearest(location: Offset, threshold: Float): NativePHPChartsDatum? {
        return hitIndex.nearest(plot, location, threshold)
    }
}
