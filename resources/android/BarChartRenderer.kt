package com.donmanueldev.plugins.nativephp_charts.ui

import android.graphics.Paint
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.tween
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.gestures.detectTapGestures
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.wrapContentSize
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.geometry.CornerRadius
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Rect
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.drawscope.DrawScope
import androidx.compose.ui.graphics.nativeCanvas
import androidx.compose.ui.graphics.toArgb
import androidx.compose.ui.input.pointer.pointerInput
import androidx.compose.ui.layout.onSizeChanged
import androidx.compose.ui.platform.LocalDensity
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.IntSize
import androidx.compose.ui.unit.sp
import com.nativephp.mobile.ui.nativerender.ColorParser
import com.nativephp.mobile.ui.nativerender.NativeUINode
import java.text.NumberFormat
import java.util.Currency
import java.util.Locale
import kotlin.math.abs
import kotlin.math.max
import kotlin.math.min
import org.json.JSONArray

object BarChartRenderer {
    private const val DefaultBarColor = 0xFF6366F1.toInt()

    @Composable
    fun Render(node: NativeUINode, modifier: Modifier) {
        val seriesJson = node.props.getString("series_json")
        val series = remember(seriesJson) { decodeSeries(seriesJson) }
        val points = series?.points.orEmpty()
        val a11yLabel = node.props.getString("a11y_label", "Chart")
        val emptyLabel = node.props.getString("empty_label", "No data")
        if (points.isEmpty()) {
            Box(modifier = modifier.semantics { contentDescription = "$a11yLabel: $emptyLabel" }.wrapContentSize()) {
                Text(text = emptyLabel)
            }
            return
        }

        val animated = node.props.getBool("animated", true)
        var started by remember(seriesJson, animated) { mutableStateOf(!animated) }
        LaunchedEffect(seriesJson, animated) { started = true }
        val progress by animateFloatAsState(if (started) 1f else 0f, tween(420), label = "barChartProgress")
        val locale = node.props.getString("locale")
        val valueFormat = node.props.getString("value_format")
        val currencyCode = node.props.getString("currency_code")
        val minimumFractionDigits = node.props.getInt("minimum_fraction_digits", -1)
        val maximumFractionDigits = node.props.getInt("maximum_fraction_digits", -1)
        val formatter = remember(locale, valueFormat, currencyCode, minimumFractionDigits, maximumFractionDigits) {
            numberFormatter(
                localeFor(locale),
                valueFormat.ifBlank { "number" },
                currencyCode,
                minimumFractionDigits,
                maximumFractionDigits,
            )
        }
        val barColor = Color(series?.color ?: DefaultBarColor)
        val axisLabelColor = MaterialTheme.colorScheme.onSurfaceVariant
        val gridColor = MaterialTheme.colorScheme.outlineVariant.copy(alpha = 0.65f)
        val axisColor = MaterialTheme.colorScheme.outline
        val tooltipBackground = MaterialTheme.colorScheme.inverseSurface
        val tooltipLabel = MaterialTheme.colorScheme.inverseOnSurface
        val density = LocalDensity.current
        var selectedIndex by remember(seriesJson) { mutableIntStateOf(-1) }
        var canvasSize by remember { mutableStateOf(IntSize.Zero) }
        Canvas(
            modifier = modifier
                .semantics { contentDescription = summary(a11yLabel, series, formatter) }
                .onSizeChanged { canvasSize = it }
                .pointerInput(points, canvasSize, formatter) {
                    detectTapGestures { position ->
                        selectedIndex = barIndexAt(
                            position.x,
                            points,
                            formatter,
                            node.props.getBool("begin_at_zero", true),
                            canvasSize,
                            density,
                        )
                    }
                }
                .fillMaxSize(),
        ) {
            drawBars(
                points, barColor, node.props.getBool("begin_at_zero", true), node.props.getBool("show_grid", true),
                node.props.getBool("show_points", true), progress, formatter, axisLabelColor, gridColor, axisColor,
                selectedIndex, tooltipBackground, tooltipLabel,
            )
        }
    }

