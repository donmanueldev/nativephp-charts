package com.donmanueldev.plugins.nativephp_charts.ui

import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.tween
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.derivedStateOf
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberUpdatedState
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Rect
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.input.pointer.pointerInput
import androidx.compose.ui.layout.onSizeChanged
import androidx.compose.foundation.gestures.detectTapGestures
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalDensity
import androidx.compose.ui.semantics.CustomAccessibilityAction
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.customActions
import androidx.compose.ui.semantics.onClick
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.semantics.stateDescription
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.IntSize
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
        NativePHPChartsAsyncState.Loading -> {
            Box(modifier.fillMaxSize())
            return
        }
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
    val interactionReady by remember { derivedStateOf { progress.value >= 0.999f } }
    var selectedId by remember { mutableStateOf<String?>(null) }
    var canvasSize by remember { mutableStateOf(IntSize.Zero) }
    val selected = configuration.metrics.firstOrNull { it.id == selectedId }
    val ringLayout = NativePHPChartsProgressRingLayout.of(
        configuration.metrics.size,
        canvasSize.width.toFloat(),
        canvasSize.height.toFloat(),
        configuration.ringWidth * density,
        configuration.ringGap * density,
    )
    val percent = remember(configuration.locale, configuration.minimumFractionDigits, configuration.maximumFractionDigits) {
        NumberFormat.getPercentInstance(configuration.locale.takeIf(String::isNotBlank)?.let(Locale::forLanguageTag) ?: Locale.getDefault()).apply {
            if (configuration.minimumFractionDigits >= 0) minimumFractionDigits = configuration.minimumFractionDigits
            if (configuration.maximumFractionDigits >= 0) maximumFractionDigits = configuration.maximumFractionDigits
        }
    }
    val currentNode by rememberUpdatedState(node)
    val currentConfiguration by rememberUpdatedState(configuration)
    val currentPercent by rememberUpdatedState(percent)
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
        val onSelect = currentConfiguration.onSelect
        if (onSelect > 0) {
            val payload = nativePHPChartsSpecialSelectionPayload(
                "progress", metric.id, metric.label, metric.index, metric.label,
                metric.value, currentPercent.format(metric.value),
            )
            NativeUIBridge.sendTextChangeEvent(onSelect, currentNode.id, JSONObject(payload).toString())
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
                .onSizeChanged { canvasSize = it }
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
                        val latest = currentConfiguration
                        val layout = NativePHPChartsProgressRingLayout.of(
                            latest.metrics.size,
                            size.width.toFloat(),
                            size.height.toFloat(),
                            latest.ringWidth * density,
                            latest.ringGap * density,
                        )
                        layout.indexAt(location, size.width / 2f, size.height / 2f, latest.metrics.size)
                            ?.let(latest.metrics::getOrNull)
                            ?.let(::select)
                    }
                },
        ) {
            val layout = NativePHPChartsProgressRingLayout.of(
                configuration.metrics.size,
                size.width,
                size.height,
                configuration.ringWidth.dp.toPx(),
                configuration.ringGap.dp.toPx(),
            )
            val ringWidth = layout.ringWidth
            val cap = when (configuration.ringCap) { "butt" -> StrokeCap.Butt; "square" -> StrokeCap.Square; else -> StrokeCap.Round }
            configuration.metrics.forEachIndexed { index, metric ->
                val radius = layout.radius(index)
                if (radius <= 0f) return@forEachIndexed
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
        // The label is hidden once the innermost ring leaves too little room to read it.
        val labelFits = ringLayout.innerRadius(configuration.metrics.size) >= 40f * density
        if (configuration.centerLabel.isNotBlank() && labelFits) {
            Text(configuration.centerLabel, color = configuration.foregroundColor, maxLines = 2, overflow = TextOverflow.Ellipsis)
        }
    }
    if (configuration.legendVisible) {
        Column(
            Modifier.fillMaxWidth()
                .heightIn(max = 132.dp)
                .verticalScroll(rememberScrollState())
                .padding(horizontal = 12.dp, vertical = 8.dp),
        ) {
            configuration.metrics.forEach { metric ->
                Row(Modifier.fillMaxWidth().padding(vertical = 3.dp), verticalAlignment = Alignment.CenterVertically) {
                    Box(Modifier.size(8.dp).background(metric.color, CircleShape))
                    Text(
                        metric.label,
                        modifier = Modifier.weight(1f).padding(start = 8.dp),
                        color = configuration.foregroundColor,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis,
                    )
                    Text(percent.format(metric.value), color = configuration.foregroundColor)
                }
            }
        }
    }
    }
}

/**
 * Ring geometry shared by drawing and hit testing.
 *
 * The configured stroke width and gap are kept while every ring fits inside 90% of the
 * radius; beyond that both shrink proportionally so all rings stay visible and tappable,
 * matching the iOS renderer. The outer ring keeps one stroke width of margin for its
 * selection halo.
 */
internal data class NativePHPChartsProgressRingLayout(
    val ringWidth: Float,
    val ringStep: Float,
    val outerRadius: Float,
) {
    fun radius(index: Int): Float = outerRadius - index * ringStep

    /** Radius of the empty hole inside the innermost ring. */
    fun innerRadius(count: Int): Float = radius((count - 1).coerceAtLeast(0)) - ringWidth / 2f

    fun indexAt(location: Offset, centerX: Float, centerY: Float, count: Int): Int? {
        val distance = (location - Offset(centerX, centerY)).getDistance()
        return (0 until count).firstOrNull { index ->
            val radius = radius(index)
            radius > 0f && abs(distance - radius) <= ringStep / 2f
        }
    }

    companion object {
        fun of(count: Int, width: Float, height: Float, ringWidthPx: Float, gapPx: Float): NativePHPChartsProgressRingLayout {
            val radius = (min(width, height) / 2f).coerceAtLeast(0f)
            val step = ringWidthPx + gapPx
            val extent = (count - 1).coerceAtLeast(0) * step + ringWidthPx * 1.5f
            val available = radius * 0.9f
            val scale = if (extent > available && extent > 0f) available / extent else 1f
            val ringWidth = ringWidthPx * scale
            return NativePHPChartsProgressRingLayout(ringWidth, step * scale, radius - ringWidth)
        }
    }
}
