package com.donmanueldev.plugins.nativephp_charts.ui

import android.graphics.Paint
import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.tween
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.gestures.awaitEachGesture
import androidx.compose.foundation.gestures.awaitFirstDown
import androidx.compose.foundation.gestures.calculateCentroid
import androidx.compose.foundation.gestures.calculateCentroidSize
import androidx.compose.foundation.gestures.calculatePan
import androidx.compose.foundation.gestures.calculateZoom
import androidx.compose.foundation.gestures.detectTapGestures
import androidx.compose.foundation.gestures.detectDragGestures
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberUpdatedState
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.drawscope.clipRect
import androidx.compose.ui.input.pointer.PointerInputScope
import androidx.compose.ui.input.pointer.positionChanged
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
import kotlin.math.abs
import kotlin.math.max
import kotlin.math.min

/**
 * Owns transient Cartesian interaction state and draws one normalized layout.
 *
 * Selection is retained by stable series/point identity. Taps and accessibility
 * actions dispatch immediately; scrub updates only the preview during drag and
 * dispatches once when the drag ends. Viewport frames update local x-domain state
 * for responsive redraw, but emit one callback only after a completed, changed
 * gesture. A canceled viewport gesture restores its starting domain.
 *
 * Canvas geometry is in pixels. Viewport values remain logical x values even for
 * horizontal bars, whose pan/zoom gesture follows the physical vertical axis.
 */
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
                series.style?.lineColor
                    ?: configuration.style.lineColor.takeIf { configuration.series.size == 1 },
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
            lineColors = lineColors,
            pointColors = configuration.series.associate { series ->
                val lineColor = lineColors.getValue(series.id)
                series.id to chartColor(
                    series.style?.pointColor
                        ?: configuration.style.pointColor.takeIf { configuration.series.size == 1 },
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
    val baseLayout = remember(configuration, formatting, canvasSize, density, drawingResources.axisLabelPaint) {
        val fontMetrics = drawingResources.axisLabelPaint.fontMetrics
        NativePHPChartsLayoutEngine.build(
            configuration = configuration,
            formatting = formatting,
            size = canvasSize,
            density = density,
            measureAxisLabel = drawingResources.axisLabelPaint::measureText,
            axisLabelHeight = fontMetrics.descent - fontMetrics.ascent,
        )
    }
    val configuredViewport = remember(configuration.viewport, formatting) {
        if (!configuration.viewport.enabled) {
            null
        } else {
            val minimum = formatting.xNumeric(configuration.viewport.minimum)
            val maximum = formatting.xNumeric(configuration.viewport.maximum)
            if (minimum != null && maximum != null) NativePHPChartsDomain(minimum, maximum) else null
        }
    }
    var viewportDomain by remember(configuration.viewport, configuredViewport) {
        mutableStateOf(configuredViewport)
    }
    val layout = remember(
        configuration,
        formatting,
        canvasSize,
        density,
        drawingResources.axisLabelPaint,
        viewportDomain,
        baseLayout,
    ) {
        if (!configuration.viewport.enabled || viewportDomain == null) {
            baseLayout
        } else {
            val fontMetrics = drawingResources.axisLabelPaint.fontMetrics
            NativePHPChartsLayoutEngine.build(
                configuration = configuration,
                formatting = formatting,
                size = canvasSize,
                density = density,
                measureAxisLabel = drawingResources.axisLabelPaint::measureText,
                axisLabelHeight = fontMetrics.descent - fontMetrics.ascent,
                viewportOverride = viewportDomain,
            )
        }
    }
    val pathCache = remember(layout, configuration) {
        if (configuration.kind == NativePHPChartsKind.Line || configuration.kind == NativePHPChartsKind.Area) {
            NativePHPChartsPathCache.build(
                layout = layout,
                configuration = configuration,
                includeArea = configuration.kind == NativePHPChartsKind.Area,
            )
        } else {
            null
        }
    }
    val selected = layout.data.firstOrNull { it.selectionIdentity == selectedIdentity }
    val selectedData = selected?.let { anchor ->
        if (configuration.interaction.tooltip == "shared") {
            val key = formatting.geometryKey(anchor.point)
            layout.data.filter { formatting.geometryKey(it.point) == key }.sortedBy { it.series.index }
        } else {
            listOf(anchor)
        }
    }.orEmpty()
    val currentLayout by rememberUpdatedState(layout)
    val currentViewport by rememberUpdatedState(viewportDomain)
    LaunchedEffect(layout.data, selectedIdentity) {
        if (selectedIdentity != null && selected == null) {
            selectedIdentity = null
        }
    }

    fun select(datum: NativePHPChartsDatum) {
        selectedIdentity = datum.selectionIdentity
        NativePHPChartsSelection.dispatch(node, configuration, formatting, datum)
    }

    fun preview(datum: NativePHPChartsDatum) {
        selectedIdentity = datum.selectionIdentity
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
        "${layout.data.indexOf(datum) + 1}/${layout.data.size}",
        datum.series.name,
        formatting.x(datum.point),
        datum.point.nativePHPChartsAccessibleValue(formatting),
    ).joinToString(", ")

    Canvas(
        modifier = modifier
            .onSizeChanged { canvasSize = it }
            .semantics {
                contentDescription = summary
                selected?.let { datum ->
                    stateDescription = "${datum.series.name}, ${datum.point.label}, ${datum.point.nativePHPChartsAccessibleValue(formatting)}"
                }
                customActions = listOfNotNull(
                    previous?.let { datum ->
                        CustomAccessibilityAction(actionLabel(datum)) { moveSelection(-1) }
                    },
                    next?.let { datum ->
                        CustomAccessibilityAction(actionLabel(datum)) { moveSelection(1) }
                    },
                )
            }
            .pointerInput(
                configuration.viewport,
                configuration.onViewportChange,
                configuration.kind,
                baseLayout.xDomain,
            ) {
                val fullDomain = baseLayout.xDomain
                if (!configuration.viewport.enabled || fullDomain == null) return@pointerInput

                var initialDomain = currentViewport ?: configuredViewport ?: fullDomain
                var gestureDomain = initialDomain
                var gestureReason: NativePHPChartsViewportReason? = null
                detectNativePHPChartsViewportGestures(
                    canStart = { location -> currentLayout.plot.contains(location) },
                    onStart = {
                        initialDomain = currentViewport ?: configuredViewport ?: fullDomain
                        gestureDomain = initialDomain
                        gestureReason = null
                    },
                    onGesture = { centroid, pan, zoom ->
                        val current = gestureDomain
                        val horizontalBar = configuration.kind == NativePHPChartsKind.Bar &&
                            configuration.barOrientation == "horizontal"
                        val plot = currentLayout.plot
                        val length = if (horizontalBar) plot.height else plot.width
                        if (length <= 0f) return@detectNativePHPChartsViewportGestures

                        val zoomed = configuration.viewport.zoom && zoom != 1f
                        val panDelta = if (horizontalBar) pan.y else pan.x
                        val panned = configuration.viewport.pan && panDelta != 0f
                        val interactionReason = NativePHPChartsViewportReason.from(panned, zoomed)
                            ?: return@detectNativePHPChartsViewportGestures
                        gestureReason = NativePHPChartsViewportReason.combine(gestureReason, interactionReason)

                        var span = current.span
                        var center = (current.minimum + current.maximum) / 2.0
                        if (zoomed) {
                            val minimumSpan = min(
                                configuration.viewport.minimumSpan ?: max(fullDomain.span / 1000.0, 0.000_001),
                                fullDomain.span,
                            )
                            val nextSpan = (span / zoom).coerceIn(minimumSpan, fullDomain.span)
                            val focal = if (horizontalBar) {
                                ((centroid.y - plot.top) / plot.height).coerceIn(0f, 1f)
                            } else {
                                ((centroid.x - plot.left) / plot.width).coerceIn(0f, 1f)
                            }
                            val focalFraction = focal.toDouble()
                            val focalValue = current.minimum + (span * focalFraction)
                            center = focalValue + ((0.5 - focalFraction) * nextSpan)
                            span = nextSpan
                        }
                        if (panned) {
                            center -= (panDelta / length).toDouble() * span
                        }

                        var minimum = center - span / 2.0
                        var maximum = center + span / 2.0
                        if (minimum < fullDomain.minimum) {
                            maximum += fullDomain.minimum - minimum
                            minimum = fullDomain.minimum
                        }
                        if (maximum > fullDomain.maximum) {
                            minimum -= maximum - fullDomain.maximum
                            maximum = fullDomain.maximum
                        }
                        val nextDomain = NativePHPChartsDomain(
                            max(minimum, fullDomain.minimum),
                            min(maximum, fullDomain.maximum),
                        )
                        if (nextDomain != current) {
                            gestureDomain = nextDomain
                            viewportDomain = nextDomain
                        }
                    },
                    onEnd = { completed ->
                        val reason = gestureReason
                        if (completed && reason != null && gestureDomain != initialDomain) {
                            NativePHPChartsViewportSelection.dispatch(
                                node,
                                configuration,
                                formatting,
                                gestureDomain,
                                reason,
                            )
                        } else if (!completed) {
                            viewportDomain = initialDomain
                        }
                    },
                )
            }
            .pointerInput(configuration.interaction.enabled, configuration.interaction.mode, density) {
                if (!configuration.interaction.enabled) return@pointerInput

                if (configuration.interaction.mode == "scrub") {
                    var pending: NativePHPChartsDatum? = null
                    detectDragGestures(
                        onDragStart = { location ->
                            pending = currentLayout.nearest(location, 32f * density)
                            pending?.let(::preview)
                        },
                        onDrag = { change, _ ->
                            pending = currentLayout.nearest(change.position, 32f * density)
                            pending?.let(::preview)
                        },
                        onDragEnd = { pending?.let(::select) },
                    )
                } else {
                    detectTapGestures { location ->
                        currentLayout.nearest(location, 32f * density)?.let(::select)
                    }
                }
            },
    ) {
        drawNativePHPChartsAxes(configuration, layout, drawingResources)
        clipRect(layout.plot.left, layout.plot.top, layout.plot.right, layout.plot.bottom) {
            drawNativePHPChartsAnnotations(layout)
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
                NativePHPChartsKind.Candlestick -> drawNativePHPChartsCandlesticks(configuration, layout, progress.value)
            }
            selected?.let {
                drawNativePHPChartsSelectionOverlay(
                    it, selectedData, configuration.interaction, layout, drawingResources,
                )
            }
            selected?.takeIf { configuration.interaction.tooltip != "none" }?.let {
                drawNativePHPChartsTooltip(
                    it, selectedData, configuration.interaction, formatting, layout, drawingResources,
                )
            }
        }
        drawNativePHPChartsAnnotationLabels(layout, drawingResources)
    }
}

