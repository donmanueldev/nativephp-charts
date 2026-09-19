package com.donmanueldev.plugins.nativephp_charts.ui

import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.tween
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
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
import androidx.compose.ui.geometry.Rect
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.input.pointer.pointerInput
import androidx.compose.foundation.gestures.detectTapGestures
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalDensity
import androidx.compose.ui.semantics.CustomAccessibilityAction
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.customActions
import androidx.compose.ui.semantics.onClick
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.semantics.stateDescription
import androidx.compose.ui.unit.dp
import com.nativephp.mobile.ui.nativerender.NativeUIBridge
import com.nativephp.mobile.ui.nativerender.NativeUINode
import org.json.JSONObject
import java.text.NumberFormat
import java.util.Locale
import kotlin.math.abs
import kotlin.math.min

@Composable
internal fun NativePHPChartsProgressRender(node: NativeUINode, modifier: Modifier) {
    val input = NativePHPChartsProgressWireInput.from(node)
    val systemDark = isSystemInDarkTheme()
    val decoded = rememberNativePHPChartsDecodedState(input to systemDark, "progress") {
        decodeNativePHPChartsProgress(it.first, it.second)
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
    if (configuration.metrics.isEmpty()) {
        NativePHPChartsEmpty(modifier, configuration.accessibilityLabel, configuration.emptyLabel)
        return
    }
    NativePHPChartsProgressPlot(node, configuration, modifier)
}

@Composable
private fun NativePHPChartsProgressPlot(
    node: NativeUINode,
    configuration: NativePHPChartsProgressConfiguration,
    modifier: Modifier,
) {
    val density = LocalDensity.current.density
    val context = LocalContext.current
    val shouldAnimate = configuration.animated && remember(context) { nativePHPChartsAnimationsEnabled(context) }
    val progress = remember { Animatable(if (shouldAnimate) 0f else 1f) }
    val interactionReady = progress.value >= 0.999f
    var selectedId by remember { mutableStateOf<String?>(null) }
    val selected = configuration.metrics.firstOrNull { it.id == selectedId }
    val percent = remember(configuration.locale) { NumberFormat.getPercentInstance(configuration.locale.takeIf(String::isNotBlank)?.let(Locale::forLanguageTag) ?: Locale.getDefault()) }
    LaunchedEffect(configuration.animationKey, shouldAnimate) {
        if (shouldAnimate) {
            progress.snapTo(0f)
            progress.animateTo(1f, tween(500))
        } else progress.snapTo(1f)
    }
    LaunchedEffect(configuration.metrics, selectedId) {
        if (selectedId != null && selected == null) selectedId = null
    }

    fun select(metric: NativePHPChartsProgressMetric): Boolean {
        selectedId = metric.id
        if (configuration.onSelect > 0) {
            val payload = nativePHPChartsSpecialSelectionPayload(
                "progress", metric.id, metric.label, metric.index, metric.label,
                metric.value, percent.format(metric.value),
            )
            NativeUIBridge.sendTextChangeEvent(configuration.onSelect, node.id, JSONObject(payload).toString())
        }
        return true
    }

    val summary = remember(configuration) {
        configuration.accessibilityLabel + configuration.metrics.joinToString(separator = ". ", prefix = ". ") {
            "${it.label}: ${percent.format(it.value)}"
        }
    }
    val selectedIndex = configuration.metrics.indexOfFirst { it.id == selectedId }
    val previous = configuration.metrics.getOrNull(selectedIndex - 1)
    val next = configuration.metrics.getOrNull(if (selectedIndex < 0) 0 else selectedIndex + 1)

    Column(modifier.fillMaxSize().background(configuration.backgroundColor)) {
    Box(Modifier.weight(1f), contentAlignment = Alignment.Center) {
        Canvas(
            Modifier.fillMaxSize()
                .semantics {
                    contentDescription = summary
                    selected?.let { stateDescription = "${it.label}, ${percent.format(it.value)}" }
                    onClick(label = selected?.label ?: configuration.accessibilityLabel) {
                        (selected ?: configuration.metrics.firstOrNull())?.let(::select) ?: false
                    }
                    customActions = listOfNotNull(
                        previous?.let { CustomAccessibilityAction("${it.label}, ${percent.format(it.value)}") { select(it) } },
                        next?.let { CustomAccessibilityAction("${it.label}, ${percent.format(it.value)}") { select(it) } },
                    )
                }
                .pointerInput(configuration.metrics, interactionReady) {
                    detectTapGestures { location ->
                        if (!interactionReady) return@detectTapGestures
                        progressMetricAt(configuration.metrics, size.width, size.height, density, configuration.ringWidth, configuration.ringGap, location)?.let(::select)
                    }
                },
        ) {
            val ringWidth = configuration.ringWidth.dp.toPx().coerceAtMost(min(size.width, size.height) / (configuration.metrics.size * 2f + 1f))
            val gap = configuration.ringGap.dp.toPx()
            val cap = when (configuration.ringCap) { "butt" -> StrokeCap.Butt; "square" -> StrokeCap.Square; else -> StrokeCap.Round }
            val outerRadius = (min(size.width, size.height) / 2f) - ringWidth
            configuration.metrics.forEachIndexed { index, metric ->
                val radius = outerRadius - index * (ringWidth + gap)
                if (radius <= ringWidth) return@forEachIndexed
                val bounds = Rect(center - Offset(radius, radius), Size(radius * 2, radius * 2))
                drawArc(configuration.trackColor, -90f, 360f, false, bounds.topLeft, bounds.size, style = Stroke(ringWidth, cap = cap))
                drawArc(
                    metric.color,
                    -90f,
                    (360f * metric.value * progress.value).toFloat(),
                    false,
                    bounds.topLeft,
                    bounds.size,
                    style = Stroke(ringWidth, cap = cap),
                )
                if (metric.id == selectedId) {
                    drawArc(metric.color.copy(alpha = 0.28f), -90f, 360f, false, bounds.topLeft, bounds.size, style = Stroke(ringWidth + 6.dp.toPx()))
                }
            }
        }
        if (configuration.centerLabel.isNotBlank()) Text(configuration.centerLabel)
    }
    if (configuration.legendVisible) configuration.metrics.forEach { metric -> Text("${metric.label}: ${percent.format(metric.value)}") }
    }
}

internal fun progressMetricIndexAt(
    metricCount: Int,
    width: Float,
    height: Float,
    density: Float,
    location: Offset,
    ringWidthDp: Float = 12f,
    gapDp: Float = 7f,
): Int? {
    if (metricCount <= 0 || width <= 0 || height <= 0) return null
    val ringWidth = (ringWidthDp * density).coerceAtMost(min(width, height) / (metricCount * 2f + 1f))
    val gap = gapDp * density
    val outerRadius = min(width, height) / 2f - ringWidth
    val distance = (location - Offset(width / 2f, height / 2f)).getDistance()
    return (0 until metricCount).firstOrNull { index ->
        val radius = outerRadius - index * (ringWidth + gap)
        radius > ringWidth && abs(distance - radius) <= (ringWidth + gap) / 2f
    }
}

private fun progressMetricAt(
    metrics: List<NativePHPChartsProgressMetric>,
    width: Int,
    height: Int,
    density: Float,
    ringWidthDp: Float,
    gapDp: Float,
    location: Offset,
): NativePHPChartsProgressMetric? =
    progressMetricIndexAt(metrics.size, width.toFloat(), height.toFloat(), density, location, ringWidthDp, gapDp)?.let(metrics::getOrNull)
