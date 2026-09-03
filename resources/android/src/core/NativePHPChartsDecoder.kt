package com.donmanueldev.plugins.nativephp_charts.ui

import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.toArgb
import com.nativephp.mobile.ui.nativerender.ColorParser
import org.json.JSONArray
import org.json.JSONObject

/**
 * Converts the renderer-neutral Cartesian wire contract into Compose models.
 *
 * The PHP layer performs authoritative validation. This native boundary remains
 * fail-soft for stale or independently produced payloads: malformed roots become
 * empty/default objects, non-finite points are discarded, and values with
 * explicit public bounds are clamped before geometry or drawing code.
 */
internal object NativePHPChartsDecoder {
    /**
     * Produces one immutable configuration snapshot for a Compose render.
     * Series-level style remains nullable so rendering can preserve the explicit
     * precedence of series override, global style, then platform fallback.
     */
    fun decode(input: NativePHPChartsWireInput, kind: NativePHPChartsKind): NativePHPChartsConfiguration {
        val styleRoot = input.styleJson.asObject()
        val legacyAxis = styleRoot.optJSONObject("axis")

        return NativePHPChartsConfiguration(
            kind = kind,
            series = decodeSeries(input.seriesJson),
            style = decodeStyle(styleRoot, kind),
            xAxis = decodeXAxis(input.xAxisJson.asObject(), legacyAxis),
            yAxis = decodeYAxis(input.yAxisJson.asObject(), legacyAxis, input),
            legend = decodeLegend(input.legendJson.asObject()),
            annotations = decodeAnnotations(input.annotationsJson),
            interaction = decodeInteraction(input.interactionJson.asObject()),
            viewport = decodeViewport(input.viewportJson.asObject()),
            areaMode = input.areaMode,
            barMode = input.barMode,
            barOrientation = input.barOrientation,
            showGrid = input.showGrid,
            showPoints = input.showPoints,
            beginAtZero = input.beginAtZero,
            animated = input.animated,
            emptyLabel = input.emptyLabel,
            accessibilityLabel = input.accessibilityLabel,
            locale = input.locale,
            onSelect = input.onSelect,
            onViewportChange = input.onViewportChange,
        )
    }

