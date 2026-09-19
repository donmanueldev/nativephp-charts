package com.donmanueldev.plugins.nativephp_charts.ui

import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.semantics.clearAndSetSemantics
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.unit.dp

@Composable
internal fun NativePHPChartsEmpty(modifier: Modifier, accessibilityLabel: String, emptyLabel: String) {
    Box(
        modifier.fillMaxSize().clearAndSetSemantics {
            contentDescription = "$accessibilityLabel: $emptyLabel"
        },
    ) {
        Text(emptyLabel, Modifier.padding(16.dp))
    }
}
