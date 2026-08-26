package com.donmanueldev.plugins.nativephp_charts.ui

import android.graphics.Paint
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.tween
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.wrapContentSize
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.graphics.nativeCanvas
import androidx.compose.ui.graphics.toArgb
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.nativephp.mobile.ui.nativerender.ColorParser
import com.nativephp.mobile.ui.nativerender.NativeUINode
import kotlin.math.abs
import kotlin.math.max
import kotlin.math.min
import org.json.JSONArray

/**
 * NativePHP Line Chart rendered from the normalized `series_json` wire property.
 *
 * The current NativePHP wire protocol transports scalar properties only, so the
 * PHP element sends the documented series array as JSON. This renderer decodes
 * that boundary defensively and never lets malformed native input crash a view.
 */
object LineChartRenderer {
    private const val DefaultLineColor = 0xFF6366F1.toInt()
    private const val GridLines = 5

    @Composable
    fun Render(node: NativeUINode, modifier: Modifier) {
        val props = node.props
        val seriesJson = props.getString("series_json")
        val series = remember(seriesJson) { decodeSeries(seriesJson) }
        val points = series?.points.orEmpty()
        val emptyLabel = props.getString("empty_label", "No data")
        val showGrid = props.getBool("show_grid", true)
        val showPoints = props.getBool("show_points", true)
        val beginAtZero = props.getBool("begin_at_zero", true)
        val animated = props.getBool("animated", true)
        val a11yLabel = props.getString("a11y_label", "Chart")
        val summary = chartSummary(a11yLabel, series)

        if (points.isEmpty()) {
            Box(
                modifier = modifier
                    .semantics { contentDescription = "$a11yLabel: $emptyLabel" }
                    .wrapContentSize(),
            ) {
                Text(text = emptyLabel)
            }

            return
        }

        var animationStarted by remember(seriesJson, animated) {
            mutableStateOf(!animated)
        }
        LaunchedEffect(seriesJson, animated) {
            animationStarted = !animated
            if (animated) {
                animationStarted = true
            }
        }
        val animationProgress by animateFloatAsState(
            targetValue = if (animationStarted) 1f else 0f,
            animationSpec = tween(durationMillis = 420),
            label = "lineChartProgress",
        )

        val lineColor = Color(series?.color ?: DefaultLineColor)
        val gridColor = MaterialTheme.colorScheme.outlineVariant.copy(alpha = 0.65f)
        val axisColor = MaterialTheme.colorScheme.outline
        val labelColor = MaterialTheme.colorScheme.onSurfaceVariant

        Canvas(
            modifier = modifier
                .semantics { contentDescription = summary }
                .fillMaxSize(),
        ) {
            val horizontalPadding = 12.dp.toPx()
            val topPadding = 16.dp.toPx()
            val bottomPadding = 30.dp.toPx()
            val chartLeft = horizontalPadding
            val chartRight = (size.width - horizontalPadding).coerceAtLeast(chartLeft + 1f)
            val chartTop = topPadding
            val chartBottom = (size.height - bottomPadding).coerceAtLeast(chartTop + 1f)
            val chartWidth = chartRight - chartLeft
            val chartHeight = chartBottom - chartTop
            val domain = verticalDomain(points.map(LineChartPoint::value), beginAtZero)

            fun yFor(value: Double): Float {
                val fraction = ((value - domain.min) / (domain.max - domain.min)).toFloat()
                return chartBottom - (fraction * chartHeight)
            }

            if (showGrid) {
                repeat(GridLines + 1) { index ->
                    val y = chartTop + (chartHeight * index / GridLines)
                    drawLine(
                        color = gridColor,
                        start = Offset(chartLeft, y),
                        end = Offset(chartRight, y),
                        strokeWidth = 1.dp.toPx(),
                    )
                }
            }

            val zeroY = yFor(0.0)
            if (zeroY in chartTop..chartBottom) {
                drawLine(
                    color = axisColor,
                    start = Offset(chartLeft, zeroY),
                    end = Offset(chartRight, zeroY),
                    strokeWidth = 1.dp.toPx(),
                )
            }

            val xStep = if (points.size == 1) 0f else chartWidth / (points.size - 1)
            val path = Path()
            points.forEachIndexed { index, point ->
                val x = if (points.size == 1) chartLeft + (chartWidth / 2f) else chartLeft + (index * xStep)
                val y = chartBottom - ((chartBottom - yFor(point.value)) * animationProgress)
                if (index == 0) {
                    path.moveTo(x, y)
                } else {
                    path.lineTo(x, y)
                }
            }

            drawPath(
                path = path,
                color = lineColor,
                style = Stroke(width = 3.dp.toPx(), cap = StrokeCap.Round),
            )

            if (showPoints) {
                points.forEachIndexed { index, point ->
                    val x = if (points.size == 1) chartLeft + (chartWidth / 2f) else chartLeft + (index * xStep)
                    val y = chartBottom - ((chartBottom - yFor(point.value)) * animationProgress)
                    drawCircle(color = lineColor, radius = 4.dp.toPx(), center = Offset(x, y))
                }
            }

            drawAxisLabels(
                points = points,
                chartLeft = chartLeft,
                chartRight = chartRight,
                chartBottom = chartBottom,
                color = labelColor,
            )
        }
    }

