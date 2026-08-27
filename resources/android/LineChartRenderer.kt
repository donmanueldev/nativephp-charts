package com.donmanueldev.plugins.nativephp_charts.ui

import android.content.Context
import android.graphics.Paint
import android.graphics.Typeface
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
import androidx.compose.ui.graphics.drawscope.DrawScope
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.graphics.nativeCanvas
import androidx.compose.ui.graphics.toArgb
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.nativephp.mobile.ui.nativerender.ColorParser
import com.nativephp.mobile.ui.nativerender.NativeUINode
import com.nativephp.plugins.native_ui.ui.NativeUIFontResolver
import java.text.NumberFormat
import java.util.Currency
import java.util.Locale
import kotlin.math.abs
import kotlin.math.max
import kotlin.math.min
import org.json.JSONArray
import org.json.JSONObject

object LineChartRenderer {
    private const val DefaultLineColor = 0xFF6366F1.toInt()

    @Composable
    fun Render(node: NativeUINode, modifier: Modifier) {
        val props = node.props
        val seriesJson = props.getString("series_json")
        val series = remember(seriesJson) { decodeSeries(seriesJson) }
        val style = remember(props.getString("style_json")) { decodeStyle(props.getString("style_json")) }
        val points = series?.points.orEmpty()
        val a11yLabel = props.getString("a11y_label", "Chart")
        val emptyLabel = props.getString("empty_label", "No data")

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

        val animated = props.getBool("animated", true)
        var animationStarted by remember(seriesJson, animated) { mutableStateOf(!animated) }
        LaunchedEffect(seriesJson, animated) {
            animationStarted = true
        }
        val animationProgress by animateFloatAsState(
            targetValue = if (animationStarted) 1f else 0f,
            animationSpec = tween(durationMillis = 420),
            label = "lineChartProgress",
        )
        val locale = remember(props.getString("locale")) { localeFor(props.getString("locale")) }
        val formatter = remember(
            props.getString("value_format"),
            props.getString("currency_code"),
            props.getInt("minimum_fraction_digits", -1),
            props.getInt("maximum_fraction_digits", -1),
            locale,
        ) {
            numberFormatter(
                locale = locale,
                valueFormat = props.getString("value_format", "number"),
                currencyCode = props.getString("currency_code"),
                minimumFractionDigits = props.getInt("minimum_fraction_digits", -1),
                maximumFractionDigits = props.getInt("maximum_fraction_digits", -1),
            )
        }
        val context = LocalContext.current
        val font = remember(style.axis.font, context) { resolveTypeface(context, style.axis.font) }
        val summary = chartSummary(a11yLabel, series, formatter)
        val colors = ChartColors(
            axisLabel = chartColor(style.axis.labelColor, MaterialTheme.colorScheme.onSurfaceVariant),
            grid = chartColor(style.grid.color, MaterialTheme.colorScheme.outlineVariant.copy(alpha = 0.65f)),
            axis = chartColor(style.axis.color, MaterialTheme.colorScheme.outline),
            line = chartColor(style.line.color, Color(series?.color ?: DefaultLineColor)),
            point = chartColor(style.points.color, chartColor(style.line.color, Color(series?.color ?: DefaultLineColor))),
        )

        Canvas(
            modifier = modifier
                .semantics { contentDescription = summary }
                .fillMaxSize(),
        ) {
            drawChart(
                points = points,
                style = style,
                colors = colors,
                beginAtZero = props.getBool("begin_at_zero", true),
                showGrid = props.getBool("show_grid", true),
                showPoints = props.getBool("show_points", true),
                animationProgress = animationProgress,
                formatter = formatter,
                typeface = font,
            )
        }
    }

