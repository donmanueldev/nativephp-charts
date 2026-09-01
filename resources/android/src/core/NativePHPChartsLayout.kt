package com.donmanueldev.plugins.nativephp_charts.ui

import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Rect
import androidx.compose.ui.unit.IntSize
import kotlin.math.abs
import kotlin.math.max
import kotlin.math.min

internal object NativePHPChartsLayoutEngine {
    fun build(
        configuration: NativePHPChartsConfiguration,
        formatting: NativePHPChartsFormatting,
        size: IntSize,
        density: Float,
        measureAxisLabel: (String) -> Float,
        axisLabelHeight: Float,
        viewportOverride: NativePHPChartsDomain? = null,
    ): NativePHPChartsLayout {
        val values = when {
            configuration.kind == NativePHPChartsKind.Area && configuration.areaMode == "stacked" ||
                configuration.kind == NativePHPChartsKind.Bar && configuration.barMode == "stacked" -> {
                stackedGeometryValues(configuration.series, formatting)
            }
            else -> configuration.series.flatMap { series ->
                series.points.flatMap { point -> listOfNotNull(point.value, point.errorMin, point.errorMax) }
            }
        }
        val geometryRequiresZero = configuration.kind == NativePHPChartsKind.Area
        val automaticDomain = domain(values, configuration.beginAtZero || geometryRequiresZero)
        val domain = explicitDomain(
            automaticDomain,
            configuration.yAxis.minimum,
            configuration.yAxis.maximum,
        )
        val legacyAxisVisible = if (configuration.kind == NativePHPChartsKind.Bar) configuration.showPoints else true
        val xAxisVisible = configuration.xAxis.visible ?: configuration.style.axisVisible ?: legacyAxisVisible
        val yAxisVisible = configuration.yAxis.visible ?: configuration.style.axisVisible ?: legacyAxisVisible
        val isHorizontalBar = configuration.kind == NativePHPChartsKind.Bar && configuration.barOrientation == "horizontal"
        val categories = configuration.series.flatMap(NativePHPChartsSeries::points)
            .distinctBy { it.x?.toString() ?: it.label }
        val categoryIndexes = categories.mapIndexed { index, point ->
            (point.x?.toString() ?: point.label) to index
        }.toMap()
        val yLabelCount = configuration.yAxis.labelCount.coerceIn(2, 12)
        val yLabelValues = configuration.yAxis.interval?.let { interval ->
            ticks(domain, interval).asReversed()
        } ?: (0 until yLabelCount).map { index ->
            val fraction = index.toDouble() / (yLabelCount - 1)
            domain.maximum - (domain.span * fraction)
        }
        val verticalAxisVisible = if (isHorizontalBar) xAxisVisible else yAxisVisible
        val horizontalAxisVisible = if (isHorizontalBar) yAxisVisible else xAxisVisible
        val measuredYLabelWidth = if (verticalAxisVisible) {
            if (isHorizontalBar) {
                categories.maxOfOrNull { measureAxisLabel(formatting.x(it)) } ?: 0f
            } else {
                yLabelValues.maxOfOrNull { measureAxisLabel(formatting.value(it)) } ?: 0f
            }
        } else {
            0f
        }
        val yLabelWidth = min(measuredYLabelWidth, size.width.coerceAtLeast(0) * 0.45f)
        val horizontalPadding = 12f * density
        val labelSpacing = 8f * density
        val verticalTitle = if (isHorizontalBar) configuration.xAxis.title else configuration.yAxis.title
        val horizontalTitle = if (isHorizontalBar) configuration.yAxis.title else configuration.xAxis.title
        val yTitleWidth = if (verticalAxisVisible && verticalTitle != null) axisLabelHeight + labelSpacing else 0f
        val left = horizontalPadding + yTitleWidth + if (verticalAxisVisible) yLabelWidth + labelSpacing else 0f
        val right = max(left + density, size.width - horizontalPadding)
        val top = (12f * density) + if (verticalAxisVisible) axisLabelHeight / 2f else 0f
        val bottomPadding = if (horizontalAxisVisible) {
            (20f * density) + axisLabelHeight +
                if (horizontalTitle != null) axisLabelHeight + labelSpacing else 0f
        } else {
            max(12f * density, axisLabelHeight / 2f)
        }
        val bottom = max(top + density, size.height - bottomPadding)
        val plot = Rect(left, top, right, bottom)
        val baselineValue = configuration.yAxis.baseline ?: 0.0
        val baselineY = yFor(baselineValue, domain, plot).coerceIn(plot.top, plot.bottom)
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
        val automaticXDomain = if (xMinimum != null && xMaximum != null) {
            val padding = numericPadding ?: if (xMinimum == xMaximum) {
                max(abs(xMinimum) * 0.05, 1.0)
            } else {
                (xMaximum - xMinimum) * 0.05
            }
            NativePHPChartsDomain(xMinimum - padding, xMaximum + padding)
        } else {
            null
        }
        val fullXDomain = automaticXDomain?.let {
            explicitDomain(
                it,
                formatting.xNumeric(configuration.xAxis.minimum),
                formatting.xNumeric(configuration.xAxis.maximum),
            )
        }
        val xDomain = viewportOverride ?: fullXDomain

        fun xFor(point: NativePHPChartsPoint): Float {
            val number = formatting.xNumeric(point)
            if (number != null && xDomain != null && xDomain.span > 0.0) {
                return plot.left + (((number - xDomain.minimum) / xDomain.span).toFloat() * plot.width)
            }
            val index = categoryIndexes[point.x?.toString() ?: point.label] ?: 0
            if (configuration.kind == NativePHPChartsKind.Bar) {
                return plot.left + ((index + 0.5f) * plot.width / categories.size.coerceAtLeast(1))
            }

            return if (categories.size <= 1) plot.center.x else plot.left + (plot.width * index / (categories.size - 1))
        }

        fun categoryY(point: NativePHPChartsPoint): Float {
            val number = formatting.xNumeric(point)
            if (number != null && xDomain != null && xDomain.span > 0.0) {
                return plot.top + (((number - xDomain.minimum) / xDomain.span).toFloat() * plot.height)
            }

            val index = categoryIndexes[point.x?.toString() ?: point.label] ?: 0
            return plot.top + ((index + 0.5f) * plot.height / categories.size.coerceAtLeast(1))
        }

        fun valueX(value: Double): Float =
            plot.left + (((value - domain.minimum) / domain.span).toFloat() * plot.width)

        val data = when (configuration.kind) {
            NativePHPChartsKind.Bar -> if (isHorizontalBar && configuration.barMode == "stacked") {
                stackedHorizontalBars(configuration, formatting, plot, density, ::categoryY, ::valueX)
            } else if (isHorizontalBar) {
                groupedHorizontalBars(configuration, plot, categoryIndexes, density, ::categoryY, ::valueX)
            } else if (configuration.barMode == "stacked") {
                stackedBars(configuration, formatting, plot, domain, density, ::xFor)
            } else {
                groupedBars(
                    configuration,
                    plot,
                    domain,
                    categoryIndexes,
                    density,
                    ::xFor,
                )
            }
            NativePHPChartsKind.Area -> areaData(configuration, formatting, domain, plot, ::xFor)
            NativePHPChartsKind.Candlestick -> candlestickData(
                configuration = configuration,
                plot = plot,
                domain = domain,
                density = density,
                xFor = ::xFor,
            )
            NativePHPChartsKind.Line, NativePHPChartsKind.Scatter -> configuration.series.flatMap { series ->
                series.points.map { point ->
                    NativePHPChartsDatum(series, point, Offset(xFor(point), yFor(point.value, domain, plot)))
                }
            }
        }.map { datum ->
            if (isHorizontalBar) {
                datum.copy(
                    errorMinX = datum.point.errorMin?.let(::valueX),
                    errorMaxX = datum.point.errorMax?.let(::valueX),
                )
            } else {
                datum.copy(
                    errorMinY = datum.point.errorMin?.let { yFor(it, domain, plot) },
                    errorMaxY = datum.point.errorMax?.let { yFor(it, domain, plot) },
                )
            }
        }
        val xLabelPoints = if (configuration.xAxis.type == NativePHPChartsXType.Category) {
            categories
        } else {
            configuration.series
                .flatMap(NativePHPChartsSeries::points)
                .mapNotNull { point -> formatting.xNumeric(point)?.let { value -> value to point } }
                .filter { (value, _) -> xDomain == null || value in xDomain.minimum..xDomain.maximum }
                .sortedBy { (value, _) -> value }
                .distinctBy { (value, _) -> value }
                .map { (_, point) -> point }
        }
        val desiredLabels = configuration.xAxis.labelCount
            .coerceIn(2, 12)
            .coerceAtMost(xLabelPoints.size.coerceAtLeast(1))
        val labelIndexes = evenlySpacedIndexes(xLabelPoints.size, desiredLabels)
        val labels = if (isHorizontalBar) {
            yLabelValues.asReversed().map { value -> valueX(value) to formatting.value(value) }
        } else if (configuration.xAxis.type != NativePHPChartsXType.Category && xDomain != null && configuration.xAxis.interval != null) {
            val interval = if (configuration.xAxis.type == NativePHPChartsXType.Date) {
                configuration.xAxis.interval * 86_400.0
            } else {
                configuration.xAxis.interval
            }
            ticks(xDomain, interval).map { value ->
                xFor(value, xDomain, plot) to formatting.x(value)
            }
        } else {
            labelIndexes.map { index ->
                val point = xLabelPoints[index]
                xFor(point) to formatting.x(point)
            }
        }
        val yLabels = if (isHorizontalBar) {
            val categoryLabelCount = configuration.xAxis.labelCount.coerceIn(2, 12)
            val visibleCategories = if (configuration.xAxis.type == NativePHPChartsXType.Category || xDomain == null) {
                categories
            } else {
                categories.filter { point ->
                    formatting.xNumeric(point)?.let { value -> value in xDomain.minimum..xDomain.maximum } == true
                }
            }
            evenlySpacedIndexes(visibleCategories.size, categoryLabelCount).map { index ->
                categoryY(visibleCategories[index]) to formatting.x(visibleCategories[index])
            }
        } else {
            yLabelValues.map { value -> yFor(value, domain, plot) to formatting.value(value) }
        }
        val baselineX = configuration.xAxis.baseline.takeUnless { isHorizontalBar }
            ?.let(formatting::xNumeric)
            ?.let { value -> xDomain?.let { domain -> xFor(value, domain, plot) } }
        val valueBaselineX = if (isHorizontalBar) valueX(configuration.yAxis.baseline ?: 0.0) else null
        fun annotationPosition(annotation: NativePHPChartsAnnotation, value: Any?): Float? {
            if (annotation.axis == "y") {
                return (value as? Number)?.toDouble()?.let { number ->
                    if (isHorizontalBar) valueX(number) else yFor(number, domain, plot)
                }
            }

            if (isHorizontalBar) {
                val target = if (configuration.xAxis.type == NativePHPChartsXType.Category) {
                    categories.firstOrNull { (it.x?.toString() ?: it.label) == value?.toString() }
                } else {
                    val numericValue = formatting.xNumeric(value)
                    categories.firstOrNull { formatting.xNumeric(it) == numericValue }
                }
                return target?.let(::categoryY)
            }

            if (configuration.xAxis.type == NativePHPChartsXType.Category) {
                val point = categories.firstOrNull { (it.x?.toString() ?: it.label) == value?.toString() }
                return point?.let { if (isHorizontalBar) categoryY(it) else xFor(it) }
            }

            val number = formatting.xNumeric(value) ?: return null
            return xDomain?.let { xFor(number, it, plot) }
        }
        val annotationGeometry = configuration.annotations.mapNotNull { annotation ->
            val firstValue = if (annotation.type == "band") annotation.from else annotation.value
            val start = annotationPosition(annotation, firstValue) ?: return@mapNotNull null
            val end = if (annotation.type == "band") {
                annotationPosition(annotation, annotation.to) ?: return@mapNotNull null
            } else {
                start
            }
            val physicalAxis = if (isHorizontalBar) {
                if (annotation.axis == "x") "y" else "x"
            } else {
                annotation.axis
            }
            NativePHPChartsAnnotationGeometry(annotation, physicalAxis, min(start, end), max(start, end))
        }

        return NativePHPChartsLayout(
            plot = plot,
            domain = domain,
            xDomain = xDomain,
            baselineX = baselineX,
            valueBaselineX = valueBaselineX,
            baselineY = baselineY,
            data = data,
            dataBySeries = data.groupBy { it.series.id },
            xLabels = labels,
            yLabels = yLabels,
            annotations = annotationGeometry,
            hitIndex = NativePHPChartsHitIndex.build(data),
        )
    }

