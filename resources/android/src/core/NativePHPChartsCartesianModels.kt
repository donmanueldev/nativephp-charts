package com.donmanueldev.plugins.nativephp_charts.ui

import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Rect

/** Numeric domain used to map logical values into plot coordinates. */
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

/**
 * One mark expressed in Canvas pixels, where the origin is top-left, x grows
 * rightward, and y grows downward. Optional shapes carry the exact geometry used
 * for drawing and hit testing so interaction does not approximate renderer marks.
 */
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

/**
 * Immutable output of the Cartesian layout pass.
 *
 * [plot], marks, labels, baselines, and annotations all share Canvas coordinates.
 * [domain] is the logical value-axis domain; [xDomain] is the optional numeric or
 * temporal logical x domain. Horizontal bars transpose those logical axes when
 * producing physical coordinates, while retaining their public x/y semantics.
 * Rendering, hit testing, and accessibility selection consume this same snapshot.
 */
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
