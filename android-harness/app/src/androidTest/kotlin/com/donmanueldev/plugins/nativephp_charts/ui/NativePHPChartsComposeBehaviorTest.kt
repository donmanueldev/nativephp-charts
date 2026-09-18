package com.donmanueldev.plugins.nativephp_charts.ui

import androidx.compose.foundation.ScrollState
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Text
import androidx.compose.runtime.CompositionLocalProvider
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.semantics.SemanticsActions
import androidx.compose.ui.semantics.SemanticsProperties
import androidx.compose.ui.semantics.getOrNull
import androidx.compose.ui.test.click
import androidx.compose.ui.test.hasContentDescription
import androidx.compose.ui.test.junit4.createComposeRule
import androidx.compose.ui.test.pinch
import androidx.compose.ui.test.performTouchInput
import androidx.compose.ui.test.swipe
import androidx.compose.ui.test.swipeUp
import androidx.compose.ui.unit.dp
import androidx.test.ext.junit.runners.AndroidJUnit4
import com.nativephp.mobile.ui.nativerender.GenericProps
import com.nativephp.mobile.ui.nativerender.NativeUIBridge
import com.nativephp.mobile.ui.nativerender.NativeUINode
import org.json.JSONObject
import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Rule
import org.junit.Test
import org.junit.runner.RunWith
import java.util.concurrent.atomic.AtomicInteger

@RunWith(AndroidJUnit4::class)
class NativePHPChartsComposeBehaviorTest {
    @get:Rule
    val compose = createComposeRule()

    @Before
    fun resetBridgeEvents() {
        NativeUIBridge.resetRecordedEvents()
    }

    @Test
    fun accessibleSelectionEmitsExactlyOneVersionedCallback() {
        render(lineNode())

        val actions = chartNode().fetchSemanticsNode().config[SemanticsActions.CustomActions]
        compose.runOnIdle {
            val next = actions.single { it.label.startsWith("1/3") }
            assertTrue(next.action())
        }
        compose.waitForIdle()

        val event = NativeUIBridge.recordedTextChangeEvents().single()
        assertEquals(41, event.callbackId)
        assertEquals(7, event.nodeId)
        JSONObject(event.text).run {
            assertEquals(1, getInt("version"))
            assertEquals("line", getString("chart_type"))
            assertEquals("revenue", getString("series_id"))
            assertEquals("jan", getString("point_id"))
        }
    }

    @Test
    fun tapOnVisiblePointEmitsOneCallback() {
        render(lineNode())

        chartNode().performTouchInput { click(center) }
        compose.waitForIdle()

        val events = NativeUIBridge.recordedTextChangeEvents()
        assertEquals(1, events.size)
        assertEquals("feb", JSONObject(events.single().text).getString("point_id"))
    }

    @Test
    fun scrubPreviewsFramesButEmitsOnlyOnCompletion() {
        render(lineNode(interaction = """{"enabled":true,"mode":"scrub"}"""))

        chartNode().performTouchInput {
            down(center)
            advanceEventTime(100)
            moveBy(Offset(width * 0.15f, 0f))
            advanceEventTime(100)
            moveTo(center)
            advanceEventTime(100)
            up()
        }
        compose.waitForIdle()

        assertEquals(1, NativeUIBridge.recordedTextChangeEvents().size)
    }

    @Test
    fun viewportPanEmitsOnlyAfterCompletedGesture() {
        render(
            lineNode(
                interaction = """{"enabled":false}""",
                xAxis = """{"type":"number"}""",
                viewport = """{"enabled":true,"pan":true,"zoom":true,"minimum":0.5,"maximum":2.5}""",
                numericX = true,
            ),
        )

        chartNode().performTouchInput {
            swipe(
                start = Offset(width * 0.70f, height * 0.50f),
                end = Offset(width * 0.30f, height * 0.50f),
                durationMillis = 500,
            )
        }
        compose.waitForIdle()

        val event = NativeUIBridge.recordedTextChangeEvents().single()
        assertEquals(42, event.callbackId)
        JSONObject(event.text).run {
            assertEquals(1, getInt("version"))
            assertEquals("line", getString("chart_type"))
            assertEquals("pan", getString("reason"))
        }
    }