/**
 * Reduces raw pointer events to slop-qualified pan/zoom frames.
 *
 * The recognizer starts only inside the current plot, consumes position changes
 * after touch slop, and calls [onEnd] only for an accepted gesture that crossed
 * slop. `completed` is false when another recognizer consumed the gesture.
 */
private suspend fun PointerInputScope.detectNativePHPChartsViewportGestures(
    canStart: (Offset) -> Boolean,
    onStart: () -> Unit,
    onGesture: (centroid: Offset, pan: Offset, zoom: Float) -> Unit,
    onEnd: (completed: Boolean) -> Unit,
) {
    awaitEachGesture {
        val down = awaitFirstDown(requireUnconsumed = false)
        val accepted = canStart(down.position)
        var pastTouchSlop = false
        var canceled = false
        var pointersPressed: Boolean
        var accumulatedPan = Offset.Zero
        var accumulatedZoom = 1f
        if (accepted) onStart()

        do {
            val event = awaitPointerEvent()
            canceled = event.changes.any { it.isConsumed }
            pointersPressed = event.changes.any { it.pressed }
            if (accepted && !canceled) {
                val zoomChange = event.calculateZoom()
                val panChange = event.calculatePan()

                if (!pastTouchSlop) {
                    accumulatedZoom *= zoomChange
                    accumulatedPan += panChange
                    val centroidSize = event.calculateCentroidSize(useCurrent = false)
                    val zoomMotion = abs(1 - accumulatedZoom) * centroidSize
                    val panMotion = accumulatedPan.getDistance()
                    pastTouchSlop = zoomMotion > viewConfiguration.touchSlop ||
                        panMotion > viewConfiguration.touchSlop
                }

                if (pastTouchSlop && (zoomChange != 1f || panChange != Offset.Zero)) {
                    val centroid = event.calculateCentroid(useCurrent = false)
                    if (centroid != Offset.Unspecified) {
                        onGesture(centroid, panChange, zoomChange)
                    }
                    event.changes.forEach { change ->
                        if (change.positionChanged()) change.consume()
                    }
                }
            }
        } while (!canceled && pointersPressed)

        if (accepted && pastTouchSlop) {
            onEnd(!canceled)
        }
    }
}
