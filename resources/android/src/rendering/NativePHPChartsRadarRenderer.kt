package com.donmanueldev.plugins.nativephp_charts.ui

import android.graphics.Paint
import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.tween
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.gestures.detectTapGestures
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.PathEffect
import androidx.compose.ui.graphics.toArgb
import androidx.compose.ui.graphics.drawscope.Fill
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.graphics.nativeCanvas
import androidx.compose.ui.input.pointer.pointerInput
import androidx.compose.ui.layout.onSizeChanged
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalDensity
import androidx.compose.ui.semantics.CustomAccessibilityAction
import androidx.compose.ui.semantics.clearAndSetSemantics
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.customActions
import androidx.compose.ui.semantics.onClick
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.semantics.stateDescription
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.IntSize
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.nativephp.mobile.ui.nativerender.NativeUIBridge
import com.nativephp.mobile.ui.nativerender.NativeUINode
import com.nativephp.plugins.native_ui.ui.NativeUIFontResolver
import org.json.JSONArray
import org.json.JSONObject
import kotlin.math.PI
import kotlin.math.cos
import kotlin.math.min
import kotlin.math.sin

internal data class NativePHPChartsRadarNavigation(
    val previous: NativePHPChartsRadarSelection?,
    val next: NativePHPChartsRadarSelection?,
)

internal fun nativePHPChartsRadarNavigation(
    selections: List<NativePHPChartsRadarSelection>,
    selectedId: String?,
): NativePHPChartsRadarNavigation {
    if (selections.isEmpty()) return NativePHPChartsRadarNavigation(null, null)
    val index = selections.indexOfFirst { it.id == selectedId }
    if (index < 0) return NativePHPChartsRadarNavigation(null, selections.first())
    return NativePHPChartsRadarNavigation(
        previous = selections.getOrNull(index - 1),
        next = selections.getOrNull(index + 1),
    )
}

/**
 * Keeps a dense radar readable without changing the semantic axis labels used by
 * tooltips, selection callbacks, or accessibility actions.
 *
 * Twelve short labels still fit around the plot. Beyond that, rendering every
 * label would overlap, so the visible ring uses stable one-based axis markers
 * on alternating axes instead. A selected point always exposes its full label.
 */
internal fun nativePHPChartsRadarAxisDisplayLabel(
    label: String,
    index: Int,
    axisCount: Int,
): String? = when {
    axisCount <= 8 -> label
    axisCount <= 12 -> nativePHPChartsRadarAbbreviateAxisLabel(label, maximumLength = 10)
    index % 2 == 0 -> (index + 1).toString()
    else -> null
}

internal fun nativePHPChartsRadarAbbreviateAxisLabel(label: String, maximumLength: Int): String {
    val normalized = label.trim()
    if (maximumLength <= 1) return "…"
    if (normalized.length <= maximumLength) return normalized

    return normalized.take(maximumLength - 1).trimEnd() + "…"
}

internal fun nativePHPChartsRadarNearestSelection(
    selections: List<Pair<NativePHPChartsRadarSelection, Offset>>,
    location: Offset,
    threshold: Float,
): NativePHPChartsRadarSelection? {
    if (threshold < 0f) return null
    return selections.minByOrNull { (it.second - location).getDistance() }
        ?.takeIf { (it.second - location).getDistance() <= threshold }
        ?.first
}

internal fun nativePHPChartsRadarSelectedIdAfterTap(
    selections: List<Pair<NativePHPChartsRadarSelection, Offset>>,
    location: Offset,
    threshold: Float,
): String? = nativePHPChartsRadarNearestSelection(selections, location, threshold)?.id

internal fun nativePHPChartsRadarSelectionPayload(
    selection: NativePHPChartsRadarSelection,
    localizedValue: String,
): Map<String, Any> = linkedMapOf(
    "version" to 1,
    "chart_type" to "radar",
    "series_id" to selection.series.id,
    "series_name" to selection.series.name,
    "point_id" to selection.axis.id,
    "point_index" to selection.index,
    "x_type" to "category",
    "x" to selection.axis.id,
    "label" to selection.axis.label,
    "value" to selection.value.value,
    "localized_value" to localizedValue,
)

