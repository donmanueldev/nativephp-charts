package com.donmanueldev.plugins.nativephp_charts.ui

import android.graphics.Paint
import android.graphics.Typeface
import androidx.compose.ui.geometry.CornerRadius
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.PathEffect
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.drawscope.DrawScope
import androidx.compose.ui.graphics.drawscope.Fill
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.graphics.nativeCanvas
import androidx.compose.ui.graphics.toArgb
import androidx.compose.ui.unit.dp
import com.nativephp.plugins.native_ui.ui.NativeUIFontResolver
import kotlin.math.max
import kotlin.math.min

internal data class NativePHPChartsDrawingResources(
    val axisLabelPaint: Paint,
    val tooltipPaint: Paint,
    val axisColor: Color,
    val axisLabelColor: Color,
    val gridColor: Color,
    val lineColors: Map<String, Color>,
    val pointColors: Map<String, Color>,
)

internal data class NativePHPChartsSeriesPaths(
    val data: List<NativePHPChartsDatum>,
    val line: Path,
    val area: Path?,
    val fillBetween: Path?,
    val fillTarget: List<NativePHPChartsDatum>?,
)

internal class NativePHPChartsPathCache private constructor(
    private val pathsBySeries: Map<String, NativePHPChartsSeriesPaths>,
) {
    operator fun get(seriesId: String): NativePHPChartsSeriesPaths? = pathsBySeries[seriesId]

    companion object {
        fun build(
            layout: NativePHPChartsLayout,
            configuration: NativePHPChartsConfiguration,
            includeArea: Boolean,
        ): NativePHPChartsPathCache {
            val seriesById = configuration.series.associateBy(NativePHPChartsSeries::id)
            val renderDataBySeries = layout.dataBySeries.mapValues { (_, data) ->
                nativePHPChartsCullToPlot(data, layout.plot)
            }

            return NativePHPChartsPathCache(
                renderDataBySeries.mapValues { (seriesId, data) ->
                    val series = requireNotNull(seriesById[seriesId])
                    val interpolation = series.style?.interpolation
                        ?: if (configuration.style.smooth) "smooth" else "linear"
                    val target = series.fillTo?.let(renderDataBySeries::get)
                    NativePHPChartsSeriesPaths(
                        data = data,
                        line = nativePHPChartsPath(data, interpolation, 1f, layout),
                        area = if (includeArea) nativePHPChartsAreaPath(data, interpolation, 1f, layout) else null,
                        fillBetween = target?.let { nativePHPChartsBetweenPath(data, it, interpolation, 1f, layout) },
                        fillTarget = target,
                    )
                },
            )
        }
    }
}

internal fun nativePHPChartsCullToPlot(
    data: List<NativePHPChartsDatum>,
    plot: androidx.compose.ui.geometry.Rect,
): List<NativePHPChartsDatum> {
    if (data.size <= 2) return data

    var firstRelevant = data.size
    var lastRelevant = -1

    fun include(index: Int) {
        firstRelevant = min(firstRelevant, index)
        lastRelevant = max(lastRelevant, index)
    }

    data.forEachIndexed { index, datum ->
        if (datum.center.x in plot.left..plot.right) {
            include(index)
        }
    }
    data.zipWithNext().forEachIndexed { index, (start, end) ->
        val segmentMinimum = min(start.center.x, end.center.x)
        val segmentMaximum = max(start.center.x, end.center.x)
        if (segmentMinimum <= plot.right && segmentMaximum >= plot.left) {
            include(index)
            include(index + 1)
        }
    }

    if (lastRelevant < 0) return emptyList()

    val start = max(firstRelevant - 1, 0)
    val endExclusive = min(lastRelevant + 2, data.size)
    return data.subList(start, endExclusive)
}

