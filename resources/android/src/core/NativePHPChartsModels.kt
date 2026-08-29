package com.donmanueldev.plugins.nativephp_charts.ui

import androidx.compose.ui.graphics.Color

internal enum class NativePHPChartsKind { Line, Area, Bar, Scatter }

internal enum class NativePHPChartsXType { Category, Number, Date, Datetime }

internal data class NativePHPChartsPoint(
    val id: String,
    val label: String,
    val value: Double,
    val x: Any?,
    val index: Int,
)

internal data class NativePHPChartsSeries(
    val id: String,
    val name: String,
    val color: Color,
    val points: List<NativePHPChartsPoint>,
    val index: Int,
)

internal data class NativePHPChartsXAxis(
    val type: NativePHPChartsXType = NativePHPChartsXType.Category,
    val visible: Boolean? = null,
    val labelCount: Int = 4,
    val dateFormat: String = "medium",
    val timezone: String = "",
)

internal data class NativePHPChartsYAxis(
    val visible: Boolean? = null,
    val labelCount: Int = 4,
    val valueFormat: String = "number",
    val currencyCode: String = "",
    val minimumFractionDigits: Int = -1,
    val maximumFractionDigits: Int = -1,
)

internal data class NativePHPChartsLegend(
    val visible: Boolean? = null,
    val position: String = "bottom",
    val alignment: String = "center",
    val markerSize: Float = 9f,
    val fontSize: Float = 11f,
    val font: String? = null,
    val labelColor: String? = null,
)

internal data class NativePHPChartsStyle(
    val lineColor: String? = null,
    val lineWidth: Float = 3f,
    val smooth: Boolean = false,
    val pointColor: String? = null,
    val pointSize: Float = 4f,
    val pointsVisible: Boolean? = null,
    val gridColor: String? = null,
    val gridVisible: Boolean? = null,
    val gridWidth: Float = 1f,
    val areaOpacity: Float = 0.28f,
    val areaGradient: Boolean = true,
    val barRadius: Float = 5f,
    val barWidth: Float? = null,
    val axisVisible: Boolean? = null,
    val axisColor: String? = null,
    val axisLabelCount: Int? = null,
    val axisFont: String? = null,
    val axisFontSize: Float = 10f,
    val axisLabelColor: String? = null,
)

internal data class NativePHPChartsConfiguration(
    val kind: NativePHPChartsKind,
    val series: List<NativePHPChartsSeries>,
    val style: NativePHPChartsStyle,
    val xAxis: NativePHPChartsXAxis,
    val yAxis: NativePHPChartsYAxis,
    val legend: NativePHPChartsLegend,
    val areaMode: String,
    val showGrid: Boolean,
    val showPoints: Boolean,
    val beginAtZero: Boolean,
    val animated: Boolean,
    val emptyLabel: String,
    val accessibilityLabel: String,
    val locale: String,
    val onSelect: Int,
) {
    val hasData: Boolean get() = series.any { it.points.isNotEmpty() }
    val legendVisible: Boolean get() = legend.visible ?: (series.size > 1)
    val animationKey: Int get() {
        var result = kind.hashCode()
        result = 31 * result + areaMode.hashCode()
        result = 31 * result + beginAtZero.hashCode()
        result = 31 * result + xAxis.type.hashCode()
        result = 31 * result + xAxis.timezone.hashCode()
        result = 31 * result + style.smooth.hashCode()
        result = 31 * result + (style.barWidth?.hashCode() ?: 0)

        series.forEach { item ->
            result = 31 * result + item.id.hashCode()
            item.points.forEach { point ->
                result = 31 * result + point.id.hashCode()
                result = 31 * result + point.value.hashCode()
                result = 31 * result + (point.x?.hashCode() ?: 0)
            }
        }

        return result
    }
}