    private fun DrawScope.drawBars(
        points: List<BarPoint>, barColor: Color, beginAtZero: Boolean, showGrid: Boolean,
        showAxis: Boolean, progress: Float, formatter: NumberFormat, axisLabelColor: Color, gridColor: Color, axisColor: Color,
        selectedIndex: Int, tooltipBackground: Color, tooltipLabel: Color,
    ) {
        val domain = verticalDomain(points.map(BarPoint::value), beginAtZero)
        val paint = Paint(Paint.ANTI_ALIAS_FLAG).apply { color = axisLabelColor.toArgb(); textSize = 10.sp.toPx() }
        val labelWidth = if (showAxis) max(paint.measureText(formatter.format(domain.min)), paint.measureText(formatter.format(domain.max))).coerceAtLeast(42.dp.toPx()) else 0f
        val left = 12.dp.toPx() + labelWidth
        val right = (size.width - 12.dp.toPx()).coerceAtLeast(left + 1f)
        val top = 16.dp.toPx()
        val bottom = (size.height - 32.dp.toPx()).coerceAtLeast(top + 1f)
        val height = bottom - top
        fun yFor(value: Double): Float = bottom - (((value - domain.min) / (domain.max - domain.min)).toFloat() * height)
        val zeroY = yFor(0.0)
        if (showGrid || showAxis) {
            repeat(5) { index ->
                val y = top + (height * index / 4f)
                if (showGrid) drawLine(gridColor, Offset(left, y), Offset(right, y), 1.dp.toPx())
                if (showAxis) {
                    paint.textAlign = Paint.Align.RIGHT
                    drawContext.canvas.nativeCanvas.drawText(formatter.format(domain.max - ((domain.max - domain.min) * index / 4)), left - 6.dp.toPx(), y + 4.dp.toPx(), paint)
                }
            }
        }
        drawLine(axisColor, Offset(left, zeroY), Offset(right, zeroY), 1.dp.toPx())
        val slot = (right - left) / points.size
        val barWidth = (slot * 0.68f).coerceAtLeast(2.dp.toPx())
        points.forEachIndexed { index, point ->
            val x = left + (slot * index) + ((slot - barWidth) / 2)
            val end = yFor(point.value)
            val animatedEnd = zeroY + ((end - zeroY) * progress)
            val rect = Rect(x, min(zeroY, animatedEnd), x + barWidth, max(zeroY, animatedEnd))
            drawRoundRect(
                if (index == selectedIndex) barColor else barColor.copy(alpha = 0.82f),
                rect.topLeft,
                rect.size,
                CornerRadius(5.dp.toPx(), 5.dp.toPx()),
            )
            if (index == selectedIndex) {
                drawTooltip(point, formatter, rect, top, tooltipBackground, tooltipLabel)
            }
            if (showAxis) {
                paint.textAlign = Paint.Align.CENTER
                val available = slot - 6.dp.toPx()
                drawContext.canvas.nativeCanvas.drawText(ellipsize(point.label, paint, available), x + (barWidth / 2), bottom + 20.dp.toPx(), paint)
            }
        }
    }

    private fun barIndexAt(
        touchX: Float,
        points: List<BarPoint>,
        formatter: NumberFormat,
        beginAtZero: Boolean,
        canvasSize: IntSize,
        density: androidx.compose.ui.unit.Density,
    ): Int {
        if (canvasSize.width == 0 || points.isEmpty()) {
            return -1
        }

        val paint = Paint(Paint.ANTI_ALIAS_FLAG).apply {
            textSize = with(density) { 10.sp.toPx() }
        }
        val domain = verticalDomain(points.map(BarPoint::value), beginAtZero)
        val labelWidth = max(paint.measureText(formatter.format(domain.min)), paint.measureText(formatter.format(domain.max))).coerceAtLeast(with(density) { 42.dp.toPx() })
        val left = with(density) { 12.dp.toPx() } + labelWidth
        val right = canvasSize.width - with(density) { 12.dp.toPx() }
        if (touchX < left || touchX > right) {
            return -1
        }

        return ((touchX - left) / ((right - left) / points.size)).toInt().coerceIn(0, points.lastIndex)
    }

