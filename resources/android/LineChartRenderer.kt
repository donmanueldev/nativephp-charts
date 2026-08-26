package com.donmanueldev.plugins.nativephp_charts.ui

import androidx.compose.foundation.layout.*
import androidx.compose.material3.Text
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import com.nativephp.mobile.ui.nativerender.NativeUINode
import com.nativephp.mobile.ui.nativerender.NativeUIBridge

object NativePHPChartsRenderer {
    @Composable
    fun Render(node: NativeUINode, modifier: Modifier) {
        val p = node.props

        // Read props from the wire format
        // val myValue = p.getString("value", "")
        // val onChangeCb = p.getCallbackId("on_change")

        // TODO: Replace with your custom Compose UI
        Box(modifier = modifier.padding(16.dp)) {
            Text(text = "NativePHPCharts Component")
        }

        // To send events back to PHP:
        // NativeUIBridge.sendTextChangeEvent(onChangeCb, newValue)
    }
}