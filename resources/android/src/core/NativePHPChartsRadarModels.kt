package com.donmanueldev.plugins.nativephp_charts.ui

import androidx.compose.ui.graphics.Color
import java.text.NumberFormat
import java.util.Currency
import java.util.Locale

/**
 * Decoded radar contract and its presentation-independent formatting rules.
 * The Compose renderer only draws this already validated state.
 */
internal data class NativePHPChartsRadarAxis(val id: String, val label: String, val maximum: Double)
internal data class NativePHPChartsRadarValue(val axis: String, val value: Double)
internal data class NativePHPChartsRadarSeries(
    val id: String,
    val name: String,
    val color: Color,
    val values: List<NativePHPChartsRadarValue>,
)

/**
 * Stable selection identity for one series/axis pair. The byte-length prefix
 * prevents ambiguous concatenation when ids have variable length.
 */
internal data class NativePHPChartsRadarSelection(
    val series: NativePHPChartsRadarSeries,
    val axis: NativePHPChartsRadarAxis,
    val value: NativePHPChartsRadarValue,
    val index: Int,
) {
    val id: String get() = "${series.id.encodeToByteArray().size}:${series.id}${axis.id}"
}

internal data class NativePHPChartsRadarStyle(
    val lineColor: String?,
    val lineWidth: Float,
    val interpolation: String,
    val dash: List<Float>,
    val areaOpacity: Float,
    val areaGradient: Boolean,
    val pointsVisible: Boolean,
    val pointColor: String?,
    val pointSize: Float,
    val gridVisible: Boolean,
    val gridColor: String?,
    val gridWidth: Float,
    val axisVisible: Boolean,
    val axisColor: String?,
    val axisLabelColor: String?,
    val axisFont: String?,
    val axisFontSize: Float,
)

/**
 * Decoded radar state whose axis order defines both geometry and keyboard-style
 * accessibility traversal. A series value participates only when its position
 * and axis id both match the corresponding axis; at least three axes are required.
 */
internal data class NativePHPChartsRadarConfiguration(
    val axes: List<NativePHPChartsRadarAxis>,
    val series: List<NativePHPChartsRadarSeries>,
    val style: NativePHPChartsRadarStyle,
    val legend: NativePHPChartsLegend,
    val locale: String,
    val valueFormat: String,
    val currencyCode: String,
    val minimumFractionDigits: Int,
    val maximumFractionDigits: Int,
    val animated: Boolean,
    val emptyLabel: String,
    val accessibilityLabel: String,
    val onSelect: Int,
    val gridLevels: Int,
) {
    val selections: List<NativePHPChartsRadarSelection> = series.flatMap { item ->
        item.values.mapIndexedNotNull { index, value ->
            axes.getOrNull(index)?.takeIf { it.id == value.axis }?.let {
                NativePHPChartsRadarSelection(item, it, value, index)
            }
        }
    }
    val hasData: Boolean get() = axes.size >= 3 && selections.isNotEmpty()
    val legendVisible: Boolean get() = legend.visible ?: (series.size > 1)
    val animationKey: Int get() = 31 * axes.hashCode() + series.hashCode()
}

internal class NativePHPChartsRadarFormatting(configuration: NativePHPChartsRadarConfiguration) {
    private val formatter: NumberFormat = when (configuration.valueFormat) {
        "currency" -> NumberFormat.getCurrencyInstance(configuration.resolvedLocale()).apply {
            runCatching { Currency.getInstance(configuration.currencyCode) }.getOrNull()?.let { currency = it }
        }
        "percent" -> NumberFormat.getPercentInstance(configuration.resolvedLocale())
        else -> NumberFormat.getNumberInstance(configuration.resolvedLocale())
    }.apply {
        if (configuration.minimumFractionDigits >= 0) minimumFractionDigits = configuration.minimumFractionDigits
        if (configuration.maximumFractionDigits >= 0) maximumFractionDigits = configuration.maximumFractionDigits
    }

    fun value(value: Double): String = formatter.format(value)
}

internal fun NativePHPChartsRadarConfiguration.resolvedLocale(): Locale =
    if (locale.isBlank()) Locale.getDefault() else Locale.forLanguageTag(locale)