internal fun DrawScope.drawNativePHPChartsAxes(
    configuration: NativePHPChartsConfiguration,
    layout: NativePHPChartsLayout,
    resources: NativePHPChartsDrawingResources,
) {
    val axisColor = resources.axisColor
    val gridColor = resources.gridColor
    val legacyAxisVisible = if (configuration.kind == NativePHPChartsKind.Bar) configuration.showPoints else true
    val xAxisVisible = configuration.xAxis.visible ?: configuration.style.axisVisible ?: legacyAxisVisible
    val yAxisVisible = configuration.yAxis.visible ?: configuration.style.axisVisible ?: legacyAxisVisible
    val isHorizontalBar = configuration.kind == NativePHPChartsKind.Bar && configuration.barOrientation == "horizontal"
    val verticalAxisVisible = if (isHorizontalBar) xAxisVisible else yAxisVisible
    val horizontalAxisVisible = if (isHorizontalBar) yAxisVisible else xAxisVisible
    val labelPaint = resources.axisLabelPaint
    labelPaint.color = resources.axisLabelColor.toArgb()
    layout.yLabels.forEach { (y, label) ->
        if (!isHorizontalBar && (configuration.style.gridVisible ?: configuration.showGrid)) {
            drawLine(gridColor, Offset(layout.plot.left, y), Offset(layout.plot.right, y), configuration.style.gridWidth.dp.toPx())
        }
        if (verticalAxisVisible) {
            labelPaint.textAlign = Paint.Align.RIGHT
            val availableWidth = (layout.plot.left - 20.dp.toPx()).coerceAtLeast(0f)
            val baseline = y - ((labelPaint.fontMetrics.ascent + labelPaint.fontMetrics.descent) / 2f)
            drawContext.canvas.nativeCanvas.drawText(
                ellipsizeNativePHPCharts(label, labelPaint, availableWidth),
                layout.plot.left - 8.dp.toPx(),
                baseline,
                labelPaint,
            )
        }
    }
    if (!isHorizontalBar && yAxisVisible && layout.baselineY in layout.plot.top..layout.plot.bottom) {
        drawLine(axisColor, Offset(layout.plot.left, layout.baselineY), Offset(layout.plot.right, layout.baselineY), 1.dp.toPx())
    }
    if (isHorizontalBar && yAxisVisible && layout.valueBaselineX != null && layout.valueBaselineX in layout.plot.left..layout.plot.right) {
        drawLine(axisColor, Offset(layout.valueBaselineX, layout.plot.top), Offset(layout.valueBaselineX, layout.plot.bottom), 1.dp.toPx())
    }
    if (xAxisVisible && layout.baselineX != null && layout.baselineX in layout.plot.left..layout.plot.right) {
        drawLine(axisColor, Offset(layout.baselineX, layout.plot.top), Offset(layout.baselineX, layout.plot.bottom), 1.dp.toPx())
    }
    if (horizontalAxisVisible) {
        labelPaint.textAlign = Paint.Align.CENTER
        val width = layout.plot.width / max(layout.xLabels.size, 1) - 6.dp.toPx()
        layout.xLabels.forEach { (x, label) ->
            if (isHorizontalBar && (configuration.style.gridVisible ?: configuration.showGrid)) {
                drawLine(gridColor, Offset(x, layout.plot.top), Offset(x, layout.plot.bottom), configuration.style.gridWidth.dp.toPx())
            }
            drawContext.canvas.nativeCanvas.drawText(
                ellipsizeNativePHPCharts(label, labelPaint, width),
                x,
                layout.plot.bottom + 8.dp.toPx() - labelPaint.fontMetrics.ascent,
                labelPaint,
            )
        }
        val title = if (isHorizontalBar) configuration.yAxis.title else configuration.xAxis.title
        title?.let {
            drawContext.canvas.nativeCanvas.drawText(
                it,
                layout.plot.center.x,
                size.height - 6.dp.toPx(),
                labelPaint,
            )
        }
    }
    if (verticalAxisVisible) {
        val title = if (isHorizontalBar) configuration.xAxis.title else configuration.yAxis.title
        title?.let {
            val canvas = drawContext.canvas.nativeCanvas
            val centerY = layout.plot.center.y
            canvas.save()
            canvas.rotate(-90f, 8.dp.toPx(), centerY)
            labelPaint.textAlign = Paint.Align.CENTER
            canvas.drawText(it, 8.dp.toPx(), centerY - labelPaint.fontMetrics.ascent / 2f, labelPaint)
            canvas.restore()
        }
    }
}

