package com.donmanueldev.plugins.nativephp_charts.ui

import android.util.Log
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.toArgb
import com.nativephp.mobile.ui.nativerender.ColorParser
import org.json.JSONArray
import org.json.JSONObject

internal object NativePHPChartsDecoder {
    fun decode(input: NativePHPChartsWireInput, kind: NativePHPChartsKind): NativePHPChartsConfiguration {
        val styleRoot = input.styleJson.asObject("style_json")
        val legacyAxis = styleRoot.optJSONObject("axis")

        return NativePHPChartsConfiguration(
            kind = kind,
            series = decodeSeries(input.seriesJson),
            style = decodeStyle(styleRoot, kind),
            xAxis = decodeXAxis(input.xAxisJson.asObject("x_axis_json"), legacyAxis),
            yAxis = decodeYAxis(input.yAxisJson.asObject("y_axis_json"), legacyAxis, input),
            legend = decodeLegend(input.legendJson.asObject("legend_json")),
            areaMode = input.areaMode,
            showGrid = input.showGrid,
            showPoints = input.showPoints,
            beginAtZero = input.beginAtZero,
            animated = input.animated,
            emptyLabel = input.emptyLabel,
            accessibilityLabel = input.accessibilityLabel,
            locale = input.locale,
            onSelect = input.onSelect,
        )
    }

    private fun decodeSeries(json: String): List<NativePHPChartsSeries> = try {
        val root = JSONArray(json)
        buildList {
            for (seriesIndex in 0 until root.length()) {
                val item = root.optJSONObject(seriesIndex)
                if (item == null) {
                    Log.w(LOG_TAG, "Ignoring non-object chart series at index $seriesIndex")
                    continue
                }
                val id = item.optString("id", "series-$seriesIndex")
                val points = item.optJSONArray("points") ?: JSONArray()
                add(
                    NativePHPChartsSeries(
                        id = id,
                        name = item.optString("name", id),
                        color = chartColor(item.optString("color"), Color(0xFF6366F1)),
                        points = buildList {
                            for (pointIndex in 0 until points.length()) {
                                val point = points.optJSONObject(pointIndex)
                                if (point == null) {
                                    Log.w(LOG_TAG, "Ignoring non-object chart point at series $seriesIndex, index $pointIndex")
                                    continue
                                }
                                val value = point.optDouble("value", Double.NaN)
                                if (!value.isFinite()) {
                                    Log.w(LOG_TAG, "Ignoring non-finite chart point at series $seriesIndex, index $pointIndex")
                                    continue
                                }
                                add(
                                    NativePHPChartsPoint(
                                        id = point.optString("id", "$id-$pointIndex"),
                                        label = point.optString("label"),
                                        value = value,
                                        x = point.opt("x").takeUnless { it == JSONObject.NULL },
                                        index = pointIndex,
                                    ),
                                )
                            }
                        },
                        index = seriesIndex,
                    ),
                )
            }
        }
    } catch (exception: Exception) {
        Log.w(LOG_TAG, "Unable to decode series_json; rendering the empty state", exception)
        emptyList()
    }

    private fun decodeStyle(root: JSONObject, kind: NativePHPChartsKind): NativePHPChartsStyle {
        val line = root.optJSONObject("line")
        val points = root.optJSONObject("points")
        val grid = root.optJSONObject("grid")
        val area = root.optJSONObject("area")
        val bar = root.optJSONObject("bar")
        val axis = root.optJSONObject("axis")

        return NativePHPChartsStyle(
            lineColor = line?.optString("color")?.takeIf(String::isNotBlank),
            lineWidth = line.float("width", 3f),
            smooth = line?.optString("interpolation") == "smooth",
            pointColor = points?.optString("color")?.takeIf(String::isNotBlank),
            pointSize = points.float("size", defaultPointSize(kind)),
            pointsVisible = points.booleanOrNull("visible"),
            gridColor = grid?.optString("color")?.takeIf(String::isNotBlank),
            gridVisible = grid.booleanOrNull("visible"),
            gridWidth = grid.float("width", 1f),
            areaOpacity = area.float("opacity", 0.28f).coerceIn(0f, 1f),
            areaGradient = area.booleanOrNull("gradient") ?: true,
            barRadius = bar.float("radius", 5f),
            barWidth = bar?.takeIf { it.has("width") }?.optDouble("width")?.toFloat(),
            axisVisible = axis.booleanOrNull("visible"),
            axisColor = axis?.optString("color")?.takeIf(String::isNotBlank),
            axisLabelCount = axis.intOrNull("label_count"),
            axisFont = axis?.optString("font")?.takeIf(String::isNotBlank),
            axisFontSize = axis.float("font_size", 10f),
            axisLabelColor = axis?.optString("label_color")?.takeIf(String::isNotBlank),
        )
    }