internal data class NativePHPChartsRadarTooltipLayout(
    val left: Float,
    val top: Float,
    val width: Float,
    val height: Float,
    val drawsText: Boolean,
)

internal fun nativePHPChartsRadarTooltipLayout(
    canvas: Size,
    anchor: Offset,
    measuredTextWidth: Float,
    requestedHeight: Float,
    horizontalPadding: Float,
    margin: Float,
    verticalOffset: Float,
): NativePHPChartsRadarTooltipLayout? {
    if (!canvas.width.isFinite() || !canvas.height.isFinite() || canvas.width <= 0f || canvas.height <= 0f) return null
    val safeMargin = (margin.takeIf { it.isFinite() } ?: 0f)
        .coerceAtLeast(0f)
        .coerceAtMost(min(canvas.width, canvas.height) / 2f)
    val availableWidth = (canvas.width - (safeMargin * 2f)).coerceAtLeast(0f)
    val availableHeight = (canvas.height - (safeMargin * 2f)).coerceAtLeast(0f)
    if (availableWidth <= 0f || availableHeight <= 0f) return null

    val safeTextWidth = measuredTextWidth.takeIf { it.isFinite() }?.coerceAtLeast(0f) ?: 0f
    val safePadding = horizontalPadding.takeIf { it.isFinite() }?.coerceAtLeast(0f) ?: 0f
    val safeHeight = requestedHeight.takeIf { it.isFinite() }?.coerceAtLeast(0f) ?: 0f
    val width = min(safeTextWidth + safePadding, availableWidth)
    val height = min(safeHeight, availableHeight)
    if (width <= 0f || height <= 0f) return null

    val minimumCenterX = safeMargin + (width / 2f)
    val maximumCenterX = canvas.width - safeMargin - (width / 2f)
    val safeAnchorX = anchor.x.takeIf { it.isFinite() } ?: canvas.width / 2f
    val safeAnchorY = anchor.y.takeIf { it.isFinite() } ?: canvas.height / 2f
    val centerX = safeAnchorX.coerceIn(minimumCenterX, maximumCenterX)
    val minimumTop = safeMargin
    val maximumTop = canvas.height - safeMargin - height
    val safeVerticalOffset = (verticalOffset.takeIf { it.isFinite() } ?: 0f).coerceAtLeast(0f)
    val desiredTop = safeAnchorY - safeVerticalOffset
    val top = desiredTop.coerceIn(minimumTop, maximumTop)

    return NativePHPChartsRadarTooltipLayout(
        left = centerX - (width / 2f),
        top = top,
        width = width,
        height = height,
        drawsText = safeTextWidth + safePadding <= availableWidth && safeHeight <= availableHeight,
    )
}

