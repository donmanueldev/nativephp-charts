package com.nativephp.plugins.native_ui.ui

import android.content.Context
import androidx.compose.ui.text.font.FontFamily

object NativeUIFontResolver {
    var aliases: Map<String, String> = emptyMap()
    fun resolve(context: Context, token: String): FontFamily? = null
}
