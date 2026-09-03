package com.donmanueldev.plugins.nativephp_charts.ui

import com.nativephp.mobile.ui.nativerender.NativeUIBridge
import com.nativephp.mobile.ui.nativerender.NativeUINode
import org.json.JSONObject

/** Serializes a confirmed pie/donut segment selection for NativePHP. */
internal object NativePHPChartsRadialSelection {
    /**
     * Emits once per caller-confirmed tap or accessibility action when `_select`
     * is bound. A radial segment maps to both series and point fields so it stays
     * compatible with the common version-1 selection payload.
     */
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