@Composable
internal fun NativePHPChartsRadarRender(node: NativeUINode, modifier: Modifier) {
    val props = node.props
    val wireKey = listOf(
        props.getString("axes_json", "[]"), props.getString("series_json", "[]"),
        props.getString("style_json", "{}"), props.getString("legend_json", "{}"),
        props.getString("locale", ""), props.getString("value_format", "number"),
        props.getString("currency_code", ""), props.getInt("minimum_fraction_digits", -1),
        props.getInt("maximum_fraction_digits", -1), props.getBool("animated", true),
        props.getString("empty_label", "No data"), props.getString("a11y_label", "Chart"),
        props.getCallbackId("on_select"), props.getInt("grid_levels", 5),
        props.getFloat("fill_opacity", 0.22f),
    )
    val configuration = remember(wireKey) { decodeRadarConfiguration(node) }
    val formatting = remember(configuration) { NativePHPChartsRadarFormatting(configuration) }

    if (!configuration.hasData) {
        Box(
            modifier = modifier.fillMaxSize().clearAndSetSemantics {
                contentDescription = "${configuration.accessibilityLabel}: ${configuration.emptyLabel}"
            },
        ) {
            Text(configuration.emptyLabel, Modifier.padding(16.dp))
        }
        return
    }

    val position = configuration.legend.position
    if (configuration.legendVisible && position in setOf("leading", "trailing")) {
        Row(modifier.fillMaxSize()) {
            if (position == "leading") NativePHPChartsRadarLegend(configuration, horizontal = false)
            NativePHPChartsRadarPlot(node, configuration, formatting, Modifier.weight(1f).fillMaxSize())
            if (position == "trailing") NativePHPChartsRadarLegend(configuration, horizontal = false)
        }
    } else {
        Column(modifier.fillMaxSize()) {
            if (configuration.legendVisible && position == "top") NativePHPChartsRadarLegend(configuration, horizontal = true)
            NativePHPChartsRadarPlot(node, configuration, formatting, Modifier.weight(1f).fillMaxWidth())
            if (configuration.legendVisible && position != "top") NativePHPChartsRadarLegend(configuration, horizontal = true)
        }
    }
}

