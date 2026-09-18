package com.donmanueldev.plugins.nativephp_charts.harness

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.donmanueldev.plugins.nativephp_charts.ui.NativePHPChartsAreaChartRenderer
import com.donmanueldev.plugins.nativephp_charts.ui.NativePHPChartsBarChartRenderer
import com.donmanueldev.plugins.nativephp_charts.ui.NativePHPChartsCandlestickChartRenderer
import com.donmanueldev.plugins.nativephp_charts.ui.NativePHPChartsContributionHeatmapRenderer
import com.donmanueldev.plugins.nativephp_charts.ui.NativePHPChartsDonutChartRenderer
import com.donmanueldev.plugins.nativephp_charts.ui.NativePHPChartsLineChartRenderer
import com.donmanueldev.plugins.nativephp_charts.ui.NativePHPChartsPieChartRenderer
import com.donmanueldev.plugins.nativephp_charts.ui.NativePHPChartsProgressChartRenderer
import com.donmanueldev.plugins.nativephp_charts.ui.NativePHPChartsRadarChartRenderer
import com.donmanueldev.plugins.nativephp_charts.ui.NativePHPChartsScatterChartRenderer
import com.nativephp.mobile.ui.nativerender.GenericProps
import com.nativephp.mobile.ui.nativerender.NativeUINode
import java.time.LocalDate
import kotlin.math.absoluteValue

