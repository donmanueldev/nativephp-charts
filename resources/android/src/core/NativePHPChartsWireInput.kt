package com.donmanueldev.plugins.nativephp_charts.ui

import android.util.LruCache
import com.nativephp.mobile.ui.nativerender.NativeUINode
import java.io.File

/**
 * Raw Cartesian contract captured from a single [NativeUINode] render.
 *
 * This type intentionally keeps structured props as JSON strings. Parsing and
 * render-safe fallback decisions belong to [NativePHPChartsDecoder], while this
 * boundary is responsible only for NativePHP prop defaults, callback ids, and
 * resolving the optional file-backed series transport. [contractVersion] is
 * retained as wire metadata even though the current decoder consumes one shape.
 */
internal data class NativePHPChartsWireInput(
    val contractVersion: Int,
    val seriesJson: String,
    val styleJson: String,
    val xAxisJson: String,
    val yAxisJson: String,
    val legendJson: String,
    val annotationsJson: String,
    val interactionJson: String,
    val viewportJson: String,
    val samplingJson: String,
    val areaMode: String,
    val barMode: String,
    val barOrientation: String,
    val emptyLabel: String,
    val accessibilityLabel: String,
    val locale: String,
    val valueFormat: String,
    val currencyCode: String,
    val minimumFractionDigits: Int,
    val maximumFractionDigits: Int,
    val showGrid: Boolean,
    val showPoints: Boolean,
    val beginAtZero: Boolean,
    val animated: Boolean,
    val onSelect: Int,
    val onViewportChange: Int,
) {
    companion object {
        private val seriesFileCache = LruCache<String, String>(8)

        /**
         * Snapshots the wire props so Compose can key decoding with value equality.
         * Missing scalar props receive the legacy-compatible defaults used by PHP.
         */
        fun from(node: NativeUINode): NativePHPChartsWireInput {
            val props = node.props

            return NativePHPChartsWireInput(
                contractVersion = props.getInt("contract_version", 0),
                seriesJson = resolveSeriesJson(node),
                styleJson = props.getString("style_json", "{}"),
                xAxisJson = props.getString("x_axis_json", "{}"),
                yAxisJson = props.getString("y_axis_json", "{}"),
                legendJson = props.getString("legend_json", "{}"),
                annotationsJson = props.getString("annotations_json", "[]"),
                interactionJson = props.getString("interaction_json", "{}"),
                viewportJson = props.getString("viewport_json", "{}"),
                samplingJson = props.getString("sampling_json", "{}"),
                areaMode = props.getString("area_mode", "overlay"),
                barMode = props.getString("bar_mode", "grouped"),
                barOrientation = props.getString("bar_orientation", "vertical"),
                emptyLabel = props.getString("empty_label", "No data"),
                accessibilityLabel = props.getString("a11y_label", "Chart"),
                locale = props.getString("locale", ""),
                valueFormat = props.getString("value_format", "number"),
                currencyCode = props.getString("currency_code", ""),
                minimumFractionDigits = props.getInt("minimum_fraction_digits", -1),
                maximumFractionDigits = props.getInt("maximum_fraction_digits", -1),
                showGrid = props.getBool("show_grid", true),
                showPoints = props.getBool("show_points", true),
                beginAtZero = props.getBool("begin_at_zero", true),
                animated = props.getBool("animated", true),
                onSelect = props.getCallbackId("on_select"),
                onViewportChange = props.getCallbackId("on_viewport_change"),
            )
        }

        private fun resolveSeriesJson(node: NativeUINode): String {
            val inline = node.props.getString("series_json", "[]")
            val transport = node.props.getString("series_transport", "inline-v1")
            val path = node.props.getString("series_json_file", "")
            if (transport != "file-v1" || path.isBlank()) return inline

            seriesFileCache.get(path)?.let { return it }

            // A stale or unreadable payload must render the normal empty state,
            // never retain data from a different path or crash composition.
            return try {
                File(path).readText(Charsets.UTF_8).also { seriesFileCache.put(path, it) }
            } catch (_: Exception) {
                "[]"
            }
        }
    }
}