    private fun androidx.compose.ui.graphics.drawscope.DrawScope.drawAxisLabels(
        points: List<LineChartPoint>,
        chartLeft: Float,
        chartRight: Float,
        chartBottom: Float,
        color: Color,
    ) {
        val maxLabels = 4
        val labelIndexes = if (points.size <= maxLabels) {
            points.indices.toList()
        } else {
            (0 until maxLabels).map { index ->
                (index * (points.lastIndex.toFloat() / (maxLabels - 1))).toInt()
            }.distinct()
        }
        val paint = Paint(Paint.ANTI_ALIAS_FLAG).apply {
            this.color = color.toArgb()
            textSize = 10.sp.toPx()
            textAlign = Paint.Align.CENTER
        }

        labelIndexes.forEach { index ->
            val x = if (points.size == 1) {
                (chartLeft + chartRight) / 2f
            } else {
                chartLeft + ((chartRight - chartLeft) * index.toFloat() / points.lastIndex.toFloat())
            }
            drawContext.canvas.nativeCanvas.drawText(
                points[index].label,
                x,
                chartBottom + 18.dp.toPx(),
                paint,
            )
        }
    }

    private fun decodeSeries(seriesJson: String): LineChartSeries? {
        if (seriesJson.isBlank()) {
            return null
        }

        return runCatching {
            val series = JSONArray(seriesJson).optJSONObject(0) ?: return null
            val points = series.optJSONArray("points") ?: JSONArray()
            val decodedPoints = buildList {
                for (index in 0 until points.length()) {
                    val point = points.optJSONObject(index) ?: continue
                    val value = point.optDouble("value", Double.NaN)
                    if (value.isFinite()) {
                        add(LineChartPoint(point.optString("label"), value))
                    }
                }
            }
            LineChartSeries(
                name = series.optString("name"),
                color = ColorParser.parse(series.optString("color"), DefaultLineColor),
                points = decodedPoints,
            )
        }.getOrNull()
    }

    private fun verticalDomain(values: List<Double>, beginAtZero: Boolean): ChartDomain {
        var minimum = values.minOrNull() ?: 0.0
        var maximum = values.maxOrNull() ?: 0.0
        if (beginAtZero) {
            minimum = min(minimum, 0.0)
            maximum = max(maximum, 0.0)
        }
        if (minimum == maximum) {
            val padding = max(abs(minimum) * 0.1, 1.0)
            minimum -= padding
            maximum += padding
        }

        return ChartDomain(minimum, maximum)
    }

    private fun chartSummary(a11yLabel: String, series: LineChartSeries?): String {
        val points = series?.points.orEmpty()
        if (points.isEmpty()) {
            return a11yLabel
        }

        val values = points.joinToString(separator = ", ") { "${it.label}: ${it.value}" }
        val name = series?.name?.takeIf(String::isNotBlank)?.let { "$it. " }.orEmpty()
        return "$a11yLabel. $name$values"
    }

    private data class LineChartSeries(
        val name: String,
        val color: Int,
        val points: List<LineChartPoint>,
    )

    private data class LineChartPoint(val label: String, val value: Double)

    private data class ChartDomain(val min: Double, val max: Double)
}