    private fun decodeSeries(json: String): List<NativePHPChartsSeries> = try {
        val root = JSONArray(json)
        buildList {
            for (seriesIndex in 0 until root.length()) {
                val item = root.optJSONObject(seriesIndex)
                if (item == null) {
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
                                    continue
                                }
                                val value = point.optDouble("value", Double.NaN)
                                if (!value.isFinite()) {
                                    continue
                                }
                                add(
                                    NativePHPChartsPoint(
                                        id = point.optString("id", "$id-$pointIndex"),
                                        label = point.optString("label"),
                                        value = value,
                                        x = point.opt("x").takeUnless { it == JSONObject.NULL },
                                        index = point.optInt("source_index", pointIndex),
                                        errorMin = point.doubleOrNull("error_min"),
                                        errorMax = point.doubleOrNull("error_max"),
                                        open = point.doubleOrNull("open"),
                                        high = point.doubleOrNull("high"),
                                        low = point.doubleOrNull("low"),
                                        close = point.doubleOrNull("close"),
                                    ),
                                )
                            }
                        },
                        index = seriesIndex,
                        style = item.optJSONObject("style")?.let(::decodeSeriesStyle),
                        fillTo = item.optionalString("fill_to"),
                    ),
                )
            }
        }
    } catch (_: Exception) {
        emptyList()
    }

    private fun decodeSeriesStyle(root: JSONObject): NativePHPChartsSeriesStyle {
        val line = root.optJSONObject("line")
        val points = root.optJSONObject("points")
        val area = root.optJSONObject("area")
        val bar = root.optJSONObject("bar")
        val candlestick = root.optJSONObject("candlestick")

        return NativePHPChartsSeriesStyle(
            lineColor = line?.optionalString("color"),
            lineWidth = line?.floatOrNull("width"),
            interpolation = line?.optionalString("interpolation"),
            dash = line?.optJSONArray("dash")?.floatList(),
            pointColor = points?.optionalString("color"),
            pointSize = points?.floatOrNull("size"),
            pointsVisible = points.booleanOrNull("visible"),
            areaOpacity = area?.floatOrNull("opacity")?.coerceIn(0f, 1f),
            areaGradient = area.booleanOrNull("gradient"),
            barRadius = bar?.floatOrNull("radius"),
            barWidth = bar?.floatOrNull("width"),
            candlestickRisingColor = candlestick?.optionalString("rising_color"),
            candlestickFallingColor = candlestick?.optionalString("falling_color"),
            candlestickNeutralColor = candlestick?.optionalString("neutral_color"),
            candlestickWickWidth = candlestick?.floatOrNull("wick_width"),
        )
    }

    private fun decodeStyle(root: JSONObject, kind: NativePHPChartsKind): NativePHPChartsStyle {
        val line = root.optJSONObject("line")
        val points = root.optJSONObject("points")
        val grid = root.optJSONObject("grid")
        val area = root.optJSONObject("area")
        val bar = root.optJSONObject("bar")
        val axis = root.optJSONObject("axis")
        val candlestick = root.optJSONObject("candlestick")

        return NativePHPChartsStyle(
            lineColor = line?.optString("color")?.takeIf(String::isNotBlank),
            lineWidth = line.float("width", 3f),
            interpolation = line?.optString("interpolation", "linear") ?: "linear",
            dash = line?.optJSONArray("dash")?.floatList().orEmpty(),
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
            candlestickRisingColor = candlestick?.optionalString("rising_color"),
            candlestickFallingColor = candlestick?.optionalString("falling_color"),
            candlestickNeutralColor = candlestick?.optionalString("neutral_color"),
            candlestickWickWidth = candlestick.float("wick_width", 1.5f),
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
        NativePHPChartsKind.Candlestick -> 4f
    }

    private fun decodeXAxis(value: JSONObject?, fallback: JSONObject?): NativePHPChartsXAxis {
        val axis = value ?: JSONObject()
        val labelCount = when {
            axis.has("label_count") -> axis.optInt("label_count", 4)
            axis.has("labelCount") -> axis.optInt("labelCount", 4)
            fallback?.has("label_count") == true -> fallback.optInt("label_count", 4)
            fallback?.has("labelCount") == true -> fallback.optInt("labelCount", 4)
            else -> 4
        }
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
            title = axis?.optionalString("title"),
            minimum = axis?.optionalValue("minimum"),
            maximum = axis?.optionalValue("maximum"),
            baseline = axis?.optionalValue("baseline"),
            interval = axis?.doubleOrNull("interval"),
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
            title = axis.optionalString("title"),
            minimum = axis.doubleOrNull("minimum"),
            maximum = axis.doubleOrNull("maximum"),
            baseline = axis.doubleOrNull("baseline"),
            interval = axis.doubleOrNull("interval"),
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

    private fun decodeAnnotations(json: String): List<NativePHPChartsAnnotation> = try {
        val root = JSONArray(json)
        buildList {
            for (index in 0 until root.length()) {
                val item = root.optJSONObject(index) ?: continue
                add(
                    NativePHPChartsAnnotation(
                        id = item.optString("id", "annotation-$index"),
                        type = item.optString("type", "line"),
                        axis = item.optString("axis", "y"),
                        color = chartColor(item.optString("color"), Color(0xFF6366F1)),
                        label = item.optionalString("label"),
                        value = item.optionalValue("value"),
                        from = item.optionalValue("from"),
                        to = item.optionalValue("to"),
                        width = item.float("width", 1f),
                        opacity = item.float("opacity", 0.12f).coerceIn(0f, 1f),
                    ),
                )
            }
        }
    } catch (_: Exception) {
        emptyList()
    }

    private fun decodeInteraction(root: JSONObject): NativePHPChartsInteraction = NativePHPChartsInteraction(
        enabled = root.optBoolean("enabled", true),
        mode = root.optString("mode", "tap"),
        crosshair = root.optString("crosshair", "x"),
        tooltip = root.optString("tooltip", "single"),
    )

    private fun decodeViewport(root: JSONObject): NativePHPChartsViewport = NativePHPChartsViewport(
        enabled = root.optBoolean("enabled", false),
        pan = root.optBoolean("pan", true),
        zoom = root.optBoolean("zoom", true),
        minimum = root.optionalValue("minimum"),
        maximum = root.optionalValue("maximum"),
        minimumSpan = root.doubleOrNull("minimum_span"),
    )
}

private fun String.asObject(): JSONObject = try {
    JSONObject(this)
} catch (_: Exception) {
    JSONObject()
}

private fun JSONObject?.float(name: String, fallback: Float): Float =
    this?.optDouble(name, fallback.toDouble())?.toFloat() ?: fallback

private fun JSONObject?.booleanOrNull(name: String): Boolean? =
    if (this?.has(name) == true) optBoolean(name) else null

private fun JSONObject?.intOrNull(name: String): Int? =
    if (this?.has(name) == true) optInt(name) else null

private fun JSONObject.optionalString(name: String): String? =
    if (has(name)) optString(name).takeIf(String::isNotBlank) else null

private fun JSONObject.optionalValue(name: String): Any? =
    if (has(name)) opt(name).takeUnless { it == JSONObject.NULL } else null

private fun JSONObject.doubleOrNull(name: String): Double? =
    if (has(name)) optDouble(name).takeIf(Double::isFinite) else null

private fun JSONObject.floatOrNull(name: String): Float? = doubleOrNull(name)?.toFloat()

private fun JSONArray.floatList(): List<Float> = buildList {
    for (index in 0 until length()) {
        optDouble(index).takeIf(Double::isFinite)?.toFloat()?.let(::add)
    }
}

/** Resolves the shared color grammar and preserves the supplied fallback on absent/invalid input. */
internal fun chartColor(value: String?, fallback: Color): Color = value
    ?.takeIf(String::isNotBlank)
    ?.let { Color(ColorParser.parse(it, fallback.toArgb())) }
    ?: fallback
