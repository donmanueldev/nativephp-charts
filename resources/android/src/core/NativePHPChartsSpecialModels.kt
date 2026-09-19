package com.donmanueldev.plugins.nativephp_charts.ui

import androidx.compose.ui.graphics.Color
import com.nativephp.mobile.ui.nativerender.NativeUINode
import org.json.JSONArray
import org.json.JSONObject
import java.time.LocalDate
import java.time.ZoneOffset

internal data class NativePHPChartsProgressMetric(
    val id: String,
    val label: String,
    val value: Double,
    val color: Color,
    val index: Int,
)

internal data class NativePHPChartsProgressConfiguration(
    val metrics: List<NativePHPChartsProgressMetric>,
    val centerLabel: String,
    val trackColor: Color,
    val backgroundColor: Color,
    val ringWidth: Float,
    val ringGap: Float,
    val ringCap: String,
    val animated: Boolean,
    val emptyLabel: String,
    val errorLabel: String,
    val accessibilityLabel: String,
    val onSelect: Int,
    val locale: String,
    val minimumFractionDigits: Int,
    val maximumFractionDigits: Int,
    val legendVisible: Boolean,
) {
    val animationKey: Int = metrics.hashCode()
}

internal data class NativePHPChartsProgressWireInput(
    val contractVersion: Int,
    val metricsJson: String,
    val centerLabel: String,
    val styleJson: String,
    val legendJson: String,
    val themeMode: String,
    val themeJson: String,
    val preset: String,
    val locale: String,
    val minimumFractionDigits: Int,
    val maximumFractionDigits: Int,
    val animated: Boolean,
    val emptyLabel: String,
    val errorLabel: String,
    val accessibilityLabel: String,
    val onSelect: Int,
) {
    companion object {
        fun from(node: NativeUINode): NativePHPChartsProgressWireInput = node.props.let { props ->
            NativePHPChartsProgressWireInput(
                contractVersion = props.getInt("contract_version", 0),
                metricsJson = props.getString("metrics_json", "[]"),
                centerLabel = props.getString("center_label", ""),
                styleJson = props.getString("style_json", "{}"),
                legendJson = props.getString("legend_json", "{}"),
                themeMode = props.getString("theme_mode", "system"),
                themeJson = props.getString("theme_json", "{}"),
                preset = props.getString("preset", "default"),
                locale = props.getString("locale", ""),
                minimumFractionDigits = props.getInt("minimum_fraction_digits", -1),
                maximumFractionDigits = props.getInt("maximum_fraction_digits", -1),
                animated = props.getBool("animated", true),
                emptyLabel = props.getString("empty_label", "No data"),
                errorLabel = props.getString("error_label", "Chart unavailable"),
                accessibilityLabel = props.getString("a11y_label", "Progress chart"),
                onSelect = props.getCallbackId("on_select"),
            )
        }
    }
}

internal fun decodeNativePHPChartsProgress(
    input: NativePHPChartsProgressWireInput,
    systemDark: Boolean,
): NativePHPChartsDecodeResult<NativePHPChartsProgressConfiguration> {
    nativePHPChartsContractFailure(input.contractVersion)?.let { return it }
    val theme = nativePHPChartsTheme(input.themeJson, input.themeMode, systemDark)
    val ring = input.styleJson.asObject()?.optJSONObject("ring")
    val legend = input.legendJson.asObject()
    val root = runCatching { JSONArray(input.metricsJson) }.getOrNull()
        ?: return NativePHPChartsDecodeResult.Failure("malformed_progress_snapshot")
    val seen = mutableSetOf<String>()
    val fallback = listOf(
        Color(0xFF6366F1), Color(0xFF14B8A6), Color(0xFFF59E0B), Color(0xFFEC4899),
    )
    val metrics = buildList {
        for (index in 0 until root.length()) {
            val item = root.optJSONObject(index)
                ?: return NativePHPChartsDecodeResult.Failure("malformed_progress_snapshot")
            val id = item.optString("id")
            val label = item.optString("label")
            val value = item.optDouble("value", Double.NaN)
            if (id.isBlank() || label.isBlank() || !seen.add(id) || !value.isFinite() || value !in 0.0..1.0) {
                return NativePHPChartsDecodeResult.Failure("invalid_progress_metric")
            }
            val base = fallback[index % fallback.size]
            add(
                NativePHPChartsProgressMetric(
                    id = id,
                    label = label,
                    value = value,
                    color = item.optString("color").takeIf(String::isNotBlank)?.let { chartColor(it, base) }
                        ?: theme.color(index, base),
                    index = index,
                ),
            )
        }
    }
    return NativePHPChartsDecodeResult.Success(
        NativePHPChartsProgressConfiguration(
            metrics = metrics,
            centerLabel = input.centerLabel,
            trackColor = chartColor(theme.track, Color.Gray.copy(alpha = 0.18f)),
            backgroundColor = chartColor(theme.background, Color.Transparent),
            ringWidth = ring?.optDouble("width", 12.0)?.toFloat()?.coerceAtLeast(1f) ?: 12f,
            ringGap = ring?.optDouble("gap", 7.0)?.toFloat()?.coerceAtLeast(0f) ?: 7f,
            ringCap = ring?.optString("cap", "round") ?: "round",
            animated = input.animated,
            emptyLabel = input.emptyLabel,
            errorLabel = input.errorLabel,
            accessibilityLabel = input.accessibilityLabel,
            onSelect = input.onSelect,
            locale = input.locale,
            minimumFractionDigits = input.minimumFractionDigits,
            maximumFractionDigits = input.maximumFractionDigits,
            legendVisible = when (legend?.opt("visible")) { is Boolean -> legend.optBoolean("visible"); else -> metrics.size > 1 },
        ),
    )
}