internal fun DrawScope.drawNativePHPChartsAnnotations(
    layout: NativePHPChartsLayout,
    resources: NativePHPChartsDrawingResources,
) {
    layout.annotations.forEach { geometry ->
        val annotation = geometry.annotation
        if (annotation.type == "band") {
            if (geometry.physicalAxis == "x") {
                drawRect(
                    annotation.color.copy(alpha = annotation.opacity),
                    topLeft = Offset(geometry.start, layout.plot.top),
                    size = androidx.compose.ui.geometry.Size(geometry.end - geometry.start, layout.plot.height),
                )
            } else {
                drawRect(
                    annotation.color.copy(alpha = annotation.opacity),
                    topLeft = Offset(layout.plot.left, geometry.start),
                    size = androidx.compose.ui.geometry.Size(layout.plot.width, geometry.end - geometry.start),
                )
            }
            return@forEach
        }

        if (geometry.physicalAxis == "x") {
            drawLine(
                annotation.color,
                Offset(geometry.start, layout.plot.top),
                Offset(geometry.start, layout.plot.bottom),
                annotation.width.dp.toPx(),
            )
        } else {
            drawLine(
                annotation.color,
                Offset(layout.plot.left, geometry.start),
                Offset(layout.plot.right, geometry.start),
                annotation.width.dp.toPx(),
            )
        }

        annotation.label?.let { label ->
            val paint = resources.axisLabelPaint
            paint.color = annotation.color.toArgb()
            paint.textAlign = Paint.Align.RIGHT
            val x = if (geometry.physicalAxis == "x") geometry.start - 4.dp.toPx() else layout.plot.right
            val y = if (geometry.physicalAxis == "x") layout.plot.top - paint.fontMetrics.descent else geometry.start - 4.dp.toPx()
            drawContext.canvas.nativeCanvas.drawText(label, x, y, paint)
        }
    }
}

internal fun DrawScope.drawNativePHPChartsLines(
    configuration: NativePHPChartsConfiguration,
    layout: NativePHPChartsLayout,
    progress: Float,
    area: Boolean,
    resources: NativePHPChartsDrawingResources,
    pathCache: NativePHPChartsPathCache,
) {
    configuration.series.forEach { series ->
        val cachedPaths = pathCache[series.id]
        val data = cachedPaths?.data.orEmpty()
        if (data.isEmpty()) return@forEach
        val path = if (progress == 1f) cachedPaths?.line else null
        val lineColor = resources.lineColors.getValue(series.id)
        val interpolation = series.style?.interpolation
            ?: if (configuration.style.smooth) "smooth" else "linear"
        val fillBetween = if (progress == 1f) {
            cachedPaths?.fillBetween
        } else {
            cachedPaths?.fillTarget
                ?.let { target -> nativePHPChartsBetweenPath(data, target, interpolation, progress, layout) }
        }
        fillBetween?.let { fill ->
            drawPath(
                path = fill,
                color = lineColor.copy(alpha = series.style?.areaOpacity ?: configuration.style.areaOpacity),
                style = Fill,
            )
        }
        if (area) {
            val fill = if (progress == 1f) cachedPaths?.area else null
            val resolvedFill = fill ?: nativePHPChartsAreaPath(data, interpolation, progress, layout)
            if (series.style?.areaGradient ?: configuration.style.areaGradient) {
                val opacity = series.style?.areaOpacity ?: configuration.style.areaOpacity
                drawPath(
                    path = resolvedFill,
                    brush = Brush.verticalGradient(
                        colors = listOf(
                            lineColor.copy(alpha = opacity * 0.9f),
                            lineColor.copy(alpha = opacity * 0.16f),
                        ),
                        startY = layout.plot.top,
                        endY = layout.plot.bottom,
                    ),
                    style = Fill,
                )
            } else {
                drawPath(
                    resolvedFill,
                    lineColor.copy(alpha = series.style?.areaOpacity ?: configuration.style.areaOpacity),
                    style = Fill,
                )
            }
        }
        val resolvedPath = path ?: nativePHPChartsPath(data, interpolation, progress, layout)
        val width = (series.style?.lineWidth ?: configuration.style.lineWidth).dp.toPx()
        val dash = series.style?.dash?.map { it.dp.toPx() }?.toFloatArray()
        drawPath(
            resolvedPath,
            lineColor,
            style = Stroke(
                width = width,
                cap = StrokeCap.Round,
                pathEffect = dash?.let(PathEffect::dashPathEffect),
            ),
        )
        if ((series.style?.pointsVisible ?: configuration.style.pointsVisible ?: configuration.showPoints) || data.size == 1) {
            val pointColor = resources.pointColors.getValue(series.id)
            data.forEach { datum ->
                drawCircle(
                    pointColor,
                    (series.style?.pointSize ?: configuration.style.pointSize).dp.toPx() / 2f,
                    animatedPoint(datum, progress, layout, useAreaBase = false),
                )
            }
        }
        data.forEach { datum -> drawNativePHPChartsErrorRange(datum, lineColor) }
    }
}