    private fun DrawScope.drawTooltip(
        point: BarPoint,
        formatter: NumberFormat,
        bar: Rect,
        chartTop: Float,
        background: Color,
        labelColor: Color,
    ) {
        val paint = Paint(Paint.ANTI_ALIAS_FLAG).apply {
            color = labelColor.toArgb()
            textSize = 12.sp.toPx()
            textAlign = Paint.Align.CENTER
        }
        val horizontalPadding = 10.dp.toPx()
        val verticalPadding = 7.dp.toPx()
        val maximumWidth = (size.width - 24.dp.toPx()).coerceAtLeast(1f)
        val maximumTextWidth = (maximumWidth - (horizontalPadding * 2)).coerceAtLeast(0f)
        val text = ellipsize("${point.label} · ${formatter.format(point.value)}", paint, maximumTextWidth)
        val width = (paint.measureText(text) + (horizontalPadding * 2)).coerceAtMost(maximumWidth)
        val height = (paint.fontMetrics.descent - paint.fontMetrics.ascent) + (verticalPadding * 2)
        val minimumCenterX = width / 2
        val maximumCenterX = (size.width - (width / 2)).coerceAtLeast(minimumCenterX)
        val centerX = (bar.left + (bar.width / 2)).coerceIn(minimumCenterX, maximumCenterX)
        val bottom = (bar.top - 8.dp.toPx()).coerceAtLeast(chartTop + height)
        val rect = Rect(centerX - (width / 2), bottom - height, centerX + (width / 2), bottom)

        drawRoundRect(background, rect.topLeft, rect.size, CornerRadius(height / 2, height / 2))
        drawContext.canvas.nativeCanvas.drawText(text, centerX, bottom - verticalPadding - paint.fontMetrics.bottom, paint)
    }

    private fun decodeSeries(json: String): BarSeries? = try {
        val series = JSONArray(json).optJSONObject(0) ?: return null
        val points = series.optJSONArray("points") ?: JSONArray()
        val decoded = buildList {
            for (index in 0 until points.length()) {
                val point = points.optJSONObject(index) ?: continue
                point.optDouble("value", Double.NaN).takeIf(Double::isFinite)?.let { add(BarPoint(point.optString("label"), it)) }
            }
        }
        BarSeries(series.optString("name"), ColorParser.parse(series.optString("color"), DefaultBarColor), decoded)
    } catch (_: Exception) { null }

    private fun verticalDomain(values: List<Double>, beginAtZero: Boolean): Domain {
        var min = values.minOrNull() ?: 0.0; var max = values.maxOrNull() ?: 0.0
        if (beginAtZero) { min = min(min, 0.0); max = max(max, 0.0) }
        if (min == max) { val padding = if (min == 0.0) 1.0 else abs(min) * 0.1; min -= padding; max += padding }
        return Domain(min, max)
    }

    private fun localeFor(value: String): Locale = if (value.isBlank()) Locale.getDefault() else Locale.forLanguageTag(value)
    private fun numberFormatter(locale: Locale, format: String, currencyCode: String, minimum: Int, maximum: Int): NumberFormat {
        val formatter = when (format) {
            "currency" -> NumberFormat.getCurrencyInstance(locale).apply { runCatching { Currency.getInstance(currencyCode) }.getOrNull()?.let { currency = it } }
            "percent" -> NumberFormat.getPercentInstance(locale)
            else -> NumberFormat.getNumberInstance(locale)
        }
        if (minimum >= 0) formatter.minimumFractionDigits = minimum
        if (maximum >= 0) formatter.maximumFractionDigits = maximum
        return formatter
    }
    private fun ellipsize(value: String, paint: Paint, width: Float): String = if (paint.measureText(value) <= width) value else value.take(paint.breakText(value, true, max(width - paint.measureText("…"), 0f), null)) + "…"
    private fun summary(label: String, series: BarSeries?, formatter: NumberFormat): String = "$label. ${series?.name.orEmpty()}. ${series?.points?.joinToString { "${it.label}: ${formatter.format(it.value)}" }.orEmpty()}"
    private data class BarSeries(val name: String, val color: Int, val points: List<BarPoint>)
    private data class BarPoint(val label: String, val value: Double)
    private data class Domain(val min: Double, val max: Double)
}
