package com.donmanueldev.plugins.nativephp_charts.ui

import android.graphics.Paint
import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.tween
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.gestures.detectTapGestures
import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Rect
import androidx.compose.ui.graphics.drawscope.Fill
import androidx.compose.ui.graphics.nativeCanvas
import androidx.compose.ui.input.pointer.pointerInput
import androidx.compose.ui.layout.onSizeChanged
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalDensity
import androidx.compose.ui.semantics.CustomAccessibilityAction
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.customActions
import androidx.compose.ui.semantics.onClick
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.semantics.stateDescription
import androidx.compose.ui.unit.IntSize
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.nativephp.mobile.ui.nativerender.NativeUIBridge
import com.nativephp.mobile.ui.nativerender.NativeUINode
import org.json.JSONObject
import java.text.NumberFormat
import java.time.LocalDate
import java.time.format.TextStyle
import java.util.Locale
import kotlin.math.ceil
import kotlin.math.max

internal data class NativePHPChartsContributionCell(
    val date: LocalDate,
    val rect: Rect,
    val value: NativePHPChartsContributionValue?,
    val column: Int,
    val row: Int,
)

internal data class NativePHPChartsContributionLayout(
    val cells: List<NativePHPChartsContributionCell>,
    val columns: Int,
) {
    fun cellAt(location: Offset): NativePHPChartsContributionCell? = cells.firstOrNull { it.rect.contains(location) }
}

internal fun nativePHPChartsContributionLayout(
    configuration: NativePHPChartsContributionConfiguration,
    size: IntSize,
    density: Float,
): NativePHPChartsContributionLayout {
    if (size.width <= 0 || size.height <= 0) return NativePHPChartsContributionLayout(emptyList(), 0)
    val startOffset = ((configuration.startDate.dayOfWeek.value % 7) - configuration.weekStartsOn + 7) % 7
    val columns = ceil((startOffset + configuration.days) / 7.0).toInt().coerceAtLeast(1)
    val left = if (configuration.showWeekdayLabels) 30f * density else 0f
    val top = if (configuration.showMonthLabels) 20f * density else 0f
    val gap = 3f * density
    val cell = minOf(
        (size.width - left - gap * (columns - 1)).coerceAtLeast(1f) / columns,
        (size.height - top - gap * 6).coerceAtLeast(1f) / 7f,
    ).coerceAtLeast(1f)
    val byDate = configuration.visibleValues.associateBy { it.date }
    val cells = (0 until configuration.days).map { dayIndex ->
        val slot = startOffset + dayIndex
        val column = slot / 7
        val row = slot % 7
        val date = configuration.startDate.plusDays(dayIndex.toLong())
        val origin = Offset(left + column * (cell + gap), top + row * (cell + gap))
        NativePHPChartsContributionCell(
            date = date,
            rect = Rect(origin, androidx.compose.ui.geometry.Size(cell, cell)),
            value = byDate[date],
            column = column,
            row = row,
        )
    }
    return NativePHPChartsContributionLayout(cells, columns)
}

@Composable
internal fun NativePHPChartsContributionRender(node: NativeUINode, modifier: Modifier) {
    val input = NativePHPChartsContributionWireInput.from(node)
    val systemDark = isSystemInDarkTheme()
    val decoded = rememberNativePHPChartsDecodedState(input to systemDark, "contribution_heatmap") {
        decodeNativePHPChartsContribution(it.first, it.second)
    }
    when (decoded) {
        NativePHPChartsAsyncState.Loading -> return
        is NativePHPChartsAsyncState.Unavailable -> {
            NativePHPChartsUnavailable(modifier, input.accessibilityLabel, input.errorLabel)
            return
        }
        is NativePHPChartsAsyncState.Ready -> Unit
    }
    val configuration = (decoded as NativePHPChartsAsyncState.Ready).value
    if (configuration.values.isEmpty()) {
        NativePHPChartsEmpty(modifier, configuration.accessibilityLabel, configuration.emptyLabel)
        return
    }
    NativePHPChartsContributionPlot(node, configuration, modifier)
}