@Composable
private fun NativePHPChartsRadarPlot(
    node: NativeUINode,
    configuration: NativePHPChartsRadarConfiguration,
    formatting: NativePHPChartsRadarFormatting,
    modifier: Modifier,
) {
    val localDensity = LocalDensity.current
    val context = LocalContext.current
    val shouldAnimate = configuration.animated && remember(context) { nativePHPChartsAnimationsEnabled(context) }
    val typeface = remember(context, configuration.style.axisFont) {
        resolveNativePHPChartsTypeface(context, configuration.style.axisFont)
    }
    val labelPaint = remember(localDensity, typeface, configuration.style) {
        Paint(Paint.ANTI_ALIAS_FLAG).apply {
            textSize = with(localDensity) { configuration.style.axisFontSize.sp.toPx() }
            textAlign = Paint.Align.CENTER
            this.typeface = typeface
        }
    }
    val tooltipPaint = remember(localDensity) {
        Paint(Paint.ANTI_ALIAS_FLAG).apply {
            color = android.graphics.Color.WHITE
            textSize = with(localDensity) { 11.sp.toPx() }
            textAlign = Paint.Align.CENTER
        }
    }
    var size by remember { mutableStateOf(IntSize.Zero) }
    var selectedId by remember { mutableStateOf<String?>(null) }
    val selected = configuration.selections.firstOrNull { it.id == selectedId }
    val progress = remember { Animatable(if (shouldAnimate) 0f else 1f) }

    LaunchedEffect(configuration.animationKey, shouldAnimate) {
        if (shouldAnimate) {
            progress.snapTo(0f)
            progress.animateTo(1f, tween(durationMillis = 460))
        } else {
            progress.snapTo(1f)
        }
    }
    LaunchedEffect(configuration.selections, selectedId) {
        if (selectedId != null && selected == null) selectedId = null
    }

    fun select(selection: NativePHPChartsRadarSelection): Boolean {
        selectedId = selection.id
        if (configuration.onSelect > 0) {
            val payload = JSONObject(
                nativePHPChartsRadarSelectionPayload(
                    selection = selection,
                    localizedValue = formatting.value(selection.value.value),
                ),
            ).toString()
            NativeUIBridge.sendTextChangeEvent(configuration.onSelect, node.id, payload)
        }
        return true
    }

    fun moveSelection(offset: Int): Boolean {
        val adjacent = nativePHPChartsRadarNavigation(configuration.selections, selectedId)
        val target = if (offset < 0) adjacent.previous else adjacent.next
        return target?.let(::select) ?: false
    }

    val summary = remember(configuration, formatting) {
        buildString {
            append(configuration.accessibilityLabel)
            configuration.selections.take(18).forEach {
                append(". ").append(it.series.name).append(", ").append(it.axis.label)
                    .append(": ").append(formatting.value(it.value.value))
            }
            if (configuration.selections.size > 18) append(". (+").append(configuration.selections.size - 18).append(')')
        }
    }
    val navigation = nativePHPChartsRadarNavigation(configuration.selections, selectedId)
    val previous = navigation.previous
    val next = navigation.next

    Canvas(
        modifier = modifier
            .onSizeChanged { size = it }
            .semantics {
                contentDescription = summary
                selected?.let {
                    stateDescription = "${it.series.name}, ${it.axis.label}, ${formatting.value(it.value.value)}"
                }
                onClick(label = selected?.axis?.label ?: configuration.accessibilityLabel) {
                    (selected ?: configuration.selections.firstOrNull())?.let(::select) ?: false
                }
                customActions = listOfNotNull(
                    previous?.let {
                        CustomAccessibilityAction("${it.series.name}, ${it.axis.label}, ${formatting.value(it.value.value)}") {
                            moveSelection(-1)
                        }
                    },
                    next?.let {
                        CustomAccessibilityAction("${it.series.name}, ${it.axis.label}, ${formatting.value(it.value.value)}") {
                            moveSelection(1)
                        }
                    },
                )
            }
            .pointerInput(configuration.selections, size) {
                detectTapGestures { location ->
                    selectedId = nativePHPChartsRadarSelectedIdAfterTap(
                        selections = radarSelections(configuration, size, 1f),
                        location = location,
                        threshold = 44.dp.toPx(),
                    )
                    configuration.selections.firstOrNull { it.id == selectedId }?.let(::select)
                }
            },
    ) {
        val geometry = radarGeometry(size, configuration.axes)
        val plotted = radarSelections(configuration, size, progress.value)
        val gridColor = chartColor(configuration.style.gridColor, Color.Gray.copy(alpha = 0.2f))
        val axisColor = chartColor(configuration.style.axisColor, Color.Gray.copy(alpha = 0.28f))
        labelPaint.color = chartColor(configuration.style.axisLabelColor, axisColor).toArgb()

        if (configuration.style.gridVisible) {
            for (level in 1..configuration.gridLevels) {
                val vertices = configuration.axes.indices.map {
                    geometry.point(it, level.toDouble() / configuration.gridLevels, progress.value)
                }
                drawPath(radarPath(vertices, "linear"), gridColor, style = Stroke(configuration.style.gridWidth.dp.toPx()))
            }
        }
        if (configuration.style.axisVisible) {
            configuration.axes.indices.forEach { index ->
                val outer = geometry.point(index, 1.0, progress.value)
                drawLine(axisColor, geometry.center, outer, 1.dp.toPx())
                nativePHPChartsRadarAxisDisplayLabel(
                    label = configuration.axes[index].label,
                    index = index,
                    axisCount = configuration.axes.size,
                )?.let { displayLabel ->
                    val label = geometry.point(index, 1.16, progress.value)
                    drawContext.canvas.nativeCanvas.drawText(
                        displayLabel,
                        label.x,
                        label.y - labelPaint.fontMetrics.ascent / 2,
                        labelPaint,
                    )
                }
            }
        }

        configuration.series.forEach { series ->
            val points = plotted.filter { it.first.series.id == series.id }.map { it.second }
            val path = radarPath(points, configuration.style.interpolation)
            val lineColor = chartColor(configuration.style.lineColor.takeIf { configuration.series.size == 1 }, series.color)
            if (configuration.style.areaGradient) {
                drawPath(
                    path,
                    brush = Brush.linearGradient(
                        listOf(
                            lineColor.copy(alpha = configuration.style.areaOpacity),
                            lineColor.copy(alpha = configuration.style.areaOpacity * 0.35f),
                        ),
                    ),
                )
            } else {
                drawPath(path, lineColor.copy(alpha = configuration.style.areaOpacity), style = Fill)
            }
            drawPath(
                path,
                lineColor,
                style = Stroke(
                    width = configuration.style.lineWidth.dp.toPx(),
                    pathEffect = configuration.style.dash.takeIf { it.size >= 2 }
                        ?.map { it.dp.toPx() }
                        ?.toFloatArray()
                        ?.let { PathEffect.dashPathEffect(it) },
                ),
            )
            if (configuration.style.pointsVisible) {
                val pointColor = chartColor(configuration.style.pointColor.takeIf { configuration.series.size == 1 }, lineColor)
                points.forEach { drawCircle(pointColor, configuration.style.pointSize.dp.toPx() / 2, it) }
            }
        }

        selected?.let { selection ->
            plotted.firstOrNull { it.first.id == selection.id }?.second?.let { selectedPoint ->
                drawCircle(Color.White, 7.dp.toPx(), selectedPoint)
                drawCircle(selection.series.color, 5.dp.toPx(), selectedPoint)
                val text = "${selection.axis.label} · ${formatting.value(selection.value.value)}"
                nativePHPChartsRadarTooltipLayout(
                    canvas = this.size,
                    anchor = selectedPoint,
                    measuredTextWidth = tooltipPaint.measureText(text),
                    requestedHeight = 26.dp.toPx(),
                    horizontalPadding = 18.dp.toPx(),
                    margin = 4.dp.toPx(),
                    verticalOffset = 38.dp.toPx(),
                )?.let { tooltip ->
                    drawRoundRect(
                        Color.Black.copy(alpha = 0.84f),
                        topLeft = Offset(tooltip.left, tooltip.top),
                        size = Size(tooltip.width, tooltip.height),
                        cornerRadius = androidx.compose.ui.geometry.CornerRadius(tooltip.height / 2),
                    )
                    if (tooltip.drawsText) {
                        val baseline = tooltip.top +
                            (tooltip.height - (tooltipPaint.fontMetrics.descent + tooltipPaint.fontMetrics.ascent)) / 2
                        drawContext.canvas.nativeCanvas.drawText(
                            text,
                            tooltip.left + (tooltip.width / 2),
                            baseline,
                            tooltipPaint,
                        )
                    }
                }
            }
        }
    }
}

