package com.donmanueldev.plugins.nativephp_charts.ui

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
import com.nativephp.mobile.ui.nativerender.NativeUINode

@Composable
internal fun NativePHPChartsRadialPlot(
    node: NativeUINode,
    configuration: NativePHPChartsRadialConfiguration,
    formatting: NativePHPChartsRadialFormatting,
    modifier: Modifier,
) {
    val density = LocalDensity.current.density
    val context = LocalContext.current
    val animationsEnabled = remember(context) { nativePHPChartsAnimationsEnabled(context) }
    val shouldAnimate = configuration.animated && animationsEnabled
    var canvasSize by remember { mutableStateOf(IntSize.Zero) }
    var selectedId by remember { mutableStateOf<String?>(null) }
    val layout = remember(configuration, canvasSize, density) {
        NativePHPChartsRadialLayoutEngine.build(configuration, canvasSize, density)
    }
    val selected = layout.data.firstOrNull { it.segment.id == selectedId }
    val progress = remember { Animatable(if (shouldAnimate) 0f else 1f) }
    LaunchedEffect(configuration.animationKey, shouldAnimate) {
        if (shouldAnimate) {
            progress.snapTo(0f)
            progress.animateTo(1f, tween(durationMillis = 560))
        } else {
            progress.snapTo(1f)
        }
    }
    LaunchedEffect(layout.data, selectedId) {
        if (selectedId != null && selected == null) selectedId = null
    }

    fun select(datum: NativePHPChartsRadialDatum) {
        selectedId = datum.segment.id
        NativePHPChartsRadialSelection.dispatch(node, configuration, formatting, datum.segment)
    }

    fun moveSelection(offset: Int): Boolean {
        if (layout.data.isEmpty()) return false
        val current = layout.data.indexOfFirst { it.segment.id == selectedId }
        val target = when {
            current < 0 && offset > 0 -> 0
            current < 0 -> layout.data.lastIndex
            else -> current + offset
        }
        val datum = layout.data.getOrNull(target) ?: return false
        select(datum)
        return true
    }

    val summary = remember(configuration, formatting) {
        buildString {
            append(configuration.accessibilityLabel)
            configuration.segments.take(8).forEach { segment ->
                append(". ").append(segment.label).append(": ").append(formatting.value(segment.value))
            }
            if (configuration.segments.size > 8) append(". … (+").append(configuration.segments.size - 8).append(')')
        }
    }
    val selectedIndex = layout.data.indexOfFirst { it.segment.id == selectedId }
    val previous = layout.data.getOrNull(if (selectedIndex < 0) layout.data.lastIndex else selectedIndex - 1)
    val next = layout.data.getOrNull(if (selectedIndex < 0) 0 else selectedIndex + 1)

    Canvas(
        modifier = modifier
            .onSizeChanged { canvasSize = it }
            .semantics {
                contentDescription = summary
                selected?.let { datum ->
                    stateDescription = "${datum.segment.label}, ${formatting.value(datum.segment.value)}"
                }
                onClick(label = selected?.segment?.label ?: configuration.accessibilityLabel) {
                    val target = selected ?: layout.data.firstOrNull()
                    target?.let(::select)
                    target != null
                }
                customActions = listOfNotNull(
                    previous?.let { datum ->
                        CustomAccessibilityAction(
                            "${datum.segment.label}, ${formatting.value(datum.segment.value)}",
                        ) { moveSelection(-1) }
                    },
                    next?.let { datum ->
                        CustomAccessibilityAction(
                            "${datum.segment.label}, ${formatting.value(datum.segment.value)}",
                        ) { moveSelection(1) }
                    },
                ).distinctBy { it.label }
            }
            .pointerInput(layout, configuration.onSelect) {
                detectTapGestures { location -> layout.segmentAt(location)?.let(::select) }
            },
    ) {
        val revealAngle = 360f * progress.value
        layout.data.forEach { datum ->
            val visibleSweep = (revealAngle - (datum.startAngle + 90f)).coerceIn(0f, datum.sweepAngle)
            if (visibleSweep <= 0f) return@forEach
            val path = if (progress.value == 1f) {
                datum.path
            } else {
                nativePHPChartsRadialPath(
                    center = layout.center,
                    outerRadius = layout.outerRadius,
                    innerRadius = layout.innerRadius,
                    startAngle = datum.startAngle,
                    sweepAngle = visibleSweep,
                    requestedCornerRadius = configuration.style.cornerRadius * density,
                )
            }
            drawPath(path, datum.segment.color.copy(alpha = configuration.style.opacity))
            if (datum.segment.id == selectedId) {
                drawPath(path, Color.White.copy(alpha = 0.18f))
            }
        }
    }
}