    @Test
    fun viewportPinchEmitsOneZoomCallback() {
        render(viewportNode(pan = false))

        chartNode().performTouchInput {
            pinch(
                start0 = Offset(width * 0.45f, height * 0.50f),
                end0 = Offset(width * 0.25f, height * 0.50f),
                start1 = Offset(width * 0.55f, height * 0.50f),
                end1 = Offset(width * 0.75f, height * 0.50f),
                durationMillis = 500,
            )
        }
        compose.waitForIdle()

        val event = NativeUIBridge.recordedTextChangeEvents().single()
        assertEquals(42, event.callbackId)
        assertEquals("zoom", JSONObject(event.text).getString("reason"))
    }

    @Test
    fun canceledViewportGestureRestoresStateWithoutCallback() {
        render(viewportNode())

        chartNode().performTouchInput {
            down(center)
            advanceEventTime(100)
            moveBy(Offset(-width * 0.25f, 0f))
            advanceEventTime(100)
            cancel()
        }
        compose.waitForIdle()

        assertTrue(NativeUIBridge.recordedTextChangeEvents().isEmpty())
    }

    @Test
    fun verticalSwipeStillScrollsTheSurroundingScreen() {
        val scrollState = ScrollState(0)
        compose.setContent {
            Column(
                Modifier
                    .height(320.dp)
                    .verticalScroll(scrollState),
            ) {
                Text("Before chart")
                NativePHPChartsLineChartRenderer.Render(lineNode(), Modifier.size(320.dp, 240.dp))
                Spacer(Modifier.height(640.dp))
                Text("After chart")
            }
        }
        awaitChart()

        chartNode().performTouchInput { swipeUp(durationMillis = 500) }
        compose.waitUntil(timeoutMillis = 5_000) { scrollState.value > 0 }

        assertTrue(scrollState.value > 0)
        assertTrue(NativeUIBridge.recordedTextChangeEvents().isEmpty())
    }

    @Test
    fun unsupportedContractShowsAccessibleUnavailableState() {
        render(lineNode(contractVersion = 99))

        compose.onNode(
            hasContentDescription("Revenue trend: Chart unavailable", substring = true),
        ).assertExists()
        assertTrue(NativeUIBridge.recordedTextChangeEvents().isEmpty())
    }

    @Test
    fun drawObserverReportsValidatedChartSnapshots() {
        val validDraws = AtomicInteger(0)
        compose.setContent {
            CompositionLocalProvider(
                LocalNativePHPChartsDrawObserver provides { kind, points ->
                    if (kind == NativePHPChartsKind.Line && points == 3) validDraws.incrementAndGet()
                },
            ) {
                NativePHPChartsLineChartRenderer.Render(lineNode(), Modifier.size(320.dp, 240.dp))
            }
        }
        awaitChart()
        compose.waitUntil(timeoutMillis = 5_000) { validDraws.get() > 0 }
    }

    @Test
    fun drawObserverDoesNotReportUnavailablePlaceholders() {
        val unavailableDraws = AtomicInteger(0)
        compose.setContent {
            CompositionLocalProvider(
                LocalNativePHPChartsDrawObserver provides { _, _ -> unavailableDraws.incrementAndGet() },
            ) {
                NativePHPChartsLineChartRenderer.Render(
                    lineNode(contractVersion = 99),
                    Modifier.size(320.dp, 240.dp),
                )
            }
        }
        compose.onNode(
            hasContentDescription("Revenue trend: Chart unavailable", substring = true),
        ).assertExists()
        compose.waitForIdle()

        assertEquals(0, unavailableDraws.get())
    }

    @Test
    fun chartCanBeDisposedAndReopenedWithoutDuplicatingCallbacks() {
        var visible by mutableStateOf(true)
        val node = lineNode()
        compose.setContent {
            if (visible) {
                NativePHPChartsLineChartRenderer.Render(node, Modifier.size(320.dp, 240.dp))
            }
        }
        awaitChart()

        chartNode().performTouchInput { click(center) }
        compose.waitForIdle()
        assertEquals(1, NativeUIBridge.recordedTextChangeEvents().size)

        NativeUIBridge.resetRecordedEvents()
        compose.runOnIdle { visible = false }
        compose.waitUntil(timeoutMillis = 5_000) {
            compose.onAllNodes(hasContentDescription("Revenue trend", substring = true))
                .fetchSemanticsNodes().isEmpty()
        }
        compose.runOnIdle { visible = true }
        awaitChart()
        chartNode().performTouchInput { click(center) }
        compose.waitForIdle()

        assertEquals(1, NativeUIBridge.recordedTextChangeEvents().size)
    }

