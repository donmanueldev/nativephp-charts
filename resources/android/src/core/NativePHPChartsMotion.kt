package com.donmanueldev.plugins.nativephp_charts.ui

import android.content.Context
import android.provider.Settings

internal fun nativePHPChartsAnimationsEnabled(context: Context): Boolean = runCatching {
    Settings.Global.getFloat(
        context.contentResolver,
        Settings.Global.ANIMATOR_DURATION_SCALE,
        1f,
    ) > 0f
}.getOrDefault(true)