    private fun DrawScope.drawChart(
        points: List<LineChartPoint>,
        style: ChartStyle,
        colors: ChartColors,
        beginAtZero: Boolean,
        showGrid: Boolean,
        showPoints: Boolean,
        animationProgress: Float,
        formatter: NumberFormat,
        typeface: Typeface?,
    ) {
        val axisVisible = style.axis.visible ?: true
        val gridVisible = style.grid.visible ?: showGrid
        val pointsVisible = style.points.visible ?: showPoints
        val axisFontSize = style.axis.fontSize ?: 10f
        val domain = verticalDomain(points.map(LineChartPoint::value), beginAtZero)
        val axisPaint = Paint(Paint.ANTI_ALIAS_FLAG).apply {
            color = colors.axisLabel.toArgb()
            textSize = axisFontSize.sp.toPx()
            typeface?.let { this.typeface = it }
        }
        val labelWidth = if (axisVisible) {
            max(axisPaint.measureText(formatter.format(domain.min)), axisPaint.measureText(formatter.format(domain.max))).coerceAtLeast(42.dp.toPx())
        } else {
            0f
        }
        val horizontalPadding = 12.dp.toPx()
        val chartLeft = horizontalPadding + labelWidth
        val chartRight = (size.width - horizontalPadding).coerceAtLeast(chartLeft + 1f)
        val chartTop = 16.dp.toPx()
        val chartBottom = (size.height - 32.dp.toPx()).coerceAtLeast(chartTop + 1f)
        val chartWidth = chartRight - chartLeft
        val chartHeight = chartBottom - chartTop
        val labelCount = style.axis.labelCount ?: 4

        fun yFor(value: Double): Float {
            val fraction = ((value - domain.min) / (domain.max - domain.min)).toFloat()

            return chartBottom - (fraction * chartHeight)
        }

        if (gridVisible || axisVisible) {
            repeat(labelCount + 1) { index ->
                val fraction = index.toDouble() / labelCount
                val y = chartTop + (chartHeight * index / labelCount)
                if (gridVisible) {
                    drawLine(
                        color = colors.grid,
                        start = Offset(chartLeft, y),
                        end = Offset(chartRight, y),
                        strokeWidth = (style.grid.width ?: 1f).dp.toPx(),
                    )
                }
                if (axisVisible) {
                    axisPaint.textAlign = Paint.Align.RIGHT
                    drawContext.canvas.nativeCanvas.drawText(
                        formatter.format(domain.max - ((domain.max - domain.min) * fraction)),
                        chartLeft - 8.dp.toPx(),
                        y + (axisPaint.textSize / 3f),
                        axisPaint,
                    )
                }
            }
        }

        if (axisVisible) {
            drawLine(
                color = colors.axis,
                start = Offset(chartLeft, chartTop),
                end = Offset(chartLeft, chartBottom),
                strokeWidth = 1.dp.toPx(),
            )
        }

        val zeroY = yFor(0.0)
        if (zeroY in chartTop..chartBottom) {
            drawLine(
                color = colors.axis,
                start = Offset(chartLeft, zeroY),
                end = Offset(chartRight, zeroY),
                strokeWidth = 1.dp.toPx(),
            )
        }

        val coordinates = points.mapIndexed { index, point ->
            val x = if (points.size == 1) chartLeft + (chartWidth / 2f) else chartLeft + (chartWidth * index / points.lastIndex)
            val finalY = yFor(point.value)

            Offset(x, chartBottom - ((chartBottom - finalY) * animationProgress))
        }
        val path = Path().apply {
            moveTo(coordinates.first().x, coordinates.first().y)
            if (style.line.interpolation == "smooth" && coordinates.size > 2) {
                coordinates.drop(1).dropLast(1).forEachIndexed { index, point ->
                    val previous = coordinates[index]
                    quadraticBezierTo(previous.x, previous.y, (previous.x + point.x) / 2f, (previous.y + point.y) / 2f)
                }
                lineTo(coordinates.last().x, coordinates.last().y)
            } else {
                coordinates.drop(1).forEach { lineTo(it.x, it.y) }
            }
        }

        drawPath(
            path = path,
            color = colors.line,
            style = Stroke(width = (style.line.width ?: 3f).dp.toPx(), cap = StrokeCap.Round),
        )

        if (pointsVisible || points.size == 1) {
            coordinates.forEach { coordinate ->
                drawCircle(color = colors.point, radius = (style.points.size ?: 4f).dp.toPx(), center = coordinate)
            }
        }

        if (axisVisible) {
            drawXAxisLabels(points, chartLeft, chartRight, chartBottom, axisPaint, labelCount)
        }
    }

    private fun DrawScope.drawXAxisLabels(
        points: List<LineChartPoint>,
        chartLeft: Float,
        chartRight: Float,
        chartBottom: Float,
        paint: Paint,
        labelCount: Int,
    ) {
        val indexes = labelIndexes(points.size, labelCount)
        val availableWidth = ((chartRight - chartLeft) / max(indexes.size, 1)) - 8.dp.toPx()
        paint.textAlign = Paint.Align.CENTER

        indexes.forEach { index ->
            val x = if (points.size == 1) (chartLeft + chartRight) / 2f else chartLeft + ((chartRight - chartLeft) * index / points.lastIndex)
            drawContext.canvas.nativeCanvas.drawText(
                ellipsize(points[index].label, paint, availableWidth),
                x,
                chartBottom + 20.dp.toPx(),
                paint,
            )
        }
    }

    private fun decodeSeries(seriesJson: String): LineChartSeries? {
        if (seriesJson.isBlank()) {
            return null
        }

        return try {
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
        } catch (_: Exception) {
            null
        }
    }

    private fun decodeStyle(styleJson: String): ChartStyle {
        return try {
            val root = JSONObject(styleJson)
            ChartStyle(
                line = root.optJSONObject("line").toLineStyle(),
                points = root.optJSONObject("points").toPointStyle(),
                grid = root.optJSONObject("grid").toGridStyle(),
                axis = root.optJSONObject("axis").toAxisStyle(),
            )
        } catch (_: Exception) {
            ChartStyle()
        }
    }

    private fun JSONObject?.toLineStyle(): LineStyle = LineStyle(
        color = this?.stringOrNull("color"),
        width = this?.floatOrNull("width"),
        interpolation = this?.stringOrNull("interpolation"),
    )

    private fun JSONObject?.toPointStyle(): PointStyle = PointStyle(
        visible = this?.booleanOrNull("visible"),
        color = this?.stringOrNull("color"),
        size = this?.floatOrNull("size"),
    )

