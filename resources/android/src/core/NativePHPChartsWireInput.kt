package com.donmanueldev.plugins.nativephp_charts.ui

import com.nativephp.mobile.ui.nativerender.NativeUINode

internal data class NativePHPChartsWireInput(
    val contractVersion: Int,
    val seriesJson: String,
    val styleJson: String,
    val xAxisJson: String,
    val yAxisJson: String,
    val legendJson: String,
    val areaMode: String,
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
) {
    companion object {
        fun from(node: NativeUINode): NativePHPChartsWireInput {
            val props = node.props

            return NativePHPChartsWireInput(
                contractVersion = props.getInt("contract_version", 0),
                seriesJson = props.getString("series_json", "[]"),
                styleJson = props.getString("style_json", "{}"),
                xAxisJson = props.getString("x_axis_json", "{}"),
                yAxisJson = props.getString("y_axis_json", "{}"),
                legendJson = props.getString("legend_json", "{}"),
                areaMode = props.getString("area_mode", "overlay"),
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
            )
        }
    }
}
