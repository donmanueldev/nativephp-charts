package com.donmanueldev.plugins.nativephp_charts.ui

import com.nativephp.mobile.ui.nativerender.NativeUINode

/**
 * Raw pie/donut props copied from the NativePHP node before JSON decoding.
 *
 * Structured payloads remain opaque here so [NativePHPChartsRadialDecoder] is
 * the single place that applies radial defaults, filtering, and clamping.
 */
internal data class NativePHPChartsRadialWireInput(
    val contractVersion: Int,
    val segmentsJson: String,
    val styleJson: String,
    val themeMode: String,
    val themeJson: String,
    val preset: String,
    val legendJson: String,
    val locale: String,
    val valueFormat: String,
    val currencyCode: String,
    val minimumFractionDigits: Int,
    val maximumFractionDigits: Int,
    val animated: Boolean,
    val emptyLabel: String,
    val errorLabel: String,
    val accessibilityLabel: String,
    val onSelect: Int,
    val innerRadiusRatio: Float,
) {
    companion object {
        /** Captures stable values, including the zero callback id used for an unbound `_select`. */
        fun from(node: NativeUINode): NativePHPChartsRadialWireInput {
            val props = node.props
            return NativePHPChartsRadialWireInput(
                contractVersion = props.getInt("contract_version", 0),
                segmentsJson = props.getString("segments_json", "[]"),
                styleJson = props.getString("style_json", "{}"),
                themeMode = props.getString("theme_mode", "system"),
                themeJson = props.getString("theme_json", "{}"),
                preset = props.getString("preset", "default"),
                legendJson = props.getString("legend_json", "{}"),
                locale = props.getString("locale", ""),
                valueFormat = props.getString("value_format", "number"),
                currencyCode = props.getString("currency_code", ""),
                minimumFractionDigits = props.getInt("minimum_fraction_digits", -1),
                maximumFractionDigits = props.getInt("maximum_fraction_digits", -1),
                animated = props.getBool("animated", true),
                emptyLabel = props.getString("empty_label", "No data"),
                errorLabel = props.getString("error_label", "Chart unavailable"),
                accessibilityLabel = props.getString("a11y_label", "Chart"),
                onSelect = props.getCallbackId("on_select"),
                innerRadiusRatio = props.getFloat("inner_radius_ratio", 0.6f),
            )
        }
    }
}