internal fun DrawScope.drawNativePHPChartsBars(
    configuration: NativePHPChartsConfiguration,
    layout: NativePHPChartsLayout,
    progress: Float,
) {
    layout.data.forEach { datum ->
        val finalRect = datum.bar ?: return@forEach
        val isHorizontal = configuration.barOrientation == "horizontal"
        val baselineX = layout.valueBaselineX ?: layout.plot.left
        val animatedLeft = if (isHorizontal) baselineX + ((finalRect.left - baselineX) * progress) else finalRect.left
        val animatedRight = if (isHorizontal) baselineX + ((finalRect.right - baselineX) * progress) else finalRect.right
        val animatedTop = if (isHorizontal) finalRect.top else layout.baselineY + ((finalRect.top - layout.baselineY) * progress)
        val animatedBottom = if (isHorizontal) finalRect.bottom else layout.baselineY + ((finalRect.bottom - layout.baselineY) * progress)
        val left = min(animatedLeft, animatedRight)
        val right = max(animatedLeft, animatedRight)
        val top = min(animatedTop, animatedBottom)
        val bottom = max(animatedTop, animatedBottom)
        drawRoundRect(
            datum.series.color,
            topLeft = Offset(left, top),
            size = androidx.compose.ui.geometry.Size(max(right - left, 1f), max(bottom - top, 1f)),
            cornerRadius = CornerRadius((datum.series.style?.barRadius ?: configuration.style.barRadius).dp.toPx()),
        )
        drawNativePHPChartsErrorRange(datum, datum.series.color)
    }
}

internal fun DrawScope.drawNativePHPChartsScatter(
    configuration: NativePHPChartsConfiguration,
    layout: NativePHPChartsLayout,
    progress: Float,
    resources: NativePHPChartsDrawingResources,
) {
    layout.data.forEach { datum ->
        drawCircle(
            color = resources.pointColors.getValue(datum.series.id),
            radius = (datum.series.style?.pointSize ?: configuration.style.pointSize).dp.toPx() / 2f,
            center = animatedPoint(datum, progress, layout, useAreaBase = false),
        )
    }
    layout.data.forEach { datum ->
        drawNativePHPChartsErrorRange(datum, resources.pointColors.getValue(datum.series.id))
    }
}

internal fun DrawScope.drawNativePHPChartsCandlesticks(
    configuration: NativePHPChartsConfiguration,
    layout: NativePHPChartsLayout,
    progress: Float,
) {
    val centers = layout.data.map { it.center.x }.distinct().sorted()
    val slot = centers.zipWithNext { first, second -> second - first }.minOrNull() ?: layout.plot.width
    val bodyWidth = configuration.style.barWidth?.dp?.toPx()?.coerceAtMost(slot * 0.72f) ?: slot * 0.62f

    fun y(value: Double): Float = layout.plot.bottom -
        (((value - layout.domain.minimum) / layout.domain.span).toFloat() * layout.plot.height)

    layout.data.forEach { datum ->
        val open = datum.point.open ?: return@forEach
        val high = datum.point.high ?: return@forEach
        val low = datum.point.low ?: return@forEach
        val close = datum.point.close ?: return@forEach
        val color = if (close >= open) Color(0xFF16A35B) else Color(0xFFDB2E38)
        val animatedOpen = layout.domain.minimum + ((open - layout.domain.minimum) * progress)
        val animatedClose = layout.domain.minimum + ((close - layout.domain.minimum) * progress)
        val animatedHigh = layout.domain.minimum + ((high - layout.domain.minimum) * progress)
        val animatedLow = layout.domain.minimum + ((low - layout.domain.minimum) * progress)
        drawLine(color, Offset(datum.center.x, y(animatedHigh)), Offset(datum.center.x, y(animatedLow)), 1.5.dp.toPx())
        val top = min(y(animatedOpen), y(animatedClose))
        val bottom = max(y(animatedOpen), y(animatedClose))
        drawRoundRect(
            color,
            topLeft = Offset(datum.center.x - bodyWidth / 2f, top),
            size = androidx.compose.ui.geometry.Size(bodyWidth, max(bottom - top, 1.5.dp.toPx())),
            cornerRadius = CornerRadius(configuration.style.barRadius.dp.toPx()),
        )
    }
}