    @Test
    fun stableSelectionSurvivesMutationAndClearsAfterDeletion() {
        var node by mutableStateOf(lineNode())
        compose.setContent {
            NativePHPChartsLineChartRenderer.Render(node, Modifier.size(320.dp, 240.dp))
        }
        awaitChart()

        chartNode().performTouchInput { click(center) }
        compose.waitUntil(timeoutMillis = 5_000) {
            chartStateDescription()?.contains("Feb") == true
        }

        compose.runOnIdle {
            node = lineNode(
                pointIds = listOf("mar", "apr", "feb", "jan"),
                pointValues = mapOf("feb" to 9),
            )
        }
        compose.waitUntil(timeoutMillis = 5_000) {
            chartStateDescription()?.let { "Feb" in it && "9" in it } == true
        }

        compose.runOnIdle {
            node = lineNode(pointIds = listOf("mar", "apr", "jan"))
        }
        compose.waitUntil(timeoutMillis = 5_000) { chartStateDescription() == null }

        compose.runOnIdle { node = lineNode(pointIds = emptyList()) }
        compose.onNode(
            hasContentDescription("Revenue trend: No data", substring = true),
        ).assertExists()
    }

    private fun render(node: NativeUINode) {
        compose.setContent {
            NativePHPChartsLineChartRenderer.Render(node, Modifier.size(320.dp, 240.dp))
        }
        awaitChart()
    }

    private fun awaitChart() {
        compose.waitUntil(timeoutMillis = 5_000) {
            compose.onAllNodes(hasContentDescription("Revenue trend", substring = true))
                .fetchSemanticsNodes().isNotEmpty()
        }
    }

    private fun chartNode() = compose.onNode(
        hasContentDescription("Revenue trend", substring = true),
    )

    private fun chartStateDescription(): String? = chartNode()
        .fetchSemanticsNode()
        .config
        .getOrNull(SemanticsProperties.StateDescription)

    private fun viewportNode(pan: Boolean = true) = lineNode(
        interaction = """{"enabled":false}""",
        xAxis = """{"type":"number"}""",
        viewport = """{"enabled":true,"pan":$pan,"zoom":true,"minimum":0.5,"maximum":2.5}""",
        numericX = true,
    )

    private fun lineNode(
        contractVersion: Int = 1,
        interaction: String = """{"enabled":true,"mode":"tap"}""",
        xAxis: String = "{}",
        viewport: String = "{}",
        numericX: Boolean = false,
        pointIds: List<String> = listOf("jan", "feb", "mar"),
        pointValues: Map<String, Int> = emptyMap(),
    ): NativeUINode {
        val catalog = mapOf(
            "jan" to ("Jan" to 0),
            "feb" to ("Feb" to 5),
            "mar" to ("Mar" to 10),
            "apr" to ("Apr" to 7),
        )
        val points = pointIds.joinToString(",") { id ->
            val (label, defaultValue) = requireNotNull(catalog[id])
            val x = if (numericX) ",\"x\":${catalog.keys.indexOf(id)}" else ""
            val value = pointValues[id] ?: defaultValue
            """{"id":"$id","label":"$label","value":$value$x}"""
        }
        val props = GenericProps(
            mapOf(
                "contract_version" to contractVersion,
                "series_json" to """[{"id":"revenue","name":"Revenue","points":[$points]}]""",
                "series_transport" to "inline-v1",
                "style_json" to "{}",
                "theme_mode" to "light",
                "theme_json" to "{}",
                "preset" to "default",
                "x_axis_json" to xAxis,
                "y_axis_json" to "{}",
                "legend_json" to """{"visible":false}""",
                "annotations_json" to "[]",
                "interaction_json" to interaction,
                "viewport_json" to viewport,
                "sampling_json" to "{}",
                "a11y_label" to "Revenue trend",
                "error_label" to "Chart unavailable",
                "locale" to "en-US",
                "animated" to false,
                "on_select" to 41,
                "on_viewport_change" to 42,
            ),
        )
        return NativeUINode(
            id = 7,
            type = "line_chart",
            layout = null,
            style = null,
            props = props,
            onPress = 0,
            onLongPress = 0,
            children = emptyList(),
        )
    }
}