@Composable
private fun NativePHPChartsRadarLegend(configuration: NativePHPChartsRadarConfiguration, horizontal: Boolean) {
    val legend = configuration.legend
    if (horizontal) {
        val alignment = when (legend.alignment) {
            "start", "leading" -> Alignment.CenterStart
            "end", "trailing" -> Alignment.CenterEnd
            else -> Alignment.Center
        }
        Box(Modifier.fillMaxWidth().heightIn(max = 72.dp), contentAlignment = alignment) {
            Row(Modifier.horizontalScroll(rememberScrollState()).padding(horizontal = 8.dp, vertical = 6.dp)) {
                configuration.series.forEach { NativePHPChartsRadarLegendItem(it, configuration) }
            }
        }
    } else {
        val alignment = when (legend.alignment) {
            "end", "trailing" -> Alignment.End
            "center" -> Alignment.CenterHorizontally
            else -> Alignment.Start
        }
        Column(
            Modifier.widthIn(max = 160.dp).verticalScroll(rememberScrollState()).padding(horizontal = 8.dp, vertical = 12.dp),
            horizontalAlignment = alignment,
        ) {
            configuration.series.forEach { NativePHPChartsRadarLegendItem(it, configuration) }
        }
    }
}

@Composable
private fun NativePHPChartsRadarLegendItem(
    series: NativePHPChartsRadarSeries,
    configuration: NativePHPChartsRadarConfiguration,
) {
    val legend = configuration.legend
    val context = LocalContext.current
    val fontFamily = remember(context, legend.font) { legend.font?.let { NativeUIFontResolver.resolve(context, it) } }
    Row(Modifier.widthIn(max = 160.dp).padding(horizontal = 6.dp, vertical = 3.dp)) {
        val markerColor = chartColor(configuration.style.lineColor.takeIf { configuration.series.size == 1 }, series.color)
        Box(Modifier.padding(top = 4.dp, end = 6.dp).size(legend.markerSize.dp).background(markerColor, CircleShape))
        Text(
            series.name,
            modifier = Modifier.widthIn(max = 132.dp),
            color = chartColor(legend.labelColor, MaterialTheme.colorScheme.onSurfaceVariant),
            fontSize = legend.fontSize.sp,
            fontFamily = fontFamily,
            maxLines = 1,
            overflow = TextOverflow.Ellipsis,
        )
    }
}