@Composable
private fun NativePHPChartsContributionPlot(
    node: NativeUINode,
    configuration: NativePHPChartsContributionConfiguration,
    modifier: Modifier,
) {
    val density = LocalDensity.current
    val context = LocalContext.current
    val shouldAnimate = configuration.animated && remember(context) { nativePHPChartsAnimationsEnabled(context) }
    val progress = remember { Animatable(if (shouldAnimate) 0f else 1f) }
    val interactionReady = progress.value >= 0.999f
    var canvasSize by remember { mutableStateOf(IntSize.Zero) }
    var selectedId by remember { mutableStateOf<String?>(null) }
    val layout = remember(configuration, canvasSize, density.density) {
        nativePHPChartsContributionLayout(configuration, canvasSize, density.density)
    }
    val selected = configuration.visibleValues.firstOrNull { it.id == selectedId }
    val formatter = remember { NumberFormat.getNumberInstance() }
    val labelPaint = remember(density) {
        Paint(Paint.ANTI_ALIAS_FLAG).apply {
            color = android.graphics.Color.GRAY
            textSize = with(density) { 10.sp.toPx() }
        }
    }
    LaunchedEffect(configuration.animationKey, shouldAnimate) {
        if (shouldAnimate) {
            progress.snapTo(0f)
            progress.animateTo(1f, tween(520))
        } else progress.snapTo(1f)
    }
    LaunchedEffect(configuration.visibleValues, selectedId) {
        if (selectedId != null && selected == null) selectedId = null
    }

    fun select(value: NativePHPChartsContributionValue): Boolean {
        selectedId = value.id
        if (configuration.onSelect > 0) {
            val payload = nativePHPChartsSpecialSelectionPayload(
                "contribution_heatmap", value.id, value.label, value.index,
                value.date.toString(), value.value, formatter.format(value.value),
                xType = "date",
            )
            NativeUIBridge.sendTextChangeEvent(configuration.onSelect, node.id, JSONObject(payload).toString())
        }
        return true
    }

    val ordered = configuration.visibleValues.sortedBy { it.date }
    val selectedIndex = ordered.indexOfFirst { it.id == selectedId }
    val previous = ordered.getOrNull(selectedIndex - 1)
    val next = ordered.getOrNull(if (selectedIndex < 0) 0 else selectedIndex + 1)
    val summary = remember(configuration) {
        buildString {
            append(configuration.accessibilityLabel)
            ordered.take(18).forEach { append(". ${it.label}: ${formatter.format(it.value)}") }
            if (ordered.size > 18) append(". (+${ordered.size - 18})")
        }
    }
    val maximum = max(configuration.visibleValues.maxOfOrNull { it.value } ?: 0.0, 1.0)

    Canvas(
        modifier
            .onSizeChanged { canvasSize = it }
            .semantics {
                contentDescription = summary
                selected?.let { stateDescription = "${it.label}, ${formatter.format(it.value)}" }
                onClick(label = selected?.label ?: configuration.accessibilityLabel) {
                    (selected ?: ordered.firstOrNull())?.let(::select) ?: false
                }
                customActions = listOfNotNull(
                    previous?.let { CustomAccessibilityAction("${it.label}, ${formatter.format(it.value)}") { select(it) } },
                    next?.let { CustomAccessibilityAction("${it.label}, ${formatter.format(it.value)}") { select(it) } },
                )
            }
            .pointerInput(layout, interactionReady) {
                detectTapGestures { location ->
                    if (!interactionReady) return@detectTapGestures
                    layout.cellAt(location)?.value?.let(::select)
                }
            },
    ) {
        val visibleColumns = ceil(layout.columns * progress.value).toInt()
        layout.cells.filter { it.column < visibleColumns }.forEach { cell ->
            val value = cell.value
            val color = if (value == null || value.value <= 0.0) {
                configuration.emptyColor
            } else {
                val fraction = (value.value / maximum).coerceIn(0.0, 1.0)
                val index = ceil(fraction * configuration.colors.size).toInt().coerceIn(1, configuration.colors.size) - 1
                configuration.colors[index]
            }
            drawRoundRect(color, cell.rect.topLeft, cell.rect.size, androidx.compose.ui.geometry.CornerRadius(2.dp.toPx()), style = Fill)
            if (value?.id == selectedId) {
                drawRoundRect(
                    color = androidx.compose.ui.graphics.Color.White,
                    topLeft = cell.rect.topLeft,
                    size = cell.rect.size,
                    cornerRadius = androidx.compose.ui.geometry.CornerRadius(2.dp.toPx()),
                    style = androidx.compose.ui.graphics.drawscope.Stroke(2.dp.toPx()),
                )
            }
        }
        if (configuration.showWeekdayLabels) {
            val rows = listOf(0, 2, 4, 6)
            rows.forEach { row ->
                val date = configuration.startDate.plusDays(((row - (((configuration.startDate.dayOfWeek.value % 7) - configuration.weekStartsOn + 7) % 7) + 7) % 7).toLong())
                val cell = layout.cells.firstOrNull { it.row == row } ?: return@forEach
                drawContext.canvas.nativeCanvas.drawText(
                    date.dayOfWeek.getDisplayName(TextStyle.NARROW, Locale.getDefault()),
                    2.dp.toPx(), cell.rect.center.y - labelPaint.fontMetrics.ascent / 2, labelPaint,
                )
            }
        }
        if (configuration.showMonthLabels) {
            layout.cells.filter { it.row == 0 }.distinctBy { it.date.month }.forEach { cell ->
                drawContext.canvas.nativeCanvas.drawText(
                    cell.date.month.getDisplayName(TextStyle.SHORT, Locale.getDefault()),
                    cell.rect.left, 12.dp.toPx(), labelPaint,
                )
            }
        }
    }
}