private fun DrawScope.drawNativePHPChartsErrorRange(datum: NativePHPChartsDatum, color: Color) {
    if (datum.errorMinX != null && datum.errorMaxX != null) {
        val left = min(datum.errorMinX, datum.errorMaxX)
        val right = max(datum.errorMinX, datum.errorMaxX)
        val cap = 4.dp.toPx()
        drawLine(color, Offset(left, datum.center.y), Offset(right, datum.center.y), 1.25.dp.toPx())
        drawLine(color, Offset(left, datum.center.y - cap), Offset(left, datum.center.y + cap), 1.25.dp.toPx())
        drawLine(color, Offset(right, datum.center.y - cap), Offset(right, datum.center.y + cap), 1.25.dp.toPx())
        return
    }

    val minimum = datum.errorMinY ?: return
    val maximum = datum.errorMaxY ?: return
    val top = min(minimum, maximum)
    val bottom = max(minimum, maximum)
    val cap = 4.dp.toPx()
    drawLine(color, Offset(datum.center.x, top), Offset(datum.center.x, bottom), 1.25.dp.toPx())
    drawLine(color, Offset(datum.center.x - cap, top), Offset(datum.center.x + cap, top), 1.25.dp.toPx())
    drawLine(color, Offset(datum.center.x - cap, bottom), Offset(datum.center.x + cap, bottom), 1.25.dp.toPx())
}

internal fun DrawScope.drawNativePHPChartsSelectionOverlay(
    datum: NativePHPChartsDatum,
    selectedData: List<NativePHPChartsDatum>,
    interaction: NativePHPChartsInteraction,
    layout: NativePHPChartsLayout,
    resources: NativePHPChartsDrawingResources,
) {
    val horizontalBar = layout.valueBaselineX != null
    val showLogicalX = interaction.crosshair == "x" || interaction.crosshair == "both"
    val showLogicalY = interaction.crosshair == "y" || interaction.crosshair == "both"
    if (showLogicalX && horizontalBar || showLogicalY && !horizontalBar) {
        drawLine(
            Color.Gray.copy(alpha = 0.5f),
            Offset(layout.plot.left, datum.center.y),
            Offset(layout.plot.right, datum.center.y),
            1.dp.toPx(),
        )
    }
    if (showLogicalX && !horizontalBar || showLogicalY && horizontalBar) {
        drawLine(
            Color.Gray.copy(alpha = 0.5f),
            Offset(datum.center.x, layout.plot.top),
            Offset(datum.center.x, layout.plot.bottom),
            1.dp.toPx(),
        )
    }
    selectedData.forEach { selected ->
        drawCircle(Color.White, 6.dp.toPx(), selected.center)
        drawCircle(resources.pointColors.getValue(selected.series.id), 4.dp.toPx(), selected.center)
    }
}

internal fun DrawScope.drawNativePHPChartsTooltip(
    datum: NativePHPChartsDatum,
    selectedData: List<NativePHPChartsDatum>,
    interaction: NativePHPChartsInteraction,
    formatting: NativePHPChartsFormatting,
    layout: NativePHPChartsLayout,
    resources: NativePHPChartsDrawingResources,
) {
    val lines = if (interaction.tooltip == "shared") {
        listOf(formatting.x(datum.point)) + selectedData.map { selected ->
            "${selected.series.name} · ${formatting.value(selected.point.value)}"
        }
    } else {
        listOf("${datum.point.label} · ${formatting.value(datum.point.value)}")
    }
    val paint = resources.tooltipPaint
    val availableWidth = layout.plot.width.coerceAtLeast(1f)
    val horizontalPadding = 18.dp.toPx()
    val verticalPadding = 7.dp.toPx()
    val displayLines = lines.map { ellipsizeNativePHPCharts(it, paint, max(availableWidth - horizontalPadding, 0f)) }
    val width = min((displayLines.maxOfOrNull(paint::measureText) ?: 0f) + horizontalPadding, availableWidth)
    val fontMetrics = paint.fontMetrics
    val lineHeight = fontMetrics.descent - fontMetrics.ascent
    val height = (lineHeight * displayLines.size) + (verticalPadding * 2)
    val centerX = datum.center.x.coerceIn(layout.plot.left + width / 2, layout.plot.right - width / 2)
    val bottom = (datum.center.y - 12.dp.toPx()).coerceAtLeast(layout.plot.top + height)
    drawRoundRect(Color.Black.copy(alpha = 0.84f), Offset(centerX - width / 2, bottom - height), androidx.compose.ui.geometry.Size(width, height), CornerRadius(height / 2))
    displayLines.forEachIndexed { index, text ->
        val baseline = bottom - verticalPadding - fontMetrics.bottom - (lineHeight * (displayLines.lastIndex - index))
        drawContext.canvas.nativeCanvas.drawText(text, centerX, baseline, paint)
    }
}

