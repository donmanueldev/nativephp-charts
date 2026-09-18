package com.donmanueldev.plugins.nativephp_charts.ui

import org.json.JSONObject
import org.junit.Assert.assertEquals
import org.junit.Test
import kotlinx.coroutines.test.runTest

class NativePHPChartsSharedFixturesTest {
    private val fixtures: JSONObject by lazy {
        val text = checkNotNull(javaClass.classLoader?.getResourceAsStream("chart_contract_fixtures.json"))
            .bufferedReader()
            .use { it.readText() }
        JSONObject(text)
    }

    @Test
    fun `shared version fixtures match Android contract gate`() = runTest {
        val valid = fixtures.getJSONObject("valid_v1")
        assertEquals(null, nativePHPChartsContractFailure(valid.getInt("contract_version")))
        val decoded = NativePHPChartsDecoder.decode(
            cartesianInput(valid.getInt("contract_version"), valid.getString("series_json")),
            NativePHPChartsKind.Line,
            systemDark = false,
        )
        assertEquals("jan", (decoded as NativePHPChartsDecodeResult.Success).value.series.single().points.single().id)
        val unsupported = fixtures.getJSONObject("unsupported_version")
        assertEquals(
            unsupported.getString("expected_error"),
            nativePHPChartsContractFailure(unsupported.getInt("contract_version"))?.code,
        )
    }

    @Test
    fun `shared corrupt JSON rejects the complete Cartesian snapshot`() = runTest {
        val fixture = fixtures.getJSONObject("corrupt_json")
        val result = NativePHPChartsDecoder.decode(
            cartesianInput(fixture.getInt("contract_version"), fixture.getString("series_json")),
            NativePHPChartsKind.Line,
            systemDark = false,
        )
        assertEquals(fixture.getString("expected_error"), (result as NativePHPChartsDecodeResult.Failure).code)
    }

    @Test
    fun `shared progress selection matches version one payload`() {
        assertSharedSelection("progress_selection", "progress", "x")
    }

    @Test
    fun `shared heatmap selection matches version one payload`() {
        assertSharedSelection("heatmap_selection", "contribution_heatmap", "date")
    }

    private fun assertSharedSelection(fixtureName: String, chartType: String, xField: String) {
        val fixture = fixtures.getJSONObject(fixtureName)
        val input = fixture.getJSONObject("input")
        val actual = JSONObject(
            nativePHPChartsSpecialSelectionPayload(
                chartType = chartType,
                id = input.getString("id"),
                label = input.getString("label"),
                index = input.getInt("index"),
                x = input.optString(xField, input.getString("label")),
                value = input.getDouble("value"),
                localizedValue = input.getString("localized_value"),
                xType = if (chartType == "contribution_heatmap") "date" else "category",
            ),
        )
        assertEquals(fixture.getJSONObject("expected").toString(), actual.toString())
    }

    private fun cartesianInput(version: Int, series: String) = NativePHPChartsWireInput(
        contractVersion = version,
        seriesJson = series,
        seriesTransport = "inline-v1",
        seriesJsonFile = "",
        styleJson = "{}",
        themeMode = "light",
        themeJson = "{}",
        preset = "default",
        xAxisJson = "{}",
        yAxisJson = "{}",
        legendJson = "{}",
        annotationsJson = "[]",
        interactionJson = "{}",
        viewportJson = "{}",
        samplingJson = "{}",
        areaMode = "overlay",
        barMode = "grouped",
        barOrientation = "vertical",
        emptyLabel = "No data",
        errorLabel = "Unavailable",
        accessibilityLabel = "Chart",
        locale = "",
        valueFormat = "number",
        currencyCode = "",
        minimumFractionDigits = -1,
        maximumFractionDigits = -1,
        showGrid = true,
        showPoints = true,
        beginAtZero = true,
        animated = false,
        onSelect = 0,
        onViewportChange = 0,
    )
}
