package com.donmanueldev.plugins.nativephp_charts.ui

import java.text.DateFormat
import java.text.NumberFormat
import java.time.Instant
import java.time.LocalDate
import java.time.OffsetDateTime
import java.time.ZoneId
import java.util.Currency
import java.util.Date
import java.util.Locale
import kotlin.math.roundToLong

internal class NativePHPChartsFormatting(private val configuration: NativePHPChartsConfiguration) {
    private val locale = if (configuration.locale.isBlank()) Locale.getDefault() else Locale.forLanguageTag(configuration.locale)
    private val timezone = configuration.xAxis.timezone.takeIf(String::isNotBlank)?.let { runCatching { ZoneId.of(it) }.getOrNull() }
    private val xNumber = NumberFormat.getNumberInstance(locale)

    private val yNumber: NumberFormat = when (configuration.yAxis.valueFormat) {
        "currency" -> NumberFormat.getCurrencyInstance(locale).apply {
            runCatching { Currency.getInstance(configuration.yAxis.currencyCode) }.getOrNull()?.let { currency = it }
        }
        "percent" -> NumberFormat.getPercentInstance(locale)
        else -> NumberFormat.getNumberInstance(locale)
    }.apply {
        if (configuration.yAxis.minimumFractionDigits >= 0) {
            minimumFractionDigits = configuration.yAxis.minimumFractionDigits
        }
        if (configuration.yAxis.maximumFractionDigits >= 0) {
            maximumFractionDigits = configuration.yAxis.maximumFractionDigits
        }
    }
    private val xDateFormatter: DateFormat? = when (configuration.xAxis.type) {
        NativePHPChartsXType.Date, NativePHPChartsXType.Datetime -> {
            val style = when (configuration.xAxis.dateFormat) {
                "short" -> DateFormat.SHORT
                "long" -> DateFormat.LONG
                "full" -> DateFormat.FULL
                else -> DateFormat.MEDIUM
            }
            val formatter = when {
                configuration.xAxis.dateFormat == "time" -> DateFormat.getTimeInstance(DateFormat.SHORT, locale)
                configuration.xAxis.type == NativePHPChartsXType.Datetime -> DateFormat.getDateTimeInstance(style, DateFormat.SHORT, locale)
                else -> DateFormat.getDateInstance(style, locale)
            }
            formatter.apply {
                timezone?.let { timeZone = java.util.TimeZone.getTimeZone(it) }
            }
        }
        else -> null
    }

    fun value(value: Double): String = yNumber.format(value)

    fun x(point: NativePHPChartsPoint): String = when (configuration.xAxis.type) {
        NativePHPChartsXType.Category -> point.label
        NativePHPChartsXType.Number -> (point.x as? Number)?.let(xNumber::format) ?: point.label
        NativePHPChartsXType.Date, NativePHPChartsXType.Datetime -> formatDate(point.x?.toString()) ?: point.label
    }

    fun x(value: Double): String = when (configuration.xAxis.type) {
        NativePHPChartsXType.Category -> value.toString()
        NativePHPChartsXType.Number -> xNumber.format(value)
        NativePHPChartsXType.Date, NativePHPChartsXType.Datetime ->
            xDateFormatter?.format(Date.from(value.toInstant())) ?: value.toString()
    }

    fun xNumeric(value: Any?): Double? = when (configuration.xAxis.type) {
        NativePHPChartsXType.Category -> null
        NativePHPChartsXType.Number -> (value as? Number)?.toDouble() ?: value?.toString()?.toDoubleOrNull()
        NativePHPChartsXType.Date -> runCatching {
            LocalDate.parse(value.toString())
                .atStartOfDay(timezone ?: ZoneId.systemDefault())
                .toInstant()
                .preciseEpochSeconds()
        }.getOrNull()
        NativePHPChartsXType.Datetime -> parseInstant(value?.toString())?.preciseEpochSeconds()
    }

    fun xNumeric(point: NativePHPChartsPoint): Double? = when (configuration.xAxis.type) {
        NativePHPChartsXType.Category -> null
        NativePHPChartsXType.Number -> (point.x as? Number)?.toDouble() ?: point.x?.toString()?.toDoubleOrNull()
        NativePHPChartsXType.Date -> runCatching {
            LocalDate.parse(point.x.toString())
                .atStartOfDay(timezone ?: ZoneId.systemDefault())
                .toInstant()
                .preciseEpochSeconds()
        }.getOrNull()
        NativePHPChartsXType.Datetime -> parseInstant(point.x?.toString())?.preciseEpochSeconds()
    }

    fun geometryKey(point: NativePHPChartsPoint): String = when (configuration.xAxis.type) {
        NativePHPChartsXType.Category -> point.x?.toString() ?: point.label
        NativePHPChartsXType.Number, NativePHPChartsXType.Date, NativePHPChartsXType.Datetime ->
            xNumeric(point)?.toString() ?: point.x?.toString() ?: point.label
    }

    fun xWire(value: Double): Any = when (configuration.xAxis.type) {
        NativePHPChartsXType.Number -> value
        NativePHPChartsXType.Date -> value.toInstant().atZone(timezone ?: ZoneId.systemDefault()).toLocalDate().toString()
        NativePHPChartsXType.Datetime -> value.toMicrosecondInstant().toString()
        NativePHPChartsXType.Category -> value
    }

    private fun formatDate(raw: String?): String? {
        val value = raw ?: return null
        val instant = when (configuration.xAxis.type) {
            NativePHPChartsXType.Date -> runCatching {
                LocalDate.parse(value).atStartOfDay(timezone ?: ZoneId.systemDefault()).toInstant()
            }.getOrNull()
            else -> parseInstant(value)
        } ?: return null
        return xDateFormatter?.format(Date.from(instant))
    }

    private fun parseInstant(raw: String?): Instant? = raw?.let { value ->
        runCatching { Instant.parse(value) }
            .getOrElse { runCatching { OffsetDateTime.parse(value).toInstant() }.getOrNull() }
    }
}

private fun Instant.preciseEpochSeconds(): Double = epochSecond.toDouble() + (nano.toDouble() / 1_000_000_000.0)

private fun Double.toInstant(): Instant {
    val seconds = toLong()
    val nanos = ((this - seconds) * 1_000_000_000.0).toLong()
    return Instant.ofEpochSecond(seconds, nanos)
}

private fun Double.toMicrosecondInstant(): Instant {
    val epochMicroseconds = (this * 1_000_000.0).roundToLong()
    val seconds = Math.floorDiv(epochMicroseconds, 1_000_000L)
    val microseconds = Math.floorMod(epochMicroseconds, 1_000_000L)
    return Instant.ofEpochSecond(seconds, microseconds * 1_000L)
}
