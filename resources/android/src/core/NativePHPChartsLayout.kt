package com.donmanueldev.plugins.nativephp_charts.ui

import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Rect
import androidx.compose.ui.unit.IntSize
import kotlin.math.abs
import kotlin.math.max
import kotlin.math.min

internal data class NativePHPChartsDomain(val minimum: Double, val maximum: Double) {
    val span: Double get() = maximum - minimum
}

internal data class NativePHPChartsSelectionIdentity(
    val seriesId: String,
    val pointId: String,
)

internal data class NativePHPChartsDatum(
    val series: NativePHPChartsSeries,
    val point: NativePHPChartsPoint,
    val center: Offset,
    val bar: Rect? = null,
    val areaBaseY: Float? = null,
) {
    val selectionIdentity = NativePHPChartsSelectionIdentity(series.id, point.id)
}

internal data class NativePHPChartsLayout(
    val plot: Rect,
    val domain: NativePHPChartsDomain,
    val baselineY: Float,
    val data: List<NativePHPChartsDatum>,
    val dataBySeries: Map<String, List<NativePHPChartsDatum>>,
    val xLabels: List<Pair<Float, String>>,
    val yLabels: List<Pair<Float, String>>,
    val hitIndex: NativePHPChartsHitIndex,
) {
    fun nearest(location: Offset, threshold: Float): NativePHPChartsDatum? {
        return hitIndex.nearest(plot, location, threshold)
    }
}

internal object NativePHPChartsLayoutEngine {
    fun build(
        configuration: NativePHPChartsConfiguration,
        formatting: NativePHPChartsFormatting,
        size: IntSize,
        density: Float,
    ): NativePHPChartsLayout {
        val left = 58f * density
        val right = max(left + density, size.width - (12f * density))
        val top = 14f * density
        val bottom = max(top + density, size.height - (34f * density))
        val plot = Rect(left, top, right, bottom)
        val values = when {
            configuration.kind == NativePHPChartsKind.Area && configuration.areaMode == "stacked" -> {
                stackedGeometryValues(configuration.series, formatting)
            }
            else -> configuration.series.flatMap { series -> series.points.map(NativePHPChartsPoint::value) }
        }
        val geometryRequiresZero = configuration.kind == NativePHPChartsKind.Area
        val domain = domain(values, configuration.beginAtZero || geometryRequiresZero)
        val baselineY = yFor(0.0, domain, plot).coerceIn(plot.top, plot.bottom)
        val categories = configuration.series.flatMap(NativePHPChartsSeries::points)
            .distinctBy { it.x?.toString() ?: it.label }
        val categoryIndexes = categories.mapIndexed { index, point ->
            (point.x?.toString() ?: point.label) to index
        }.toMap()
        val numericX = configuration.series.flatMap(NativePHPChartsSeries::points).mapNotNull(formatting::xNumeric)
        val xMinimum = numericX.minOrNull()
        val xMaximum = numericX.maxOrNull()
        val numericPadding = if (configuration.kind == NativePHPChartsKind.Bar) {
            numericX.distinct().sorted().zipWithNext { first, second -> second - first }
                .filter { it > 0.0 }
                .minOrNull()
                ?.div(2.0)
        } else {
            null
        }

        fun xFor(point: NativePHPChartsPoint): Float {
            val number = formatting.xNumeric(point)
            if (number != null && xMinimum != null && xMaximum != null && xMaximum != xMinimum) {
                val padding = numericPadding ?: 0.0
                val paddedMinimum = xMinimum - padding
                val paddedMaximum = xMaximum + padding
                return plot.left + (((number - paddedMinimum) / (paddedMaximum - paddedMinimum)).toFloat() * plot.width)
            }
            val index = categoryIndexes[point.x?.toString() ?: point.label] ?: 0
            if (configuration.kind == NativePHPChartsKind.Bar) {
                return plot.left + ((index + 0.5f) * plot.width / categories.size.coerceAtLeast(1))
            }

            return if (categories.size <= 1) plot.center.x else plot.left + (plot.width * index / (categories.size - 1))
        }

        val data = when (configuration.kind) {
            NativePHPChartsKind.Bar -> groupedBars(
                configuration,
                plot,
                domain,
                categoryIndexes,
                density,
                ::xFor,
            )
            NativePHPChartsKind.Area -> areaData(configuration, formatting, domain, plot, ::xFor)
            NativePHPChartsKind.Line, NativePHPChartsKind.Scatter -> configuration.series.flatMap { series ->
                series.points.map { point ->
                    NativePHPChartsDatum(series, point, Offset(xFor(point), yFor(point.value, domain, plot)))
                }
            }
        }
        val xLabelPoints = if (configuration.xAxis.type == NativePHPChartsXType.Category) {
            categories
        } else {
            configuration.series
                .flatMap(NativePHPChartsSeries::points)
                .mapNotNull { point -> formatting.xNumeric(point)?.let { value -> value to point } }
                .sortedBy { (value, _) -> value }
                .distinctBy { (value, _) -> value }
                .map { (_, point) -> point }
        }
        val desiredLabels = (configuration.style.axisLabelCount ?: configuration.xAxis.labelCount)
            .coerceIn(2, 12)
            .coerceAtMost(xLabelPoints.size.coerceAtLeast(1))
        val labelIndexes = evenlySpacedIndexes(xLabelPoints.size, desiredLabels)
        val labels = labelIndexes.map { index ->
            val point = xLabelPoints[index]
            xFor(point) to formatting.x(point)
        }
        val yLabelCount = (configuration.style.axisLabelCount ?: configuration.yAxis.labelCount).coerceIn(2, 12)
        val yLabels = (0 until yLabelCount).map { index ->
            val fraction = index.toFloat() / (yLabelCount - 1)
            val y = plot.top + (plot.height * fraction)
            val value = domain.maximum - (domain.span * fraction)
            y to formatting.value(value)
        }

        return NativePHPChartsLayout(
            plot = plot,
            domain = domain,
            baselineY = baselineY,
            data = data,
            dataBySeries = data.groupBy { it.series.id },
            xLabels = labels,
            yLabels = yLabels,
            hitIndex = NativePHPChartsHitIndex.build(data),
        )
    }

