package com.donmanueldev.plugins.nativephp_charts.ui

import android.graphics.Paint
import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.tween
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.gestures.detectTapGestures
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.input.pointer.pointerInput
import androidx.compose.ui.layout.onSizeChanged
import androidx.compose.ui.platform.LocalDensity
import androidx.compose.ui.semantics.CustomAccessibilityAction
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.customActions
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.semantics.stateDescription
import androidx.compose.ui.unit.IntSize
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.nativephp.mobile.ui.nativerender.NativeUINode

@Composable
internal fun NativePHPChartsPlot(
    node: NativeUINode,
    configuration: NativePHPChartsConfiguration,
    formatting: NativePHPChartsFormatting,
    modifier: Modifier,
) {
    val localDensity = LocalDensity.current
    val density = localDensity.density
    val context = androidx.compose.ui.platform.LocalContext.current
    val animationsEnabled = remember(context) { nativePHPChartsAnimationsEnabled(context) }
    val shouldAnimate = configuration.animated && animationsEnabled
    val typeface = remember(context, configuration.style.axisFont) {
        resolveNativePHPChartsTypeface(context, configuration.style.axisFont)
    }
    val drawingResources = remember(localDensity, typeface, configuration) {
        val axisColor = chartColor(configuration.style.axisColor, Color.Gray.copy(alpha = 0.6f))
        val lineColors = configuration.series.associate { series ->
            series.id to chartColor(
                configuration.style.lineColor.takeIf { configuration.series.size == 1 },
                series.color,
            )
        }
        NativePHPChartsDrawingResources(
            axisLabelPaint = Paint(Paint.ANTI_ALIAS_FLAG).apply {
                textSize = with(localDensity) { configuration.style.axisFontSize.sp.toPx() }
                this.typeface = typeface
            },
            tooltipPaint = Paint(Paint.ANTI_ALIAS_FLAG).apply {
                color = android.graphics.Color.WHITE
                textSize = with(localDensity) { 11.sp.toPx() }
                textAlign = Paint.Align.CENTER
            },
            axisColor = axisColor,
            axisLabelColor = chartColor(configuration.style.axisLabelColor, axisColor),
            gridColor = chartColor(configuration.style.gridColor, Color.Gray.copy(alpha = 0.18f)),
            lineStroke = Stroke(
                width = with(localDensity) { configuration.style.lineWidth.dp.toPx() },
                cap = StrokeCap.Round,
            ),
            lineColors = lineColors,
            pointColors = configuration.series.associate { series ->
                val lineColor = lineColors.getValue(series.id)
                series.id to chartColor(
                    configuration.style.pointColor.takeIf { configuration.series.size == 1 },
                    lineColor,
                )
            },
        )
    }
    var canvasSize by remember { mutableStateOf(IntSize.Zero) }
    var selectedIdentity by remember { mutableStateOf<NativePHPChartsSelectionIdentity?>(null) }
    val animationKey = remember(configuration) { configuration.animationKey }
    val progress = remember { Animatable(if (shouldAnimate) 0f else 1f) }
    LaunchedEffect(animationKey, shouldAnimate) {
        if (shouldAnimate) {
            progress.snapTo(0f)
            progress.animateTo(1f, tween(durationMillis = 460))
        } else {
            progress.snapTo(1f)
        }
    }
    val summary = remember(configuration, formatting) { configuration.accessibilitySummary(formatting) }
    val layout = remember(configuration, formatting, canvasSize, density) {
        NativePHPChartsLayoutEngine.build(configuration, formatting, canvasSize, density)
    }
    val pathCache = remember(layout, configuration.style.smooth, configuration.kind) {
        if (configuration.kind == NativePHPChartsKind.Line || configuration.kind == NativePHPChartsKind.Area) {
            NativePHPChartsPathCache.build(
                layout = layout,
                smooth = configuration.style.smooth,
                includeArea = configuration.kind == NativePHPChartsKind.Area,
            )
        } else {
            null
        }
    }
    val selected = layout.data.firstOrNull { it.selectionIdentity == selectedIdentity }
    LaunchedEffect(layout.data, selectedIdentity) {
        if (selectedIdentity != null && selected == null) {
            selectedIdentity = null
        }
    }

    fun select(datum: NativePHPChartsDatum) {
        selectedIdentity = datum.selectionIdentity
        NativePHPChartsSelection.dispatch(node, configuration, formatting, datum)
    }

    fun selectionTarget(offset: Int): NativePHPChartsDatum? {
        if (layout.data.isEmpty()) return null
        val current = layout.data.indexOfFirst { it.selectionIdentity == selectedIdentity }
        val start = if (current < 0) {
            if (offset > 0) -1 else layout.data.size
        } else {
            current
        }
        val target = start + offset
        return layout.data.getOrNull(target)
    }

    fun moveSelection(offset: Int): Boolean {
        val target = selectionTarget(offset) ?: return false
        select(target)
        return true
    }

    val previous = selectionTarget(-1)
    val next = selectionTarget(1)

    fun actionLabel(datum: NativePHPChartsDatum): String = listOf(
        datum.series.name,
        formatting.x(datum.point),
        formatting.value(datum.point.value),
    ).joinToString(", ")

    Canvas(
        modifier = modifier
            .onSizeChanged { canvasSize = it }
            .semantics {
                contentDescription = summary
                selected?.let { datum ->
                    stateDescription = "${datum.series.name}, ${datum.point.label}, ${formatting.value(datum.point.value)}"
                }
                customActions = listOfNotNull(
                    previous?.let { datum ->
                        CustomAccessibilityAction(actionLabel(datum)) { moveSelection(-1) }
                    },
                    next?.let { datum ->
                        CustomAccessibilityAction(actionLabel(datum)) { moveSelection(1) }
                    },
                ).distinctBy { it.label }
            }
            .pointerInput(configuration, layout) {
                detectTapGestures { location ->
                    layout.nearest(location, 32f * density)?.let(::select)
                }
            },
    ) {
        drawNativePHPChartsAxes(configuration, layout, drawingResources)
        when (configuration.kind) {
            NativePHPChartsKind.Line -> drawNativePHPChartsLines(
                configuration, layout, progress.value, false, drawingResources, requireNotNull(pathCache),
            )
            NativePHPChartsKind.Area -> drawNativePHPChartsLines(
                configuration, layout, progress.value, true, drawingResources, requireNotNull(pathCache),
            )
            NativePHPChartsKind.Bar -> drawNativePHPChartsBars(configuration, layout, progress.value)
            NativePHPChartsKind.Scatter -> drawNativePHPChartsScatter(
                configuration, layout, progress.value, drawingResources,
            )
        }
        selected?.let { drawNativePHPChartsSelection(it, formatting, layout, drawingResources) }
    }
}
