package com.donmanueldev.plugins.nativephp_charts.ui

import com.nativephp.mobile.ui.nativerender.NativeUIBridge
import com.nativephp.mobile.ui.nativerender.NativeUINode
import org.json.JSONObject

internal object NativePHPChartsSelection {
    fun dispatch(
        node: NativeUINode,
        configuration: NativePHPChartsConfiguration,
        formatting: NativePHPChartsFormatting,
        datum: NativePHPChartsDatum,
    ) {
        if (configuration.onSelect == 0) return
        val point = datum.point
        val payload = JSONObject()
            .put("version", 1)
            .put("chart_type", configuration.kind.name.lowercase())
            .put("series_id", datum.series.id)
            .put("series_name", datum.series.name)
            .put("point_id", point.id)
            .put("point_index", point.index)
            .put("x_type", configuration.xAxis.type.name.lowercase())
            .put("x", point.x)
            .put("label", point.label)
            .put("value", point.value)
            .put("localized_value", formatting.value(point.value))
            .toString()
        NativeUIBridge.sendTextChangeEvent(configuration.onSelect, node.id, payload)
    }
}

internal fun NativePHPChartsConfiguration.accessibilitySummary(formatting: NativePHPChartsFormatting): String {
    val totalPoints = series.sumOf { it.points.size }
    val preview = series.take(3).joinToString(". ") { item ->
        val points = item.points.take(5).joinToString { point -> "${formatting.x(point)}: ${formatting.value(point.value)}" }
        "${item.name}. $points"
    }
    val remainder = (totalPoints - series.take(3).sumOf { minOf(it.points.size, 5) }).coerceAtLeast(0)
    return buildString {
        append(accessibilityLabel)
        if (preview.isNotBlank()) append(". ").append(preview)
        if (remainder > 0) append(". … (+").append(remainder).append(')')
    }
}