private data class NativePHPChartsRadarGeometry(val center: Offset, val radius: Float, val count: Int) {
    fun point(index: Int, ratio: Double, progress: Float): Offset {
        val angle = -PI / 2 + (2 * PI * index / count)
        val resolvedRadius = radius * ratio.toFloat() * progress
        return center + Offset((cos(angle) * resolvedRadius).toFloat(), (sin(angle) * resolvedRadius).toFloat())
    }
}

private fun radarGeometry(size: IntSize, axes: List<NativePHPChartsRadarAxis>): NativePHPChartsRadarGeometry =
    NativePHPChartsRadarGeometry(Offset(size.width / 2f, size.height / 2f), min(size.width, size.height) * 0.34f, axes.size.coerceAtLeast(1))

private fun radarSelections(
    configuration: NativePHPChartsRadarConfiguration,
    size: IntSize,
    progress: Float,
): List<Pair<NativePHPChartsRadarSelection, Offset>> {
    val geometry = radarGeometry(size, configuration.axes)
    return configuration.selections.map {
        it to geometry.point(it.index, it.value.value / it.axis.maximum, progress)
    }
}

internal fun nativePHPChartsRadarStepVertices(points: List<Offset>, interpolation: String): List<Offset>? {
    if (points.size < 3 || (interpolation != "step_before" && interpolation != "step_after")) return null
    val first = points.first()
    val vertices = mutableListOf(first)
    var previous = first
    (points.drop(1) + first).forEach { point ->
        vertices += if (interpolation == "step_before") {
            Offset(point.x, previous.y)
        } else {
            Offset(previous.x, point.y)
        }
        vertices += point
        previous = point
    }
    return vertices
}