    private fun candlestickData(
        configuration: NativePHPChartsConfiguration,
        plot: Rect,
        domain: NativePHPChartsDomain,
        density: Float,
        xFor: (NativePHPChartsPoint) -> Float,
    ): List<NativePHPChartsDatum> {
        val centers = configuration.series.flatMap(NativePHPChartsSeries::points)
            .map(xFor)
            .distinct()
            .sorted()
        val slot = centers.zipWithNext { first, second -> second - first }.minOrNull() ?: plot.width

        return configuration.series.flatMap { series ->
            val bodyWidth = nativePHPChartsCandlestickBodyWidth(
                configuredWidth = series.style?.barWidth ?: configuration.style.barWidth,
                density = density,
                slot = slot,
            )
            series.points.mapNotNull { point ->
                val open = point.open ?: return@mapNotNull null
                val high = point.high ?: return@mapNotNull null
                val low = point.low ?: return@mapNotNull null
                val close = point.close ?: return@mapNotNull null
                val x = xFor(point)
                val openY = yFor(open, domain, plot)
                val highY = yFor(high, domain, plot)
                val lowY = yFor(low, domain, plot)
                val closeY = yFor(close, domain, plot)
                val geometry = nativePHPChartsCandlestickGeometry(
                    x = x,
                    openY = openY,
                    highY = highY,
                    lowY = lowY,
                    closeY = closeY,
                    bodyWidth = bodyWidth,
                    density = density,
                )
                NativePHPChartsDatum(
                    series = series,
                    point = point,
                    center = geometry.anchor,
                    candlestick = geometry,
                )
            }
        }
    }

