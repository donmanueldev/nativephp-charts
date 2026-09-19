package com.donmanueldev.plugins.nativephp_charts.ui

import androidx.compose.ui.graphics.Color
import org.json.JSONObject

internal data class NativePHPChartsTheme(
    val background: String? = null,
    val foreground: String? = null,
    val muted: String? = null,
    val grid: String? = null,
    val track: String? = null,
    val error: String? = null,
    val palette: List<String> = emptyList(),
) {
    fun color(index: Int, fallback: Color): Color =
        palette.takeIf { it.isNotEmpty() }?.let { colors ->
            chartColor(colors[index.mod(colors.size)], fallback)
        } ?: fallback
}

internal fun nativePHPChartsTheme(json: String, mode: String, systemDark: Boolean): NativePHPChartsTheme {
    val root = runCatching { JSONObject(json) }.getOrNull() ?: return NativePHPChartsTheme()
    val resolvedMode = when (mode) {
        "dark" -> "dark"
        "light" -> "light"
        else -> if (systemDark) "dark" else "light"
    }
    val value = root.optJSONObject(resolvedMode) ?: return NativePHPChartsTheme()
    val palette = value.optJSONArray("palette")?.let { values ->
        buildList {
            for (index in 0 until values.length()) {
                values.optString(index).takeIf(String::isNotBlank)?.let(::add)
            }
        }
    }.orEmpty()
    return NativePHPChartsTheme(
        background = value.optionalThemeColor("background"),
        foreground = value.optionalThemeColor("foreground"),
        muted = value.optionalThemeColor("muted"),
        grid = value.optionalThemeColor("grid"),
        track = value.optionalThemeColor("track"),
        error = value.optionalThemeColor("error"),
        palette = palette,
    )
}

private fun JSONObject.optionalThemeColor(name: String): String? =
    optString(name).takeIf(String::isNotBlank)
