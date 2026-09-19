package com.donmanueldev.plugins.nativephp_charts.ui

import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Text
import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.runtime.Composable
import androidx.compose.runtime.remember
import androidx.compose.ui.Modifier
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.clearAndSetSemantics
import androidx.compose.ui.unit.dp
import com.nativephp.mobile.ui.nativerender.NativeUINode

/**
 * Cartesian renderer boundary from NativePHP node props to Compose UI.
 *
 * The wire snapshot and decoded configuration are memoized by value. Empty or
 * malformed data takes the same accessible empty-state path; valid state is then
 * split between legend composition and the Canvas plot.
 */
@Composable
internal fun NativePHPChartsRender(node: NativeUINode, modifier: Modifier, kind: NativePHPChartsKind) {
    val wireInput = NativePHPChartsWireInput.from(node)
    val systemDark = isSystemInDarkTheme()
    val decodeKey = wireInput to systemDark
    val decoded = rememberNativePHPChartsDecodedState(decodeKey, kind.name.lowercase()) {
        NativePHPChartsDecoder.decode(it.first, kind, it.second)
    }
    if (decoded !is NativePHPChartsAsyncState.Ready) {
        if (decoded is NativePHPChartsAsyncState.Unavailable) {
            NativePHPChartsUnavailable(modifier, wireInput.accessibilityLabel, wireInput.errorLabel)
        }
        return
    }
    val configuration = decoded.value
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
        Row(modifier = modifier.fillMaxSize().background(configuration.backgroundColor)) {
            if (position == "leading") NativePHPChartsLegendView(configuration, false)
            NativePHPChartsPlot(node, configuration, formatting, Modifier.weight(1f).fillMaxSize())
            if (position == "trailing") NativePHPChartsLegendView(configuration, false)
        }
    } else {
        Column(modifier = modifier.fillMaxSize().background(configuration.backgroundColor)) {
            if (configuration.legendVisible && position == "top") NativePHPChartsLegendView(configuration, true)
            NativePHPChartsPlot(node, configuration, formatting, Modifier.weight(1f).fillMaxWidth())
            if (configuration.legendVisible && position != "top") NativePHPChartsLegendView(configuration, true)
        }
    }
}