    private fun JSONObject?.toGridStyle(): GridStyle = GridStyle(
        visible = this?.booleanOrNull("visible"),
        color = this?.stringOrNull("color"),
        width = this?.floatOrNull("width"),
    )

    private fun JSONObject?.toAxisStyle(): AxisStyle = AxisStyle(
        visible = this?.booleanOrNull("visible"),
        color = this?.stringOrNull("color"),
        labelColor = this?.stringOrNull("label_color"),
        font = this?.stringOrNull("font"),
        fontSize = this?.floatOrNull("font_size"),
        labelCount = this?.intOrNull("label_count"),
    )

    private fun JSONObject.stringOrNull(name: String): String? = optString(name).takeIf { has(name) && it.isNotBlank() }

    private fun JSONObject.booleanOrNull(name: String): Boolean? = if (has(name)) optBoolean(name) else null

    private fun JSONObject.floatOrNull(name: String): Float? = if (has(name)) optDouble(name).toFloat() else null

    private fun JSONObject.intOrNull(name: String): Int? = if (has(name)) optInt(name) else null

    private fun verticalDomain(values: List<Double>, beginAtZero: Boolean): ChartDomain {
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
        }

        return ChartDomain(minimum, maximum)
    }

    private fun localeFor(value: String): Locale = if (value.isBlank()) Locale.getDefault() else Locale.forLanguageTag(value)

    private fun chartColor(value: String?, fallback: Color): Color = value?.let {
        Color(ColorParser.parse(it, fallback.toArgb()))
    } ?: fallback

    private fun numberFormatter(
        locale: Locale,
        valueFormat: String,
        currencyCode: String,
        minimumFractionDigits: Int,
        maximumFractionDigits: Int,
    ): NumberFormat {
        val formatter = when (valueFormat) {
            "currency" -> NumberFormat.getCurrencyInstance(locale).apply {
                if (currencyCode.isNotBlank()) {
                    runCatching { Currency.getInstance(currencyCode) }.getOrNull()?.let { currency = it }
                }
            }
            "percent" -> NumberFormat.getPercentInstance(locale)
            else -> NumberFormat.getNumberInstance(locale)
        }
        if (minimumFractionDigits >= 0) {
            formatter.minimumFractionDigits = minimumFractionDigits
        }
        if (maximumFractionDigits >= 0) {
            formatter.maximumFractionDigits = maximumFractionDigits
        }

        return formatter
    }

    private fun labelIndexes(size: Int, labelCount: Int): List<Int> {
        if (size <= labelCount) {
            return (0 until size).toList()
        }

        return (0 until labelCount).map { index ->
            (index * (size - 1).toFloat() / (labelCount - 1)).toInt()
        }.distinct()
    }

    private fun ellipsize(value: String, paint: Paint, width: Float): String {
        if (paint.measureText(value) <= width) {
            return value
        }

        val count = paint.breakText(value, true, max(width - paint.measureText("…"), 0f), null)

        return value.take(count) + "…"
    }

    private fun resolveTypeface(context: Context, token: String?): Typeface? {
        if (token.isNullOrBlank() || token == "System") {
            return null
        }

        val name = NativeUIFontResolver.aliases[token] ?: token
        for (extension in listOf("ttf", "otf", "ttc")) {
            try {
                return Typeface.createFromAsset(context.assets, "fonts/$name.$extension")
            } catch (_: Exception) {
            }
        }

        return Typeface.create(name, Typeface.NORMAL)
    }

    private fun chartSummary(a11yLabel: String, series: LineChartSeries?, formatter: NumberFormat): String {
        val points = series?.points.orEmpty()
        if (points.isEmpty()) {
            return a11yLabel
        }

        val values = points.joinToString(separator = ", ") { "${it.label}: ${formatter.format(it.value)}" }
        val name = series?.name?.takeIf(String::isNotBlank)?.let { "$it. " }.orEmpty()

        return "$a11yLabel. $name$values"
    }

    private data class LineChartSeries(val name: String, val color: Int, val points: List<LineChartPoint>)

    private data class LineChartPoint(val label: String, val value: Double)

    private data class ChartDomain(val min: Double, val max: Double)

    private data class ChartStyle(
        val line: LineStyle = LineStyle(),
        val points: PointStyle = PointStyle(),
        val grid: GridStyle = GridStyle(),
        val axis: AxisStyle = AxisStyle(),
    )

    private data class LineStyle(val color: String? = null, val width: Float? = null, val interpolation: String? = null)

    private data class PointStyle(val visible: Boolean? = null, val color: String? = null, val size: Float? = null)

    private data class GridStyle(val visible: Boolean? = null, val color: String? = null, val width: Float? = null)

    private data class AxisStyle(
        val visible: Boolean? = null,
        val color: String? = null,
        val labelColor: String? = null,
        val font: String? = null,
        val fontSize: Float? = null,
        val labelCount: Int? = null,
    )

    private data class ChartColors(
        val axisLabel: Color,
        val grid: Color,
        val axis: Color,
        val line: Color,
        val point: Color,
    )
}
