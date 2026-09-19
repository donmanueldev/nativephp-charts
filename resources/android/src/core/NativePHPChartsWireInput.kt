package com.donmanueldev.plugins.nativephp_charts.ui

import com.nativephp.mobile.ui.nativerender.NativeUINode
import java.io.File
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext

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
    val seriesTransport: String,
    val seriesJsonFile: String,
    val styleJson: String,
    val themeMode: String,
    val themeJson: String,
    val preset: String,
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
    val errorLabel: String,
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
        /**
         * Snapshots the wire props so Compose can key decoding with value equality.
         * Missing scalar props receive the legacy-compatible defaults used by PHP.
         */
        fun from(node: NativeUINode): NativePHPChartsWireInput {
            val props = node.props

            return NativePHPChartsWireInput(
                contractVersion = props.getInt("contract_version", 0),
                seriesJson = props.getString("series_json", "[]"),
                seriesTransport = props.getString("series_transport", "inline-v1"),
                seriesJsonFile = props.getString("series_json_file", ""),
                styleJson = props.getString("style_json", "{}"),
                themeMode = props.getString("theme_mode", "system"),
                themeJson = props.getString("theme_json", "{}"),
                preset = props.getString("preset", "default"),
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
                errorLabel = props.getString("error_label", "Chart unavailable"),
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

        suspend fun resolveSeriesJson(input: NativePHPChartsWireInput): NativePHPChartsDecodeResult<String> {
            if (input.seriesTransport != "file-v1") {
                return NativePHPChartsDecodeResult.Success(input.seriesJson)
            }
            if (input.seriesJsonFile.isBlank()) {
                return NativePHPChartsDecodeResult.Failure("missing_payload_file")
            }
            return withContext(Dispatchers.IO) {
                runCatching { File(input.seriesJsonFile).readText(Charsets.UTF_8) }
                    .fold(
                        onSuccess = { NativePHPChartsDecodeResult.Success(it) },
                        onFailure = { NativePHPChartsDecodeResult.Failure("unreadable_payload_file", it) },
                    )
            }
        }
    }
}
