package com.donmanueldev.plugins.nativephp_charts.ui

import android.os.Trace
import androidx.compose.runtime.staticCompositionLocalOf

/**
 * Internal harness hook invoked only after a validated Cartesian snapshot has been drawn.
 *
 * Production renderers leave this unset. Keeping the observer in a CompositionLocal avoids adding
 * a public prop or renderer argument solely for profiling while allowing physical captures to
 * distinguish a loading placeholder from the first chart frame.
 */
internal val LocalNativePHPChartsDrawObserver =
    staticCompositionLocalOf<((NativePHPChartsKind, Int) -> Unit)?> { null }

/** Bounded sections consumed by Perfetto; no payload values or labels are recorded. */
internal object NativePHPChartsPerformance {
    fun <Value> trace(section: String, block: () -> Value): Value {
        Trace.beginSection(section.take(127))
        return try {
            block()
        } finally {
            Trace.endSection()
        }
    }

    suspend fun <Value> traceSuspending(section: String, block: suspend () -> Value): Value {
        Trace.beginSection(section.take(127))
        return try {
            block()
        } finally {
            Trace.endSection()
        }
    }
}
