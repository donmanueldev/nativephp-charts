package com.donmanueldev.plugins.nativephp_charts.ui

import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import com.nativephp.mobile.ui.nativerender.NativeUINode

object NativePHPChartsScatterChartRenderer {
    @Composable
    fun Render(node: NativeUINode, modifier: Modifier) {
        NativePHPChartsRender(node, modifier, NativePHPChartsKind.Scatter)
    }
}