private fun nativePHPChartsPath(
    data: List<NativePHPChartsDatum>,
    interpolation: String,
    progress: Float,
    layout: NativePHPChartsLayout,
): Path = Path().apply {
    appendNativePHPChartsPath(data, interpolation, progress, layout, useAreaBase = false, reversed = false, move = true)
}

private fun nativePHPChartsAreaPath(
    data: List<NativePHPChartsDatum>,
    interpolation: String,
    progress: Float,
    layout: NativePHPChartsLayout,
): Path = Path().apply {
    appendNativePHPChartsPath(data, interpolation, progress, layout, useAreaBase = false, reversed = false, move = true)
    appendNativePHPChartsPath(data, interpolation, progress, layout, useAreaBase = true, reversed = true, move = false)
    close()
}

private fun nativePHPChartsBetweenPath(
    data: List<NativePHPChartsDatum>,
    target: List<NativePHPChartsDatum>,
    interpolation: String,
    progress: Float,
    layout: NativePHPChartsLayout,
): Path = Path().apply {
    val targetByX = target.associateBy { it.center.x }
    val paired = data.filter { targetByX.containsKey(it.center.x) }
    val targetData = paired.mapNotNull { targetByX[it.center.x] }
    appendNativePHPChartsPath(paired, interpolation, progress, layout, false, false, true)
    appendNativePHPChartsPath(targetData, interpolation, progress, layout, false, true, false)
    close()
}

private fun Path.appendNativePHPChartsPath(
    data: List<NativePHPChartsDatum>,
    interpolation: String,
    progress: Float,
    layout: NativePHPChartsLayout,
    useAreaBase: Boolean,
    reversed: Boolean,
    move: Boolean,
) {
    if (data.isEmpty()) return

    fun point(index: Int): Offset {
        val dataIndex = if (reversed) data.lastIndex - index else index
        return animatedPoint(data[dataIndex], progress, layout, useAreaBase)
    }

    val first = point(0)
    if (move) moveTo(first.x, first.y) else lineTo(first.x, first.y)

    if (interpolation == "step_before" || interpolation == "step_after") {
        for (index in 1 until data.size) {
            val previous = point(index - 1)
            val next = point(index)
            if (interpolation == "step_before") {
                lineTo(previous.x, next.y)
            } else {
                lineTo(next.x, previous.y)
            }
            lineTo(next.x, next.y)
        }
        return
    }

    if (interpolation != "smooth" || data.size < 3) {
        for (index in 1 until data.size) {
            val next = point(index)
            lineTo(next.x, next.y)
        }
        return
    }

    for (index in 0 until data.lastIndex) {
        val p0 = point(max(index - 1, 0))
        val p1 = point(index)
        val p2 = point(index + 1)
        val p3 = point(min(index + 2, data.lastIndex))
        cubicTo(
            p1.x + (p2.x - p0.x) / 6f,
            p1.y + (p2.y - p0.y) / 6f,
            p2.x - (p3.x - p1.x) / 6f,
            p2.y - (p3.y - p1.y) / 6f,
            p2.x,
            p2.y,
        )
    }
}

private fun animatedPoint(
    datum: NativePHPChartsDatum,
    progress: Float,
    layout: NativePHPChartsLayout,
    useAreaBase: Boolean,
): Offset {
    val targetY = if (useAreaBase) datum.areaBaseY ?: layout.baselineY else datum.center.y
    return Offset(
        datum.center.x,
        layout.baselineY + ((targetY - layout.baselineY) * progress),
    )
}

private fun ellipsizeNativePHPCharts(value: String, paint: Paint, width: Float): String {
    if (paint.measureText(value) <= width) return value
    val count = paint.breakText(value, true, max(width - paint.measureText("…"), 0f), null)
    return value.take(count) + "…"
}

internal fun resolveNativePHPChartsTypeface(context: android.content.Context, token: String?): Typeface? {
    if (token.isNullOrBlank() || token == "System") return null
    val name = NativeUIFontResolver.aliases[token] ?: token
    for (extension in listOf("ttf", "otf", "ttc")) {
        runCatching { Typeface.createFromAsset(context.assets, "fonts/$name.$extension") }.getOrNull()?.let { return it }
    }
    return Typeface.create(name, Typeface.NORMAL)
}