internal data class NativePHPChartsContributionValue(
    val id: String,
    val date: LocalDate,
    val value: Double,
    val label: String,
    val index: Int,
)

internal data class NativePHPChartsContributionConfiguration(
    val values: List<NativePHPChartsContributionValue>,
    val endDate: LocalDate,
    val days: Int,
    val weekStartsOn: Int,
    val colors: List<Color>,
    val emptyColor: Color,
    val backgroundColor: Color,
    val cellSize: Float?,
    val cellGap: Float,
    val showMonthLabels: Boolean,
    val showWeekdayLabels: Boolean,
    val animated: Boolean,
    val emptyLabel: String,
    val errorLabel: String,
    val accessibilityLabel: String,
    val onSelect: Int,
    val locale: String,
    val minimumFractionDigits: Int,
    val maximumFractionDigits: Int,
) {
    val startDate: LocalDate = endDate.minusDays((days - 1).toLong())
    val visibleValues: List<NativePHPChartsContributionValue> = values.filter { it.date in startDate..endDate }
    val animationKey: Int = 31 * visibleValues.hashCode() + endDate.hashCode()
}

internal data class NativePHPChartsContributionWireInput(
    val contractVersion: Int,
    val valuesJson: String,
    val styleJson: String,
    val endDate: String,
    val days: Int,
    val weekStartsOn: Int,
    val colorsJson: String,
    val emptyColor: String,
    val showMonthLabels: Boolean,
    val showWeekdayLabels: Boolean,
    val themeMode: String,
    val themeJson: String,
    val preset: String,
    val locale: String,
    val minimumFractionDigits: Int,
    val maximumFractionDigits: Int,
    val animated: Boolean,
    val emptyLabel: String,
    val errorLabel: String,
    val accessibilityLabel: String,
    val onSelect: Int,
) {
    companion object {
        fun from(node: NativeUINode): NativePHPChartsContributionWireInput = node.props.let { props ->
            NativePHPChartsContributionWireInput(
                contractVersion = props.getInt("contract_version", 0),
                valuesJson = props.getString("values_json", "[]"),
                styleJson = props.getString("style_json", "{}"),
                endDate = props.getString("end_date", ""),
                days = props.getInt("days", 365),
                weekStartsOn = props.getInt("week_starts_on", 0),
                colorsJson = props.getString("colors_json", "[]"),
                emptyColor = props.getString("empty_color", ""),
                showMonthLabels = props.getBool("show_month_labels", true),
                showWeekdayLabels = props.getBool("show_weekday_labels", true),
                themeMode = props.getString("theme_mode", "system"),
                themeJson = props.getString("theme_json", "{}"),
                preset = props.getString("preset", "default"),
                locale = props.getString("locale", ""),
                minimumFractionDigits = props.getInt("minimum_fraction_digits", -1),
                maximumFractionDigits = props.getInt("maximum_fraction_digits", -1),
                animated = props.getBool("animated", true),
                emptyLabel = props.getString("empty_label", "No data"),
                errorLabel = props.getString("error_label", "Chart unavailable"),
                accessibilityLabel = props.getString("a11y_label", "Contribution heatmap"),
                onSelect = props.getCallbackId("on_select"),
            )
        }
    }
}

