package com.donmanueldev.plugins.nativephp_charts.ui

import com.nativephp.mobile.ui.nativerender.NativeUIBridge
import com.nativephp.mobile.ui.nativerender.NativeUINode
import org.json.JSONObject

internal object NativePHPChartsRadialSelection {
    fun dispatch(
        node: NativeUINode,
        configuration: NativePHPChartsRadialConfiguration,
        formatting: NativePHPChartsRadialFormatting,
        segment: NativePHPChartsRadialSegment,
    ) {
        if (configuration.onSelect == 0) return
        val chartType = configuration.kind.name.lowercase()
        val payload = JSONObject()
            .put("version", 1)
            .put("chart_type", chartType)
            .put("series_id", segment.id)
            .put("series_name", segment.label)
            .put("point_id", segment.id)
            .put("point_index", segment.index)
            .put("x_type", "category")
            .put("x", segment.label)
            .put("label", segment.label)
            .put("value", segment.value)
            .put("localized_value", formatting.value(segment.value))
            .toString()
        NativeUIBridge.sendTextChangeEvent(configuration.onSelect, node.id, payload)
    }
}