private fun radarPath(points: List<Offset>, interpolation: String): Path = Path().apply {
    if (points.size < 3) return@apply
    moveTo(points.first().x, points.first().y)
    when (interpolation) {
        "smooth" -> {
            val closed = listOf(points.last()) + points + listOf(points[0], points[1])
            for (index in 1..points.size) {
                val p0 = closed[index - 1]
                val p1 = closed[index]
                val p2 = closed[index + 1]
                val p3 = closed[index + 2]
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
        "step_before", "step_after" -> nativePHPChartsRadarStepVertices(points, interpolation)
            ?.drop(1)
            ?.forEach { lineTo(it.x, it.y) }
        else -> points.drop(1).forEach { lineTo(it.x, it.y) }
    }
    close()
}

private fun decodeRadarConfiguration(node: NativeUINode): NativePHPChartsRadarConfiguration {
    val props = node.props
    val axes = radarArray(props.getString("axes_json", "[]")) { item, _ ->
        NativePHPChartsRadarAxis(item.getString("id"), item.getString("label"), item.getDouble("maximum"))
    }
    val series = radarArray(props.getString("series_json", "[]")) { item, _ ->
        NativePHPChartsRadarSeries(
            id = item.getString("id"),
            name = item.getString("name"),
            color = chartColor(item.getString("color"), Color(0xFF6366F1)),
            values = radarArray(item.getJSONArray("values")) { value, _ ->
                NativePHPChartsRadarValue(value.getString("axis"), value.getDouble("value"))
            },
        )
    }
    val styleRoot = radarObject(props.getString("style_json", "{}"))
    val line = styleRoot.optJSONObject("line")
    val area = styleRoot.optJSONObject("area")
    val points = styleRoot.optJSONObject("points")
    val grid = styleRoot.optJSONObject("grid")
    val axis = styleRoot.optJSONObject("axis")
    val legendRoot = radarObject(props.getString("legend_json", "{}"))
    val legendStyle = legendRoot.optJSONObject("style")
    val fillOpacity = area?.optDouble("opacity", props.getFloat("fill_opacity", 0.22f).toDouble())?.toFloat()
        ?: props.getFloat("fill_opacity", 0.22f)

    return NativePHPChartsRadarConfiguration(
        axes = axes,
        series = series,
        style = NativePHPChartsRadarStyle(
            lineColor = line?.optString("color")?.takeIf(String::isNotBlank),
            lineWidth = line?.optDouble("width", 2.0)?.toFloat() ?: 2f,
            interpolation = line?.optString("interpolation", "linear") ?: "linear",
            dash = line?.optJSONArray("dash")?.let { values ->
                (0 until values.length()).mapNotNull { values.optDouble(it).takeIf(Double::isFinite)?.toFloat() }
            } ?: emptyList(),
            areaOpacity = fillOpacity.coerceIn(0f, 1f),
            areaGradient = area?.optBoolean("gradient", false) ?: false,
            pointsVisible = points?.optBoolean("visible", true) ?: true,
            pointColor = points?.optString("color")?.takeIf(String::isNotBlank),
            pointSize = points?.optDouble("size", 7.0)?.toFloat() ?: 7f,
            gridVisible = grid?.optBoolean("visible", true) ?: true,
            gridColor = grid?.optString("color")?.takeIf(String::isNotBlank),
            gridWidth = grid?.optDouble("width", 1.0)?.toFloat() ?: 1f,
            axisVisible = axis?.optBoolean("visible", true) ?: true,
            axisColor = axis?.optString("color")?.takeIf(String::isNotBlank),
            axisLabelColor = axis?.optString("label_color")?.takeIf(String::isNotBlank),
            axisFont = axis?.optString("font")?.takeIf(String::isNotBlank),
            axisFontSize = axis?.optDouble("font_size", 10.0)?.toFloat() ?: 10f,
        ),
        legend = NativePHPChartsLegend(
            visible = if (legendRoot.has("visible")) legendRoot.optBoolean("visible") else null,
            position = legendRoot.optString("position", "bottom"),
            alignment = legendRoot.optString("alignment", "center"),
            markerSize = legendStyle?.optDouble("marker_size", 9.0)?.toFloat() ?: 9f,
            fontSize = legendStyle?.optDouble("font_size", 11.0)?.toFloat() ?: 11f,
            font = legendStyle?.optString("font")?.takeIf(String::isNotBlank),
            labelColor = legendStyle?.optString("label_color")?.takeIf(String::isNotBlank),
        ),
        locale = props.getString("locale", ""),
        valueFormat = props.getString("value_format", "number"),
        currencyCode = props.getString("currency_code", ""),
        minimumFractionDigits = props.getInt("minimum_fraction_digits", -1),
        maximumFractionDigits = props.getInt("maximum_fraction_digits", -1),
        animated = props.getBool("animated", true),
        emptyLabel = props.getString("empty_label", "No data"),
        accessibilityLabel = props.getString("a11y_label", "Chart"),
        onSelect = props.getCallbackId("on_select"),
        gridLevels = props.getInt("grid_levels", 5).coerceIn(2, 10),
    )
}

private fun radarObject(json: String): JSONObject = runCatching { JSONObject(json) }.getOrDefault(JSONObject())

private fun <Value : Any> radarArray(
    json: String,
    transform: (JSONObject, Int) -> Value?,
): List<Value> = runCatching { radarArray(JSONArray(json), transform) }.getOrElse {
    emptyList()
}

private fun <Value : Any> radarArray(
    array: JSONArray,
    transform: (JSONObject, Int) -> Value?,
): List<Value> = buildList {
    for (index in 0 until array.length()) array.optJSONObject(index)?.let { transform(it, index) }?.let(::add)
}
