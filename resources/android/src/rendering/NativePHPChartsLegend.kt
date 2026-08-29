package com.donmanueldev.plugins.nativephp_charts.ui

import androidx.compose.foundation.background
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.nativephp.plugins.native_ui.ui.NativeUIFontResolver

@Composable
internal fun NativePHPChartsLegendView(configuration: NativePHPChartsConfiguration, horizontal: Boolean) {
    if (horizontal) {
        val contentAlignment = when (configuration.legend.alignment) {
            "start" -> Alignment.CenterStart
            "end" -> Alignment.CenterEnd
            else -> Alignment.Center
        }
        Box(
            modifier = Modifier.fillMaxWidth().heightIn(max = 72.dp),
            contentAlignment = contentAlignment,
        ) {
            Row(modifier = Modifier.horizontalScroll(rememberScrollState()).padding(horizontal = 8.dp, vertical = 6.dp)) {
                configuration.series.forEach { series -> NativePHPChartsLegendItem(series, configuration, configuration.legend) }
            }
        }
    } else {
        val horizontalAlignment = when (configuration.legend.alignment) {
            "start" -> Alignment.Start
            "end" -> Alignment.End
            else -> Alignment.CenterHorizontally
        }
        Column(
            modifier = Modifier
                .widthIn(max = 160.dp)
                .verticalScroll(rememberScrollState())
                .padding(horizontal = 8.dp, vertical = 12.dp),
            horizontalAlignment = horizontalAlignment,
        ) {
            configuration.series.forEach { series -> NativePHPChartsLegendItem(series, configuration, configuration.legend) }
        }
    }
}

@Composable
private fun NativePHPChartsLegendItem(
    series: NativePHPChartsSeries,
    configuration: NativePHPChartsConfiguration,
    legend: NativePHPChartsLegend,
) {
    val context = LocalContext.current
    val fontFamily = remember(context, legend.font) {
        legend.font?.let { NativeUIFontResolver.resolve(context, it) }
    }
    Row(modifier = Modifier.widthIn(max = 160.dp).padding(horizontal = 6.dp, vertical = 3.dp)) {
        val markerColor = when (configuration.kind) {
            NativePHPChartsKind.Scatter -> chartColor(
                configuration.style.pointColor.takeIf { configuration.series.size == 1 },
                series.color,
            )
            NativePHPChartsKind.Line, NativePHPChartsKind.Area -> chartColor(
                configuration.style.lineColor.takeIf { configuration.series.size == 1 },
                series.color,
            )
            NativePHPChartsKind.Bar -> series.color
        }
        Box(modifier = Modifier.padding(top = 4.dp, end = 6.dp).size(legend.markerSize.dp).background(markerColor, CircleShape))
        Text(
            series.name,
            modifier = Modifier.widthIn(max = 132.dp),
            color = chartColor(legend.labelColor, MaterialTheme.colorScheme.onSurfaceVariant),
            fontSize = legend.fontSize.sp,
            fontFamily = fontFamily,
            maxLines = 1,
            overflow = TextOverflow.Ellipsis,
        )
    }
}
