package com.donmanueldev.plugins.nativephp_charts.ui

import androidx.compose.ui.graphics.Color
import org.json.JSONArray
import org.json.JSONObject

/**
 * Fail-soft decoder for the pie/donut wire contract.
 *
 * Invalid JSON yields an empty chart, invalid or negative segments are skipped,
 * visual bounds are clamped, and pie always forces a zero inner radius. Donut's
 * public radius constraint is enforced again here to keep native rendering safe.
 */
internal object NativePHPChartsRadialDecoder {
    private val fallbackColors = listOf(
        Color(0xFF6366F1), Color(0xFF14B8A6), Color(0xFFF59E0B), Color(0xFFEC4899),
        Color(0xFF3B82F6), Color(0xFF8B5CF6), Color(0xFF22C55E), Color(0xFFEF4444),
    )

    fun decode(
        input: NativePHPChartsRadialWireInput,
        kind: NativePHPChartsRadialKind,
    ): NativePHPChartsRadialConfiguration {
        val styleRoot = radialObject(input.styleJson)
        val segmentStyle = styleRoot.optJSONObject("segment") ?: JSONObject()
        val innerRadiusRatio = when (kind) {
            NativePHPChartsRadialKind.Pie -> 0f
            NativePHPChartsRadialKind.Donut -> input.innerRadiusRatio.coerceIn(0.2f, 0.85f)
        }
        return NativePHPChartsRadialConfiguration(
            kind = kind,
            segments = decodeSegments(input.segmentsJson),
            style = NativePHPChartsRadialStyle(
                gap = segmentStyle.optDouble("gap", 2.0).toFloat().coerceIn(0f, 12f),
                cornerRadius = segmentStyle.optDouble("corner_radius", 0.0).toFloat().coerceIn(0f, 20f),
                opacity = segmentStyle.optDouble("opacity", 1.0).toFloat().coerceIn(0f, 1f),
            ),
            legend = decodeRadialLegend(radialObject(input.legendJson)),
            locale = input.locale,
            valueFormat = input.valueFormat,
            currencyCode = input.currencyCode,
            minimumFractionDigits = input.minimumFractionDigits,
            maximumFractionDigits = input.maximumFractionDigits,
            animated = input.animated,
            emptyLabel = input.emptyLabel,
            accessibilityLabel = input.accessibilityLabel,
            onSelect = input.onSelect,
            innerRadiusRatio = innerRadiusRatio,
        )
    }

    private fun decodeSegments(json: String): List<NativePHPChartsRadialSegment> = try {
        val root = JSONArray(json)
        buildList {
            for (index in 0 until root.length()) {
                val item = root.optJSONObject(index)
                if (item == null) {
                    continue
                }
                val value = item.optDouble("value", Double.NaN)
                if (!value.isFinite() || value < 0.0) {
                    continue
                }
                val id = item.optString("id", "segment-$index")
                add(
                    NativePHPChartsRadialSegment(
                        id = id,
                        label = item.optString("label", id),
                        value = value,
                        color = chartColor(item.optString("color"), fallbackColors[index % fallbackColors.size]),
                        index = index,
                    ),
                )
            }
        }
    } catch (_: Exception) {
        emptyList()
    }

    private fun decodeRadialLegend(root: JSONObject): NativePHPChartsLegend {
        val style = root.optJSONObject("style")
        val markerSize = style?.run { optDouble("marker_size", optDouble("markerSize", 9.0)).toFloat() } ?: 9f
        val fontSize = style?.run { optDouble("font_size", optDouble("fontSize", 11.0)).toFloat() } ?: 11f
        val labelColor = style?.run { optString("label_color", optString("labelColor")) }
            ?.takeIf(String::isNotBlank)
        return NativePHPChartsLegend(
            visible = if (root.has("visible")) root.optBoolean("visible") else null,
            position = root.optString("position", "bottom"),
            alignment = root.optString("alignment", "center"),
            markerSize = markerSize,
            fontSize = fontSize,
            font = style?.optString("font")?.takeIf(String::isNotBlank),
            labelColor = labelColor,
        )
    }
}

private fun radialObject(json: String): JSONObject = try {
    JSONObject(json)
} catch (_: Exception) {
    JSONObject()
}