    private fun defaultPointSize(kind: NativePHPChartsKind): Float = when (kind) {
        NativePHPChartsKind.Line -> 5f
        NativePHPChartsKind.Area -> 4.5f
        NativePHPChartsKind.Scatter -> 7f
        NativePHPChartsKind.Bar -> 4f
    }

    private fun decodeXAxis(value: JSONObject?, fallback: JSONObject?): NativePHPChartsXAxis {
        val axis = value ?: fallback
        val labelCount = axis?.let { it.optInt("label_count", it.optInt("labelCount", 4)) } ?: 4
        val type = when (axis?.optString("type", "category")) {
            "number" -> NativePHPChartsXType.Number
            "date" -> NativePHPChartsXType.Date
            "datetime" -> NativePHPChartsXType.Datetime
            else -> NativePHPChartsXType.Category
        }

        return NativePHPChartsXAxis(
            type = type,
            visible = axis.booleanOrNull("visible"),
            labelCount = labelCount.coerceIn(2, 12),
            dateFormat = axis?.optString("date_format", axis.optString("dateFormat", "medium")) ?: "medium",
            timezone = axis?.optString("timezone", axis.optString("timeZone", "")) ?: "",
        )
    }

    private fun decodeYAxis(
        value: JSONObject?,
        legacyStyle: JSONObject?,
        input: NativePHPChartsWireInput,
    ): NativePHPChartsYAxis {
        val axis = value ?: JSONObject()
        val labelCount = when {
            axis.has("label_count") -> axis.optInt("label_count", 4)
            axis.has("labelCount") -> axis.optInt("labelCount", 4)
            legacyStyle?.has("label_count") == true -> legacyStyle.optInt("label_count", 4)
            legacyStyle?.has("labelCount") == true -> legacyStyle.optInt("labelCount", 4)
            else -> 4
        }
        return NativePHPChartsYAxis(
            visible = axis.booleanOrNull("visible"),
            labelCount = labelCount.coerceIn(2, 12),
            valueFormat = axis.optString("value_format", input.valueFormat),
            currencyCode = axis.optString("currency_code", input.currencyCode),
            minimumFractionDigits = axis.optInt("minimum_fraction_digits", input.minimumFractionDigits),
            maximumFractionDigits = axis.optInt("maximum_fraction_digits", input.maximumFractionDigits),
        )
    }

    private fun decodeLegend(root: JSONObject): NativePHPChartsLegend {
        val style = root.optJSONObject("style")
        return NativePHPChartsLegend(
            visible = root.booleanOrNull("visible"),
            position = root.optString("position", "bottom"),
            alignment = root.optString("alignment", "center"),
            markerSize = style.float("marker_size", style.float("markerSize", 9f)),
            fontSize = style.float("font_size", style.float("fontSize", 11f)),
            font = style?.optString("font")?.takeIf(String::isNotBlank),
            labelColor = style?.optString("label_color", style.optString("labelColor"))?.takeIf(String::isNotBlank),
        )
    }
}

private const val LOG_TAG = "NativePHPCharts"

private fun String.asObject(property: String): JSONObject = try {
    JSONObject(this)
} catch (exception: Exception) {
    Log.w(LOG_TAG, "Unable to decode $property; using defaults", exception)
    JSONObject()
}

private fun JSONObject?.float(name: String, fallback: Float): Float =
    this?.optDouble(name, fallback.toDouble())?.toFloat() ?: fallback

private fun JSONObject?.booleanOrNull(name: String): Boolean? =
    if (this?.has(name) == true) optBoolean(name) else null

private fun JSONObject?.intOrNull(name: String): Int? =
    if (this?.has(name) == true) optInt(name) else null

internal fun chartColor(value: String?, fallback: Color): Color = value
    ?.takeIf(String::isNotBlank)
    ?.let { Color(ColorParser.parse(it, fallback.toArgb())) }
    ?: fallback