    private fun areaData(
        configuration: NativePHPChartsConfiguration,
        formatting: NativePHPChartsFormatting,
        domain: NativePHPChartsDomain,
        plot: Rect,
        xFor: (NativePHPChartsPoint) -> Float,
    ): List<NativePHPChartsDatum> {
        if (configuration.areaMode != "stacked") {
            val baseline = yFor(configuration.yAxis.baseline ?: 0.0, domain, plot)
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
        val barWidths = configuration.series.map { series ->
            (series.style?.barWidth ?: configuration.style.barWidth)
                ?.let { min(computedWidth, it * density) }
                ?: computedWidth
        }
        val totalBarWidth = barWidths.sum()
        val precedingWidths = barWidths.runningFold(0f, Float::plus).dropLast(1)

        return configuration.series.flatMapIndexed { seriesIndex, series ->
            series.points.mapNotNull { point ->
                val key = point.x?.toString() ?: point.label
                if (categoryIndexes[key] == null) return@mapNotNull null
                val center = xFor(point)
                val barWidth = barWidths[seriesIndex]
                val precedingWidth = precedingWidths[seriesIndex]
                val left = center - (totalBarWidth / 2f) + precedingWidth
                val valueY = yFor(point.value, domain, plot)
                val baseline = yFor(configuration.yAxis.baseline ?: 0.0, domain, plot).coerceIn(plot.top, plot.bottom)
                val rect = Rect(left, min(valueY, baseline), left + barWidth, max(valueY, baseline))
                NativePHPChartsDatum(series, point, rect.center, rect)
            }
        }
    }

    private fun stackedBars(
        configuration: NativePHPChartsConfiguration,
        formatting: NativePHPChartsFormatting,
        plot: Rect,
        domain: NativePHPChartsDomain,
        density: Float,
        xFor: (NativePHPChartsPoint) -> Float,
    ): List<NativePHPChartsDatum> {
        val positive = mutableMapOf<String, Double>()
        val negative = mutableMapOf<String, Double>()
        val categoryCount = configuration.series.flatMap(NativePHPChartsSeries::points)
            .map(formatting::geometryKey)
            .distinct()
            .size
            .coerceAtLeast(1)
        val slot = plot.width / categoryCount

        return configuration.series.flatMap { series ->
            series.points.map { point ->
                val key = formatting.geometryKey(point)
                val accumulator = if (point.value >= 0) positive else negative
                val start = accumulator[key] ?: 0.0
                val end = start + point.value
                accumulator[key] = end
                val center = xFor(point)
                val width = (series.style?.barWidth ?: configuration.style.barWidth)
                    ?.let { min(slot * 0.76f, it * density) }
                    ?: slot * 0.76f
                val startY = yFor(start, domain, plot)
                val endY = yFor(end, domain, plot)
                val rect = Rect(center - width / 2f, min(startY, endY), center + width / 2f, max(startY, endY))
                NativePHPChartsDatum(series, point, rect.center, rect)
            }
        }
    }

    private fun groupedHorizontalBars(
        configuration: NativePHPChartsConfiguration,
        plot: Rect,
        categoryIndexes: Map<String, Int>,
        density: Float,
        categoryY: (NativePHPChartsPoint) -> Float,
        valueX: (Double) -> Float,
    ): List<NativePHPChartsDatum> {
        val centers = configuration.series.flatMap(NativePHPChartsSeries::points)
            .map(categoryY)
            .distinct()
            .sorted()
        val slot = centers.zipWithNext { first, second -> second - first }
            .minOrNull()
            ?: plot.height
        val innerHeight = slot * 0.76f
        val computedHeight = (innerHeight / configuration.series.size.coerceAtLeast(1)).coerceAtLeast(density)
        val barHeights = configuration.series.map { series ->
            (series.style?.barWidth ?: configuration.style.barWidth)
                ?.let { min(computedHeight, it * density) }
                ?: computedHeight
        }
        val totalHeight = barHeights.sum()
        val precedingHeights = barHeights.runningFold(0f, Float::plus).dropLast(1)
        val baseline = valueX(configuration.yAxis.baseline ?: 0.0).coerceIn(plot.left, plot.right)

        return configuration.series.flatMapIndexed { seriesIndex, series ->
            series.points.mapNotNull { point ->
                val key = point.x?.toString() ?: point.label
                if (categoryIndexes[key] == null) return@mapNotNull null
                val height = barHeights[seriesIndex]
                val top = categoryY(point) - totalHeight / 2f + precedingHeights[seriesIndex]
                val value = valueX(point.value)
                val rect = Rect(min(value, baseline), top, max(value, baseline), top + height)
                NativePHPChartsDatum(series, point, rect.center, rect)
            }
        }
    }

    private fun stackedHorizontalBars(
        configuration: NativePHPChartsConfiguration,
        formatting: NativePHPChartsFormatting,
        plot: Rect,
        density: Float,
        categoryY: (NativePHPChartsPoint) -> Float,
        valueX: (Double) -> Float,
    ): List<NativePHPChartsDatum> {
        val positive = mutableMapOf<String, Double>()
        val negative = mutableMapOf<String, Double>()
        val centers = configuration.series.flatMap(NativePHPChartsSeries::points)
            .map(categoryY)
            .distinct()
            .sorted()
        val slot = centers.zipWithNext { first, second -> second - first }
            .minOrNull()
            ?: plot.height

        return configuration.series.flatMap { series ->
            series.points.map { point ->
                val key = formatting.geometryKey(point)
                val accumulator = if (point.value >= 0) positive else negative
                val start = accumulator[key] ?: 0.0
                val end = start + point.value
                accumulator[key] = end
                val height = (series.style?.barWidth ?: configuration.style.barWidth)
                    ?.let { min(slot * 0.76f, it * density) }
                    ?: slot * 0.76f
                val center = categoryY(point)
                val startX = valueX(start)
                val endX = valueX(end)
                val rect = Rect(min(startX, endX), center - height / 2f, max(startX, endX), center + height / 2f)
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

    private fun explicitDomain(
        automatic: NativePHPChartsDomain,
        minimum: Double?,
        maximum: Double?,
    ): NativePHPChartsDomain {
        val lower = minimum ?: automatic.minimum
        val upper = maximum ?: automatic.maximum
        return if (lower < upper) NativePHPChartsDomain(lower, upper) else automatic
    }

    private fun ticks(domain: NativePHPChartsDomain, interval: Double): List<Double> {
        if (!interval.isFinite() || interval <= 0.0) return emptyList()

        val first = kotlin.math.ceil(domain.minimum / interval) * interval
        val count = kotlin.math.floor((domain.maximum - first) / interval).toInt() + 1
        if (count <= 0) return emptyList()

        return (0 until min(count, 1_000)).map { first + (it * interval) }
    }

    private fun xFor(value: Double, domain: NativePHPChartsDomain, plot: Rect): Float {
        return plot.left + (((value - domain.minimum) / domain.span).toFloat() * plot.width)
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
