package com.donmanueldev.plugins.nativephp_charts.ui

import java.text.NumberFormat
import java.util.Currency
import java.util.Locale

internal class NativePHPChartsRadialFormatting(configuration: NativePHPChartsRadialConfiguration) {
    private val locale = if (configuration.locale.isBlank()) Locale.getDefault() else Locale.forLanguageTag(configuration.locale)
    private val formatter: NumberFormat = when (configuration.valueFormat) {
        "currency" -> NumberFormat.getCurrencyInstance(locale).apply {
            runCatching { Currency.getInstance(configuration.currencyCode) }.getOrNull()?.let { currency = it }
        }
        "percent" -> NumberFormat.getPercentInstance(locale)
        else -> NumberFormat.getNumberInstance(locale)
    }.apply {
        if (configuration.minimumFractionDigits >= 0) minimumFractionDigits = configuration.minimumFractionDigits
        if (configuration.maximumFractionDigits >= 0) maximumFractionDigits = configuration.maximumFractionDigits
    }

    fun value(value: Double): String = formatter.format(value)
}
