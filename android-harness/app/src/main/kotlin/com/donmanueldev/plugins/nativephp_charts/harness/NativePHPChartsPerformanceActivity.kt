package com.donmanueldev.plugins.nativephp_charts.harness

import android.os.Bundle
import android.os.SystemClock
import android.os.Trace
import android.util.Log
import android.view.WindowManager
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.runtime.CompositionLocalProvider
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import com.donmanueldev.plugins.nativephp_charts.ui.LocalNativePHPChartsDrawObserver
import com.donmanueldev.plugins.nativephp_charts.ui.NativePHPChartsKind
import com.donmanueldev.plugins.nativephp_charts.ui.NativePHPChartsLineChartRenderer
import com.nativephp.mobile.ui.nativerender.GenericProps
import com.nativephp.mobile.ui.nativerender.NativeUIBridge
import com.nativephp.mobile.ui.nativerender.NativeUINode
import org.json.JSONObject
import java.nio.charset.StandardCharsets
import java.util.concurrent.atomic.AtomicBoolean
import kotlin.math.sin

/**
 * Isolated physical-device presentation probe for deterministic Cartesian datasets.
 *
 * This Activity is part of the versioned test harness, never the distributed plugin. It logs one
 * bounded JSON marker after the first completed draw. Device scripts combine that marker with
 * platform frame and memory counters; the renderer payload itself is never written to Logcat.
 */
class NativePHPChartsPerformanceActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        window.addFlags(WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON)
        val pointCount = intent.getIntExtra(EXTRA_POINT_COUNT, 100)
        require(pointCount in SUPPORTED_POINT_COUNTS) {
            "point_count must be 100, 1000, or 10000"
        }
        val runId = sanitizeRunId(intent.getStringExtra(EXTRA_RUN_ID))
        val seriesJson = deterministicSeries(pointCount)
        val payloadBytes = seriesJson.toByteArray(StandardCharsets.UTF_8).size
        val reported = AtomicBoolean(false)
        val traceName = "NPC.first-render.$pointCount.$runId"
        val startedAt = SystemClock.elapsedRealtimeNanos()
        Trace.beginAsyncSection(traceName, pointCount)
        NativeUIBridge.resetRecordedEvents()

        val reportDrawnSnapshot: (NativePHPChartsKind, Int) -> Unit = { kind, drawnPointCount ->
            if (kind == NativePHPChartsKind.Line && drawnPointCount == pointCount && reported.compareAndSet(false, true)) {
                val latencyMillis = (SystemClock.elapsedRealtimeNanos() - startedAt) / 1_000_000.0
                Trace.endAsyncSection(traceName, pointCount)
                val marker = JSONObject()
                    .put("schema_version", 1)
                    .put("run_id", runId)
                    .put("chart", "line")
                    .put("points", pointCount)
                    .put("scenario", "first-render")
                    .put("latency_ms", latencyMillis)
                    .put("payload_bytes", payloadBytes)
                    .put("callbacks", NativeUIBridge.recordedTextChangeEvents().size)
                Log.w(LOG_TAG, marker.toString())
                reportFullyDrawn()
            }
        }

        setContent {
            CompositionLocalProvider(LocalNativePHPChartsDrawObserver provides reportDrawnSnapshot) {
                NativePHPChartsLineChartRenderer.Render(
                    node = lineNode(seriesJson, pointCount),
                    modifier = Modifier.fillMaxSize().background(Color.White),
                )
            }
        }
    }

    private fun lineNode(seriesJson: String, pointCount: Int): NativeUINode = NativeUINode(
        id = 10_001,
        type = "line_chart",
        layout = null,
        style = null,
        props = GenericProps(
            mapOf(
                "contract_version" to 1,
                "series_json" to seriesJson,
                "series_transport" to "inline-v1",
                "style_json" to """{"line":{"width":2,"interpolation":"linear"},"points":{"visible":${pointCount <= 100}},"axis":{"label_count":5}}""",
                "theme_mode" to "light",
                "theme_json" to "{}",
                "preset" to "default",
                "x_axis_json" to """{"type":"number","label_count":5}""",
                "y_axis_json" to """{"label_count":5}""",
                "legend_json" to """{"visible":false}""",
                "annotations_json" to "[]",
                "interaction_json" to """{"enabled":true,"mode":"tap"}""",
                "viewport_json" to "{}",
                "sampling_json" to "{}",
                "show_points" to (pointCount <= 100),
                "animated" to false,
                "locale" to "en-US",
                "a11y_label" to "Physical performance line chart with $pointCount points",
                "error_label" to "Chart unavailable",
                "on_select" to 41,
            ),
        ),
        onPress = 0,
        onLongPress = 0,
        children = emptyList(),
    )

    private fun deterministicSeries(pointCount: Int): String = buildString(pointCount * 80) {
        append("""[{"id":"performance","name":"Samples","points":[""")
        repeat(pointCount) { index ->
            if (index > 0) append(',')
            val value = 50 + (sin(index / 18.0) * 28) + (index % 9)
            append("""{"id":"point-$index","x":$index,"label":"Sample $index","value":${"%.3f".format(java.util.Locale.ROOT, value)}}""")
        }
        append("]}]")
    }

    private fun sanitizeRunId(raw: String?): String {
        val sanitized = raw.orEmpty()
            .take(MAX_RUN_ID_LENGTH)
            .map { character ->
                if (character.isLetterOrDigit() || character == '-' || character == '_' || character == '.') {
                    character
                } else {
                    '_'
                }
            }
            .joinToString("")
        return sanitized.ifBlank { "unspecified" }
    }

    private companion object {
        const val EXTRA_POINT_COUNT = "point_count"
        const val EXTRA_RUN_ID = "run_id"
        const val LOG_TAG = "NativePHPChartsPerf"
        const val MAX_RUN_ID_LENGTH = 64
        val SUPPORTED_POINT_COUNTS = setOf(100, 1_000, 10_000)
    }
}
