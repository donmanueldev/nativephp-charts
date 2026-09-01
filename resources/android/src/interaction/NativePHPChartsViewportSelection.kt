package com.donmanueldev.plugins.nativephp_charts.ui

import com.nativephp.mobile.ui.nativerender.NativeUIBridge
import com.nativephp.mobile.ui.nativerender.NativeUINode
import org.json.JSONObject

internal enum class NativePHPChartsViewportReason(val wireValue: String) {
    Pan("pan"),
    Zoom("zoom"),
    PanZoom("pan_zoom");

    companion object {
        fun from(panned: Boolean, zoomed: Boolean): NativePHPChartsViewportReason? = when {
            panned && zoomed -> PanZoom
            panned -> Pan
            zoomed -> Zoom
            else -> null
        }

        fun combine(
            current: NativePHPChartsViewportReason?,
            next: NativePHPChartsViewportReason,
        ): NativePHPChartsViewportReason = when {
            current == null || current == next -> next
            else -> PanZoom
        }
    }
}

internal object NativePHPChartsViewportSelection {
    fun dispatch(
        node: NativeUINode,
        configuration: NativePHPChartsConfiguration,
        formatting: NativePHPChartsFormatting,
        domain: NativePHPChartsDomain,
        reason: NativePHPChartsViewportReason,
    ) {
        if (configuration.onViewportChange == 0) return

        val payload = JSONObject()
            .put("version", 1)
            .put("chart_type", configuration.kind.name.lowercase())
            .put("axis", "x")
            .put("reason", reason.wireValue)
            .put("x_type", configuration.xAxis.type.name.lowercase())
            .put("minimum", formatting.xWire(domain.minimum))
            .put("maximum", formatting.xWire(domain.maximum))
            .toString()

        NativeUIBridge.sendTextChangeEvent(configuration.onViewportChange, node.id, payload)
    }
}
