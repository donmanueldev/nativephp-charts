package com.donmanueldev.plugins.nativephp_charts.ui

import androidx.compose.ui.graphics.toArgb
import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Test
import java.time.LocalDate

class NativePHPChartsContractTest {
    @Test
    fun `only contract version one is accepted`() {
        assertEquals(null, nativePHPChartsContractFailure(1))
        assertEquals("unsupported_contract_version", nativePHPChartsContractFailure(2)?.code)
        assertEquals("unsupported_contract_version", nativePHPChartsContractFailure(0)?.code)
    }

    @Test
    fun `progress rejects one malformed metric instead of partially rendering`() {
        val input = progressInput("""[{"id":"ok","label":"OK","value":0.5},{"id":"bad","label":"Bad","value":2}]""")
        val result = decodeNativePHPChartsProgress(input, systemDark = false)
        assertTrue(result is NativePHPChartsDecodeResult.Failure)
        assertEquals("invalid_progress_metric", (result as NativePHPChartsDecodeResult.Failure).code)
    }

    @Test
    fun `progress explicit color wins over theme palette`() {
        val input = progressInput("""[{"id":"m","label":"Metric","value":0.5,"color":"#FF0000"}]""")
        val result = decodeNativePHPChartsProgress(input, systemDark = false) as NativePHPChartsDecodeResult.Success
        assertEquals(0xFFFF0000.toInt(), result.value.metrics.single().color.toArgb())
    }

    @Test
    fun `heatmap rejects duplicate dates as one invalid snapshot`() {
        val values = """[{"id":"a","date":"2026-01-01","value":1},{"id":"b","date":"2026-01-01","value":2}]"""
        val result = decodeNativePHPChartsContribution(contributionInput(values), systemDark = false)
        assertTrue(result is NativePHPChartsDecodeResult.Failure)
        assertEquals("invalid_heatmap_value", (result as NativePHPChartsDecodeResult.Failure).code)
    }

    @Test
    fun `heatmap computes an inclusive date window`() {
        val values = """[{"id":"a","date":"2026-01-01","value":1}]"""
        val result = decodeNativePHPChartsContribution(contributionInput(values), false) as NativePHPChartsDecodeResult.Success
        assertEquals(LocalDate.parse("2025-12-27"), result.value.startDate)
        assertEquals(LocalDate.parse("2026-01-02"), result.value.endDate)
    }

    @Test
    fun `heatmap matches PHP range and empty end date defaults`() {
        val values = """[{"id":"a","date":"2026-01-01","value":1}]"""
        val tooShort = decodeNativePHPChartsContribution(contributionInput(values).copy(days = 6), false)
        val tooLong = decodeNativePHPChartsContribution(contributionInput(values).copy(days = 372), false)
        val implicitEnd = decodeNativePHPChartsContribution(contributionInput("[]").copy(endDate = ""), false)

        assertEquals("invalid_heatmap_range", (tooShort as NativePHPChartsDecodeResult.Failure).code)
        assertEquals("invalid_heatmap_range", (tooLong as NativePHPChartsDecodeResult.Failure).code)
        assertTrue(implicitEnd is NativePHPChartsDecodeResult.Success)
    }

    private fun progressInput(metrics: String) = NativePHPChartsProgressWireInput(
        contractVersion = 1,
        metricsJson = metrics,
        centerLabel = "",
        themeMode = "light",
        themeJson = """{"light":{"palette":["#00FF00"],"track":"#EEEEEE"}}""",
        preset = "default",
        animated = false,
        emptyLabel = "No data",
        errorLabel = "Unavailable",
        accessibilityLabel = "Progress",
        onSelect = 0,
    )

    private fun contributionInput(values: String) = NativePHPChartsContributionWireInput(
        contractVersion = 1,
        valuesJson = values,
        endDate = "2026-01-02",
        days = 7,
        weekStartsOn = 0,
        colorsJson = "[]",
        emptyColor = "",
        showMonthLabels = true,
        showWeekdayLabels = true,
        themeMode = "light",
        themeJson = "{}",
        preset = "default",
        animated = false,
        emptyLabel = "No data",
        errorLabel = "Unavailable",
        accessibilityLabel = "Heatmap",
        onSelect = 0,
    )
}