internal fun decodeNativePHPChartsContribution(
    input: NativePHPChartsContributionWireInput,
    systemDark: Boolean,
): NativePHPChartsDecodeResult<NativePHPChartsContributionConfiguration> {
    nativePHPChartsContractFailure(input.contractVersion)?.let { return it }
    if (input.days !in 7..371 || input.weekStartsOn !in 0..6) {
        return NativePHPChartsDecodeResult.Failure("invalid_heatmap_range")
    }
    val resolvedEndDate = input.endDate.ifBlank { LocalDate.now(ZoneOffset.UTC).toString() }
    val endDate = runCatching { LocalDate.parse(resolvedEndDate) }.getOrNull()
        ?: return NativePHPChartsDecodeResult.Failure("invalid_heatmap_end_date")
    val root = runCatching { JSONArray(input.valuesJson) }.getOrNull()
        ?: return NativePHPChartsDecodeResult.Failure("malformed_heatmap_snapshot")
    val seenIds = mutableSetOf<String>()
    val seenDates = mutableSetOf<LocalDate>()
    val values = buildList {
        for (index in 0 until root.length()) {
            val item = root.optJSONObject(index)
                ?: return NativePHPChartsDecodeResult.Failure("malformed_heatmap_snapshot")
            val id = item.optString("id")
            val date = runCatching { LocalDate.parse(item.optString("date")) }.getOrNull()
            val value = item.optDouble("value", Double.NaN)
            if (id.isBlank() || date == null || !seenIds.add(id) || !seenDates.add(date) || !value.isFinite() || value < 0.0) {
                return NativePHPChartsDecodeResult.Failure("invalid_heatmap_value")
            }
            add(NativePHPChartsContributionValue(id, date, value, item.optString("label", date.toString()), index))
        }
    }
    val theme = nativePHPChartsTheme(input.themeJson, input.themeMode, systemDark)
    val cell = input.styleJson.asObject()?.optJSONObject("cell")
    val defaultColors = listOf("#DCFCE7", "#86EFAC", "#22C55E", "#15803D")
    val colorStrings = runCatching { JSONArray(input.colorsJson) }.getOrNull()?.let { array ->
        (0 until array.length()).mapNotNull { array.optString(it).takeIf(String::isNotBlank) }
    }.orEmpty().ifEmpty { theme.palette.ifEmpty { defaultColors } }
    val colors = colorStrings.mapIndexed { index, value ->
        chartColor(value, chartColor(defaultColors[index % defaultColors.size], Color(0xFF22C55E)))
    }
    return NativePHPChartsDecodeResult.Success(
        NativePHPChartsContributionConfiguration(
            values = values,
            endDate = endDate,
            days = input.days,
            weekStartsOn = input.weekStartsOn,
            colors = colors,
            emptyColor = chartColor(input.emptyColor.ifBlank { theme.track }, Color.Gray.copy(alpha = 0.16f)),
            backgroundColor = chartColor(theme.background, Color.Transparent),
            cellSize = cell?.takeIf { it.has("size") }?.optDouble("size")?.toFloat()?.coerceAtLeast(1f),
            cellGap = cell?.optDouble("gap", 3.0)?.toFloat()?.coerceAtLeast(0f) ?: 3f,
            showMonthLabels = input.showMonthLabels,
            showWeekdayLabels = input.showWeekdayLabels,
            animated = input.animated,
            emptyLabel = input.emptyLabel,
            errorLabel = input.errorLabel,
            accessibilityLabel = input.accessibilityLabel,
            onSelect = input.onSelect,
            locale = input.locale,
            minimumFractionDigits = input.minimumFractionDigits,
            maximumFractionDigits = input.maximumFractionDigits,
        ),
    )
}

internal fun nativePHPChartsSpecialSelectionPayload(
    chartType: String,
    id: String,
    label: String,
    index: Int,
    x: String,
    value: Double,
    localizedValue: String,
    xType: String = "category",
): Map<String, Any> = linkedMapOf(
    "version" to 1,
    "chart_type" to chartType,
    "series_id" to id,
    "series_name" to label,
    "point_id" to id,
    "point_index" to index,
    "x_type" to xType,
    "x" to x,
    "label" to label,
    "value" to value,
    "localized_value" to localizedValue,
)
