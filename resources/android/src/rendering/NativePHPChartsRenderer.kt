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

@Composable
internal fun NativePHPChartsRender(node: NativeUINode, modifier: Modifier, kind: NativePHPChartsKind) {
    val wireInput = NativePHPChartsWireInput.from(node)
    val configuration = remember(wireInput, kind) { NativePHPChartsDecoder.decode(wireInput, kind) }
    val formatting = remember(configuration) { NativePHPChartsFormatting(configuration) }

    if (!configuration.hasData) {
        Box(
            modifier = modifier
                .clearAndSetSemantics {
                    contentDescription = "${configuration.accessibilityLabel}: ${configuration.emptyLabel}"
                }
                .fillMaxSize(),
        ) {
            Text(configuration.emptyLabel, modifier = Modifier.padding(16.dp))
        }
        return
    }

    NativePHPChartsContent(node, configuration, formatting, modifier)
}

@Composable
private fun NativePHPChartsContent(
    node: NativeUINode,
    configuration: NativePHPChartsConfiguration,
    formatting: NativePHPChartsFormatting,
    modifier: Modifier,
) {
    val position = configuration.legend.position
    if (configuration.legendVisible && position in setOf("leading", "trailing")) {
        Row(modifier = modifier.fillMaxSize()) {
            if (position == "leading") NativePHPChartsLegendView(configuration, false)
            NativePHPChartsPlot(node, configuration, formatting, Modifier.weight(1f).fillMaxSize())
            if (position == "trailing") NativePHPChartsLegendView(configuration, false)
        }
    } else {
        Column(modifier = modifier.fillMaxSize()) {
            if (configuration.legendVisible && position == "top") NativePHPChartsLegendView(configuration, true)
            NativePHPChartsPlot(node, configuration, formatting, Modifier.weight(1f).fillMaxWidth())
            if (configuration.legendVisible && position != "top") NativePHPChartsLegendView(configuration, true)
        }
    }
}
