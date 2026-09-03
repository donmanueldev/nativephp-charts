package com.donmanueldev.plugins.nativephp_charts.ui

import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.remember
import androidx.compose.ui.Modifier
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.clearAndSetSemantics
import androidx.compose.ui.unit.dp
import com.nativephp.mobile.ui.nativerender.NativeUINode

/**
 * Pie/donut renderer boundary from raw node props to decoded state, accessible
 * empty-state handling, legend placement, and the radial Canvas plot.
 */
@Composable
internal fun NativePHPChartsRadialRender(
    node: NativeUINode,
    modifier: Modifier,
    kind: NativePHPChartsRadialKind,
) {
    val wireInput = NativePHPChartsRadialWireInput.from(node)
    val configuration = remember(wireInput, kind) { NativePHPChartsRadialDecoder.decode(wireInput, kind) }
    val formatting = remember(configuration) { NativePHPChartsRadialFormatting(configuration) }
    if (!configuration.hasData) {
        Box(
            modifier = modifier.fillMaxSize().clearAndSetSemantics {
                contentDescription = "${configuration.accessibilityLabel}: ${configuration.emptyLabel}"
            },
        ) {
            Text(configuration.emptyLabel, Modifier.padding(16.dp))
        }
        return
    }

    val position = configuration.legend.position
    if (configuration.legendVisible && position in setOf("leading", "trailing")) {
        Row(modifier.fillMaxSize()) {
            if (position == "leading") NativePHPChartsRadialLegendView(configuration, false)
            NativePHPChartsRadialPlot(node, configuration, formatting, Modifier.weight(1f).fillMaxSize())
            if (position == "trailing") NativePHPChartsRadialLegendView(configuration, false)
        }
    } else {
        Column(modifier.fillMaxSize()) {
            if (configuration.legendVisible && position == "top") NativePHPChartsRadialLegendView(configuration, true)
            NativePHPChartsRadialPlot(node, configuration, formatting, Modifier.weight(1f).fillMaxWidth())
            if (configuration.legendVisible && position != "top") NativePHPChartsRadialLegendView(configuration, true)
        }
    }
}