/** Installed-app gallery used only to produce reviewable native evidence. */
class NativePHPChartsGalleryActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        val chart = intent.getStringExtra(EXTRA_CHART).orEmpty().lowercase()
        require(chart in CHARTS) { "chart must be one of ${CHARTS.joinToString()}" }

        setContent {
            MaterialTheme {
                Column(
                    Modifier
                        .fillMaxSize()
                        .background(Color(0xFFF7F5F2))
                        .padding(horizontal = 20.dp, vertical = 24.dp),
                ) {
                    Text("NativePHP Charts", color = Color(0xFF6B625B), fontSize = 14.sp)
                    Text(
                        chart.replace('_', ' ').replaceFirstChar(Char::uppercase),
                        color = Color(0xFF211A16),
                        fontSize = 28.sp,
                        fontWeight = FontWeight.Bold,
                        modifier = Modifier.padding(top = 4.dp, bottom = 16.dp),
                    )
                    RenderChart(chart, galleryNode(chart), Modifier.fillMaxWidth().weight(1f))
                }
            }
        }
    }

    @Composable
    private fun RenderChart(chart: String, node: NativeUINode, modifier: Modifier) {
        when (chart) {
            "line" -> NativePHPChartsLineChartRenderer.Render(node, modifier)
            "area" -> NativePHPChartsAreaChartRenderer.Render(node, modifier)
            "bar" -> NativePHPChartsBarChartRenderer.Render(node, modifier)
            "scatter" -> NativePHPChartsScatterChartRenderer.Render(node, modifier)
            "pie" -> NativePHPChartsPieChartRenderer.Render(node, modifier)
            "donut" -> NativePHPChartsDonutChartRenderer.Render(node, modifier)
            "radar" -> NativePHPChartsRadarChartRenderer.Render(node, modifier)
            "candlestick" -> NativePHPChartsCandlestickChartRenderer.Render(node, modifier)
            "progress" -> NativePHPChartsProgressChartRenderer.Render(node, modifier)
            "contribution_heatmap" -> NativePHPChartsContributionHeatmapRenderer.Render(node, modifier)
        }
    }

    private fun galleryNode(chart: String): NativeUINode {
        val props = commonProps().apply {
            when (chart) {
                "line", "area", "bar" -> putAll(cartesianProps())
                "scatter" -> putAll(scatterProps())
                "candlestick" -> putAll(candlestickProps())
                "pie", "donut" -> putAll(radialProps())
                "radar" -> putAll(radarProps())
                "progress" -> putAll(progressProps())
                "contribution_heatmap" -> putAll(contributionProps())
            }
            when (chart) {
                "area" -> this["area_mode"] = "overlay"
                "bar" -> {
                    this["bar_mode"] = "grouped"
                    this["bar_orientation"] = "vertical"
                    this["style_json"] = """{"bar":{"radius":8,"width":28}}"""
                }
                "donut" -> this["inner_radius_ratio"] = 0.62f
            }
            this["a11y_label"] = "Native $chart evidence chart"
        }
        return NativeUINode(
            id = 20_000 + chart.hashCode().absoluteValue % 10_000,
            type = chart,
            layout = null,
            style = null,
            props = GenericProps(props),
            onPress = 0,
            onLongPress = 0,
            children = emptyList(),
        )
    }

    private fun commonProps(): MutableMap<String, Any> = mutableMapOf(
        "contract_version" to 1,
        "theme_mode" to "light",
        "theme_json" to "{}",
        "preset" to "default",
        "animated" to false,
        "empty_label" to "No data",
        "error_label" to "Chart unavailable",
        "locale" to "en-US",
        "value_format" to "number",
        "on_select" to 41,
    )

    private fun cartesianProps(): Map<String, Any> = mapOf(
        "series_json" to """[{"id":"revenue","name":"Revenue","color":"#ED3F16","points":[{"id":"jan","label":"Jan","value":18},{"id":"feb","label":"Feb","value":26},{"id":"mar","label":"Mar","value":22},{"id":"apr","label":"Apr","value":34},{"id":"may","label":"May","value":31},{"id":"jun","label":"Jun","value":42}]}]""",
        "series_transport" to "inline-v1",
        "style_json" to """{"line":{"width":4,"interpolation":"monotone"},"points":{"visible":true,"size":8},"area":{"opacity":0.28},"axis":{"label_count":5}}""",
        "x_axis_json" to "{}",
        "y_axis_json" to "{}",
        "legend_json" to """{"visible":false}""",
        "annotations_json" to "[]",
        "interaction_json" to """{"enabled":true,"mode":"tap"}""",
        "viewport_json" to "{}",
        "sampling_json" to "{}",
        "show_grid" to true,
        "show_points" to true,
        "begin_at_zero" to true,
    )

    private fun scatterProps(): Map<String, Any> = cartesianProps() + mapOf(
        "series_json" to """[{"id":"observed","name":"Observed","color":"#2563EB","points":[{"id":"a","label":"A","x":1,"value":8},{"id":"b","label":"B","x":2,"value":16},{"id":"c","label":"C","x":3,"value":12},{"id":"d","label":"D","x":4,"value":23},{"id":"e","label":"E","x":5,"value":28}]}]""",
        "x_axis_json" to """{"type":"number"}""",
        "style_json" to """{"points":{"visible":true,"size":12}}""",
    )

    private fun candlestickProps(): Map<String, Any> = cartesianProps() + mapOf(
        "series_json" to """[{"id":"market","name":"NIO/USD","points":[{"id":"d1","x":"2026-09-12","label":"12 Sep","value":36.8,"open":36.7,"high":37.0,"low":36.5,"close":36.8},{"id":"d2","x":"2026-09-13","label":"13 Sep","value":36.6,"open":36.8,"high":36.9,"low":36.4,"close":36.6},{"id":"d3","x":"2026-09-14","label":"14 Sep","value":37.1,"open":36.6,"high":37.2,"low":36.5,"close":37.1},{"id":"d4","x":"2026-09-15","label":"15 Sep","value":36.9,"open":37.1,"high":37.3,"low":36.8,"close":36.9},{"id":"d5","x":"2026-09-16","label":"16 Sep","value":37.4,"open":36.9,"high":37.6,"low":36.8,"close":37.4}]}]""",
        "x_axis_json" to """{"type":"date"}""",
        "style_json" to """{"candlestick":{"rising_color":"#15803D","falling_color":"#B91C1C","neutral_color":"#64748B","wick_width":2}}""",
        "begin_at_zero" to false,
    )

    private fun radialProps(): Map<String, Any> = mapOf(
        "segments_json" to """[{"id":"web","label":"Web","value":48,"color":"#ED3F16"},{"id":"store","label":"Store","value":27,"color":"#2563EB"},{"id":"partners","label":"Partners","value":15,"color":"#14B8A6"},{"id":"events","label":"Events","value":10,"color":"#F59E0B"}]""",
        "style_json" to """{"segment":{"gap":2,"corner_radius":4}}""",
        "legend_json" to """{"visible":true,"position":"bottom"}""",
    )

    private fun radarProps(): Map<String, Any> = mapOf(
        "axes_json" to """[{"id":"speed","label":"Speed","maximum":100},{"id":"quality","label":"Quality","maximum":100},{"id":"DX","label":"DX","maximum":100},{"id":"cost","label":"Cost","maximum":100},{"id":"reach","label":"Reach","maximum":100}]""",
        "series_json" to """[{"id":"nativephp","name":"NativePHP","color":"#ED3F16","values":[{"axis":"speed","value":88},{"axis":"quality","value":92},{"axis":"DX","value":84},{"axis":"cost","value":72},{"axis":"reach","value":78}]}]""",
        "style_json" to """{"line":{"width":3},"area":{"opacity":0.30},"points":{"visible":true,"size":8}}""",
        "legend_json" to """{"visible":false}""",
        "grid_levels" to 5,
        "fill_opacity" to 0.30f,
    )

    private fun progressProps(): Map<String, Any> = mapOf(
        "metrics_json" to """[{"id":"build","label":"Build","value":0.92,"color":"#ED3F16"},{"id":"tests","label":"Tests","value":0.78,"color":"#2563EB"},{"id":"docs","label":"Docs","value":0.86,"color":"#14B8A6"}]""",
        "center_label" to "Release readiness",
    )

    private fun contributionProps(): Map<String, Any> {
        val end = LocalDate.of(2026, 9, 16)
        val values = (0 until 180).joinToString(",") { offset ->
            val date = end.minusDays(offset.toLong())
            val value = ((offset * 7 + offset / 6) % 13)
            """{"id":"$date","date":"$date","value":$value,"label":"$value contributions"}"""
        }
        return mapOf(
            "values_json" to "[$values]",
            "end_date" to end.toString(),
            "days" to 180,
            "week_starts_on" to 1,
            "colors_json" to """["#FFD0BE","#FF8A5C","#ED3F16","#8F2108"]""",
            "empty_color" to "#ECEAE7",
            "show_month_labels" to true,
            "show_weekday_labels" to true,
        )
    }

    private companion object {
        const val EXTRA_CHART = "chart"
        val CHARTS = setOf(
            "line", "area", "bar", "scatter", "pie", "donut", "radar", "candlestick",
            "progress", "contribution_heatmap",
        )
    }
}
