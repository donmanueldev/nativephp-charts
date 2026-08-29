package com.donmanueldev.plugins.nativephp_charts.ui

import android.graphics.Paint
import android.graphics.Typeface
import androidx.compose.ui.geometry.CornerRadius
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Path
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
    val lineStroke: Stroke,
    val lineColors: Map<String, Color>,
    val pointColors: Map<String, Color>,
)

internal data class NativePHPChartsSeriesPaths(
    val line: Path,
    val area: Path?,
)

internal class NativePHPChartsPathCache private constructor(
    private val pathsBySeries: Map<String, NativePHPChartsSeriesPaths>,
) {
    operator fun get(seriesId: String): NativePHPChartsSeriesPaths? = pathsBySeries[seriesId]

    companion object {
        fun build(
            layout: NativePHPChartsLayout,
            smooth: Boolean,
            includeArea: Boolean,
        ): NativePHPChartsPathCache = NativePHPChartsPathCache(
            layout.dataBySeries.mapValues { (_, data) ->
                NativePHPChartsSeriesPaths(
                    line = nativePHPChartsPath(data, smooth, 1f, layout),
                    area = if (includeArea) nativePHPChartsAreaPath(data, smooth, 1f, layout) else null,
                )
            },
        )
    }
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
    val labelPaint = resources.axisLabelPaint
    labelPaint.color = resources.axisLabelColor.toArgb()
    layout.yLabels.forEach { (y, label) ->
        if (configuration.style.gridVisible ?: configuration.showGrid) {
            drawLine(gridColor, Offset(layout.plot.left, y), Offset(layout.plot.right, y), configuration.style.gridWidth.dp.toPx())
        }
        if (yAxisVisible) {
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
    if (yAxisVisible && layout.baselineY in layout.plot.top..layout.plot.bottom) {
        drawLine(axisColor, Offset(layout.plot.left, layout.baselineY), Offset(layout.plot.right, layout.baselineY), 1.dp.toPx())
    }
    if (xAxisVisible) {
        labelPaint.textAlign = Paint.Align.CENTER
        val width = layout.plot.width / max(layout.xLabels.size, 1) - 6.dp.toPx()
        layout.xLabels.forEach { (x, label) ->
            drawContext.canvas.nativeCanvas.drawText(
                ellipsizeNativePHPCharts(label, labelPaint, width),
                x,
                layout.plot.bottom + 8.dp.toPx() - labelPaint.fontMetrics.ascent,
                labelPaint,
            )
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
        val data = layout.dataBySeries[series.id].orEmpty()
        if (data.isEmpty()) return@forEach
        val cachedPaths = pathCache[series.id]
        val path = if (progress == 1f) cachedPaths?.line else null
        val lineColor = resources.lineColors.getValue(series.id)
        if (area) {
            val fill = if (progress == 1f) cachedPaths?.area else null
            val resolvedFill = fill ?: nativePHPChartsAreaPath(data, configuration.style.smooth, progress, layout)
            if (configuration.style.areaGradient) {
                drawPath(
                    path = resolvedFill,
                    brush = Brush.verticalGradient(
                        colors = listOf(
                            lineColor.copy(alpha = configuration.style.areaOpacity * 0.9f),
                            lineColor.copy(alpha = configuration.style.areaOpacity * 0.16f),
                        ),
                        startY = layout.plot.top,
                        endY = layout.plot.bottom,
                    ),
                    style = Fill,
                )
            } else {
                drawPath(resolvedFill, lineColor.copy(alpha = configuration.style.areaOpacity), style = Fill)
            }
        }
        val resolvedPath = path ?: nativePHPChartsPath(data, configuration.style.smooth, progress, layout)
        drawPath(resolvedPath, lineColor, style = resources.lineStroke)
        if ((configuration.style.pointsVisible ?: configuration.showPoints) || data.size == 1) {
            val pointColor = resources.pointColors.getValue(series.id)
            data.forEach { datum ->
                drawCircle(
                    pointColor,
                    configuration.style.pointSize.dp.toPx() / 2f,
                    animatedPoint(datum, progress, layout, useAreaBase = false),
                )
            }
        }
    }
}

internal fun DrawScope.drawNativePHPChartsBars(
    configuration: NativePHPChartsConfiguration,
    layout: NativePHPChartsLayout,
    progress: Float,
) {
    layout.data.forEach { datum ->
        val finalRect = datum.bar ?: return@forEach
        val animatedTop = layout.baselineY + ((finalRect.top - layout.baselineY) * progress)
        val animatedBottom = layout.baselineY + ((finalRect.bottom - layout.baselineY) * progress)
        val top = min(animatedTop, animatedBottom)
        val bottom = max(animatedTop, animatedBottom)
        drawRoundRect(
            datum.series.color,
            topLeft = Offset(finalRect.left, top),
            size = androidx.compose.ui.geometry.Size(finalRect.width, max(bottom - top, 1f)),
            cornerRadius = CornerRadius(configuration.style.barRadius.dp.toPx()),
        )
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
            radius = configuration.style.pointSize.dp.toPx() / 2f,
            center = animatedPoint(datum, progress, layout, useAreaBase = false),
        )
    }
}

internal fun DrawScope.drawNativePHPChartsSelection(
    datum: NativePHPChartsDatum,
    formatting: NativePHPChartsFormatting,
    layout: NativePHPChartsLayout,
    resources: NativePHPChartsDrawingResources,
) {
    drawLine(Color.Gray.copy(alpha = 0.5f), Offset(datum.center.x, layout.plot.top), Offset(datum.center.x, layout.plot.bottom), 1.dp.toPx())
    drawCircle(Color.White, 6.dp.toPx(), datum.center)
    drawCircle(resources.pointColors.getValue(datum.series.id), 4.dp.toPx(), datum.center)
    val text = "${datum.point.label} · ${formatting.value(datum.point.value)}"
    val paint = resources.tooltipPaint
    val availableWidth = layout.plot.width.coerceAtLeast(1f)
    val horizontalPadding = 18.dp.toPx()
    val verticalPadding = 7.dp.toPx()
    val displayText = ellipsizeNativePHPCharts(text, paint, max(availableWidth - horizontalPadding, 0f))
    val width = min(paint.measureText(displayText) + horizontalPadding, availableWidth)
    val fontMetrics = paint.fontMetrics
    val height = (fontMetrics.descent - fontMetrics.ascent) + (verticalPadding * 2)
    val centerX = datum.center.x.coerceIn(layout.plot.left + width / 2, layout.plot.right - width / 2)
    val bottom = (datum.center.y - 12.dp.toPx()).coerceAtLeast(layout.plot.top + height)
    drawRoundRect(Color.Black.copy(alpha = 0.84f), Offset(centerX - width / 2, bottom - height), androidx.compose.ui.geometry.Size(width, height), CornerRadius(height / 2))
    drawContext.canvas.nativeCanvas.drawText(displayText, centerX, bottom - verticalPadding - fontMetrics.bottom, paint)
}

private fun nativePHPChartsPath(
    data: List<NativePHPChartsDatum>,
    smooth: Boolean,
    progress: Float,
    layout: NativePHPChartsLayout,
): Path = Path().apply {
    appendNativePHPChartsPath(data, smooth, progress, layout, useAreaBase = false, reversed = false, move = true)
}

private fun nativePHPChartsAreaPath(
    data: List<NativePHPChartsDatum>,
    smooth: Boolean,
    progress: Float,
    layout: NativePHPChartsLayout,
): Path = Path().apply {
    appendNativePHPChartsPath(data, smooth, progress, layout, useAreaBase = false, reversed = false, move = true)
    appendNativePHPChartsPath(data, smooth, progress, layout, useAreaBase = true, reversed = true, move = false)
    close()
}

private fun Path.appendNativePHPChartsPath(
    data: List<NativePHPChartsDatum>,
    smooth: Boolean,
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

    if (!smooth || data.size < 3) {
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