    private fun areaData(
        configuration: NativePHPChartsConfiguration,
        formatting: NativePHPChartsFormatting,
        domain: NativePHPChartsDomain,
        plot: Rect,
        xFor: (NativePHPChartsPoint) -> Float,
    ): List<NativePHPChartsDatum> {
        if (configuration.areaMode != "stacked") {
            val baseline = yFor(0.0, domain, plot)
            return configuration.series.flatMap { series ->
                series.points.map { point ->
                    NativePHPChartsDatum(
                        series = series,
                        point = point,
                        center = Offset(xFor(point), yFor(point.value, domain, plot)),
                        areaBaseY = baseline,
                    )
                }
            }
        }

        val positive = mutableMapOf<String, Double>()
        val negative = mutableMapOf<String, Double>()

        return configuration.series.flatMap { series ->
            series.points.map { point ->
                val key = formatting.geometryKey(point)
                val accumulator = if (point.value >= 0) positive else negative
                val start = accumulator[key] ?: 0.0
                val end = start + point.value
                accumulator[key] = end

                NativePHPChartsDatum(
                    series = series,
                    point = point,
                    center = Offset(xFor(point), yFor(end, domain, plot)),
                    areaBaseY = yFor(start, domain, plot),
                )
            }
        }
    }

    private fun groupedBars(
        configuration: NativePHPChartsConfiguration,
        plot: Rect,
        domain: NativePHPChartsDomain,
        categoryIndexes: Map<String, Int>,
        density: Float,
        xFor: (NativePHPChartsPoint) -> Float,
    ): List<NativePHPChartsDatum> {
        val centers = configuration.series.flatMap(NativePHPChartsSeries::points)
            .map(xFor)
            .distinct()
            .sorted()
        val groupWidth = centers.zipWithNext { first, second -> second - first }
            .minOrNull()
            ?: plot.width
        val innerWidth = groupWidth * 0.76f
        val computedWidth = (innerWidth / configuration.series.size.coerceAtLeast(1)).coerceAtLeast(1f * density)
        val barWidth = configuration.style.barWidth?.let { min(computedWidth, it * density) } ?: computedWidth
        val totalBarWidth = barWidth * configuration.series.size.coerceAtLeast(1)

        return configuration.series.flatMapIndexed { seriesIndex, series ->
            series.points.mapNotNull { point ->
                val key = point.x?.toString() ?: point.label
                if (categoryIndexes[key] == null) return@mapNotNull null
                val center = xFor(point)
                val left = center - (totalBarWidth / 2f) + (seriesIndex * barWidth)
                val valueY = yFor(point.value, domain, plot)
                val baseline = yFor(0.0, domain, plot).coerceIn(plot.top, plot.bottom)
                val rect = Rect(left, min(valueY, baseline), left + barWidth, max(valueY, baseline))
                NativePHPChartsDatum(series, point, rect.center, rect)
            }
        }
    }

    private fun domain(values: List<Double>, beginAtZero: Boolean): NativePHPChartsDomain {
        var minimum = values.minOrNull() ?: 0.0
        var maximum = values.maxOrNull() ?: 0.0
        if (beginAtZero) {
            minimum = min(minimum, 0.0)
            maximum = max(maximum, 0.0)
        }
        if (minimum == maximum) {
            val padding = if (minimum == 0.0) 1.0 else abs(minimum) * 0.1
            minimum -= padding
            maximum += padding
        } else {
            val padding = (maximum - minimum) * 0.08
            minimum -= padding
            maximum += padding
        }
        return NativePHPChartsDomain(minimum, maximum)
    }

    private fun yFor(value: Double, domain: NativePHPChartsDomain, plot: Rect): Float =
        plot.bottom - (((value - domain.minimum) / domain.span).toFloat() * plot.height)

    private fun stackedGeometryValues(
        series: List<NativePHPChartsSeries>,
        formatting: NativePHPChartsFormatting,
    ): List<Double> {
        val positive = mutableMapOf<String, Double>()
        val negative = mutableMapOf<String, Double>()
        val values = mutableListOf(0.0)

        series.flatMap(NativePHPChartsSeries::points).forEach { point ->
            val key = formatting.geometryKey(point)
            val totals = if (point.value >= 0) positive else negative
            val start = totals[key] ?: 0.0
            val end = start + point.value
            totals[key] = end
            values += start
            values += end
        }

        return values
    }

    private fun evenlySpacedIndexes(size: Int, count: Int): List<Int> {
        if (size <= 0) return emptyList()
        if (size <= count) return (0 until size).toList()
        return (0 until count).map { index -> (index * (size - 1).toFloat() / (count - 1)).toInt() }.distinct()
    }
}
