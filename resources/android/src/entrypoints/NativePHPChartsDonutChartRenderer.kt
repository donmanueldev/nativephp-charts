package com.donmanueldev.plugins.nativephp_charts.ui

import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import com.nativephp.mobile.ui.nativerender.NativeUINode

object NativePHPChartsDonutChartRenderer {
    @Composable
    fun Render(node: NativeUINode, modifier: Modifier) {
        NativePHPChartsRadialRender(node, modifier, NativePHPChartsRadialKind.Donut)
    }
}
