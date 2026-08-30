package com.donmanueldev.plugins.nativephp_charts.ui

import android.graphics.Paint
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.gestures.detectTapGestures
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.nativeCanvas
import androidx.compose.ui.graphics.drawscope.DrawScope
import androidx.compose.ui.graphics.drawscope.Fill
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.input.pointer.pointerInput
import androidx.compose.ui.layout.onSizeChanged
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.unit.IntSize
import androidx.compose.ui.unit.dp
import com.nativephp.mobile.ui.nativerender.NativeUIBridge
import com.nativephp.mobile.ui.nativerender.NativeUINode
import org.json.JSONArray
import org.json.JSONObject
import kotlin.math.PI
import kotlin.math.cos
import kotlin.math.min
import kotlin.math.sin

private data class NativePHPChartsRadarAxis(val id: String, val label: String, val maximum: Double)
private data class NativePHPChartsRadarValue(val axis: String, val value: Double)
private data class NativePHPChartsRadarSeries(val id: String, val name: String, val color: Color, val values: List<NativePHPChartsRadarValue>)
private data class NativePHPChartsRadarSelection(val series: NativePHPChartsRadarSeries, val axis: NativePHPChartsRadarAxis, val value: NativePHPChartsRadarValue, val point: Offset)

@Composable
internal fun NativePHPChartsRadarRender(node: NativeUINode, modifier: Modifier) {
    val props = node.props
    val axesJson = props.getString("axes_json", "[]")
    val seriesJson = props.getString("series_json", "[]")
    val axes = remember(axesJson) { decodeRadarAxes(axesJson) }
    val series = remember(seriesJson) { decodeRadarSeries(seriesJson) }
    val gridLevels = props.getInt("grid_levels", 5).coerceIn(2, 10)
    val fillOpacity = props.getFloat("fill_opacity", 0.22f).coerceIn(0f, 1f)
    val accessibilityLabel = props.getString("a11y_label", "Radar chart")
    val onSelect = props.getCallbackId("on_select")
    var size by remember { mutableStateOf(IntSize.Zero) }
    var selected by remember { mutableStateOf<NativePHPChartsRadarSelection?>(null) }

    fun selections(): List<NativePHPChartsRadarSelection> {
        val geometry = radarGeometry(size, axes)
        return series.flatMap { item ->
            item.values.mapIndexedNotNull { index, value ->
                val axis = axes.getOrNull(index) ?: return@mapIndexedNotNull null
                NativePHPChartsRadarSelection(item, axis, value, geometry.point(index, value.value / axis.maximum))
            }
        }
    }

    Canvas(
        modifier = modifier
            .onSizeChanged { size = it }
            .semantics { contentDescription = accessibilityLabel }
            .pointerInput(axes, series, onSelect, size) {
                detectTapGestures { location ->
                    val target = selections().minByOrNull { (it.point - location).getDistance() }
                        ?.takeIf { (it.point - location).getDistance() <= 36.dp.toPx() }
                    selected = target
                    if (target != null && onSelect != 0) {
                        val payload = JSONObject()
                            .put("version", 1).put("chart_type", "radar")
                            .put("series_id", target.series.id).put("series_name", target.series.name)
                            .put("point_id", target.axis.id).put("point_index", axes.indexOf(target.axis))
                            .put("x_type", "category").put("x", target.axis.id)
                            .put("label", target.axis.label).put("value", target.value.value)
                            .put("localized_value", target.value.value.toString()).toString()
                        NativeUIBridge.sendTextChangeEvent(onSelect, node.id, payload)
                    }
                }
            },
    ) {
        if (axes.size < 3) return@Canvas
        val geometry = radarGeometry(size, axes)
        val labelPaint = Paint(Paint.ANTI_ALIAS_FLAG).apply {
            color = android.graphics.Color.GRAY
            textSize = 11.dp.toPx()
            textAlign = Paint.Align.CENTER
        }
        for (level in 1..gridLevels) {
            drawRadarPolygon(axes.indices.map { geometry.point(it, level.toDouble() / gridLevels) }, Color.Gray.copy(alpha = 0.2f), Stroke(1.dp.toPx()))
        }
        axes.indices.forEach { index ->
            val outer = geometry.point(index, 1.0)
            drawLine(Color.Gray.copy(alpha = 0.28f), geometry.center, outer, 1.dp.toPx())
            val label = geometry.point(index, 1.16)
            drawContext.canvas.nativeCanvas.drawText(axes[index].label, label.x, label.y - labelPaint.fontMetrics.ascent / 2, labelPaint)
        }
        series.forEach { item ->
            val points = item.values.mapIndexedNotNull { index, value ->
                axes.getOrNull(index)?.let { geometry.point(index, value.value / it.maximum) }
            }
            drawRadarPolygon(points, item.color.copy(alpha = fillOpacity), Fill)
            drawRadarPolygon(points, item.color, Stroke(2.dp.toPx()))
            points.forEach { drawCircle(item.color, 3.5.dp.toPx(), it) }
        }
        selected?.let { target ->
            drawCircle(Color.White, 7.dp.toPx(), target.point)
            drawCircle(target.series.color, 5.dp.toPx(), target.point)
        }
    }
}

private data class NativePHPChartsRadarGeometry(val center: Offset, val radius: Float, val count: Int) {
    fun point(index: Int, ratio: Double): Offset {
        val angle = -PI / 2 + (2 * PI * index / count)
        return center + Offset((cos(angle) * radius * ratio).toFloat(), (sin(angle) * radius * ratio).toFloat())
    }
}

private fun radarGeometry(size: IntSize, axes: List<NativePHPChartsRadarAxis>): NativePHPChartsRadarGeometry =
    NativePHPChartsRadarGeometry(Offset(size.width / 2f, size.height / 2f), min(size.width, size.height) * 0.34f, axes.size.coerceAtLeast(1))

private fun DrawScope.drawRadarPolygon(points: List<Offset>, color: Color, style: androidx.compose.ui.graphics.drawscope.DrawStyle) {
    if (points.size < 3) return
    val path = Path().apply {
        moveTo(points[0].x, points[0].y)
        points.drop(1).forEach { lineTo(it.x, it.y) }
        close()
    }
    drawPath(path, color, style = style)
}

private fun decodeRadarAxes(json: String): List<NativePHPChartsRadarAxis> = runCatching {
    val root = JSONArray(json)
    (0 until root.length()).map { index ->
        val item = root.getJSONObject(index)
        NativePHPChartsRadarAxis(item.getString("id"), item.getString("label"), item.getDouble("maximum"))
    }
}.getOrDefault(emptyList())

private fun decodeRadarSeries(json: String): List<NativePHPChartsRadarSeries> = runCatching {
    val root = JSONArray(json)
    (0 until root.length()).map { index ->
        val item = root.getJSONObject(index)
        val values = item.getJSONArray("values")
        NativePHPChartsRadarSeries(
            item.getString("id"), item.getString("name"), chartColor(item.getString("color"), Color(0xFF6366F1)),
            (0 until values.length()).map { valueIndex ->
                val value = values.getJSONObject(valueIndex)
                NativePHPChartsRadarValue(value.getString("axis"), value.getDouble("value"))
            },
        )
    }
}.getOrDefault(emptyList())
