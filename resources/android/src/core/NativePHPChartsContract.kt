package com.donmanueldev.plugins.nativephp_charts.ui

import android.util.Log
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.semantics.clearAndSetSemantics
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.CoroutineDispatcher
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import java.util.concurrent.ConcurrentHashMap

internal const val NATIVEPHP_CHARTS_CONTRACT_VERSION = 1

internal sealed interface NativePHPChartsDecodeResult<out Value> {
    data class Success<Value>(val value: Value) : NativePHPChartsDecodeResult<Value>
    data class Failure(val code: String, val cause: Throwable? = null) : NativePHPChartsDecodeResult<Nothing>
}

internal fun nativePHPChartsContractFailure(version: Int): NativePHPChartsDecodeResult.Failure? =
    if (version == NATIVEPHP_CHARTS_CONTRACT_VERSION) null
    else NativePHPChartsDecodeResult.Failure("unsupported_contract_version")

/** Logs only bounded error metadata; wire payloads and filesystem paths are never logged. */
internal object NativePHPChartsDiagnostics {
    private const val WINDOW_MILLIS = 60_000L
    private val lastLoggedAt = ConcurrentHashMap<String, Long>()

    fun report(chartType: String, failure: NativePHPChartsDecodeResult.Failure) {
        val key = "$chartType:${failure.code}"
        val now = System.currentTimeMillis()
        var shouldLog = false
        lastLoggedAt.compute(key) { _, previous ->
            if (previous == null || now - previous >= WINDOW_MILLIS) {
                shouldLog = true
                now
            } else {
                previous
            }
        }
        if (shouldLog) Log.w("NativePHPCharts", "chart_type=$chartType code=${failure.code}")
    }
}

internal sealed interface NativePHPChartsAsyncState<out Value> {
    data object Loading : NativePHPChartsAsyncState<Nothing>
    data class Ready<Value>(val value: Value) : NativePHPChartsAsyncState<Value>
    data class Unavailable(val failure: NativePHPChartsDecodeResult.Failure) : NativePHPChartsAsyncState<Nothing>
}

/**
 * Decodes immutable wire snapshots away from the main thread. A previous valid
 * value remains visible while a replacement is loading; failures replace it
 * with the explicit unavailable state so stale data is never presented as new.
 */
@Composable
internal fun <Key : Any, Value : Any> rememberNativePHPChartsDecodedState(
    key: Key,
    chartType: String,
    dispatcher: CoroutineDispatcher = Dispatchers.IO,
    decode: suspend (Key) -> NativePHPChartsDecodeResult<Value>,
): NativePHPChartsAsyncState<Value> {
    var state by remember { mutableStateOf<NativePHPChartsAsyncState<Value>>(NativePHPChartsAsyncState.Loading) }
    LaunchedEffect(key) {
        val result = withContext(dispatcher) {
            NativePHPChartsPerformance.traceSuspending("NPC.decode.$chartType") { decode(key) }
        }
        when (result) {
            is NativePHPChartsDecodeResult.Success -> state = NativePHPChartsAsyncState.Ready(result.value)
            is NativePHPChartsDecodeResult.Failure -> {
                NativePHPChartsDiagnostics.report(chartType, result)
                state = NativePHPChartsAsyncState.Unavailable(result)
            }
        }
    }
    return state
}

@Composable
internal fun NativePHPChartsUnavailable(
    modifier: Modifier,
    accessibilityLabel: String,
    errorLabel: String,
) {
    Box(
        modifier = modifier
            .fillMaxSize()
            .clearAndSetSemantics { contentDescription = "$accessibilityLabel: $errorLabel" },
    ) {
        Text(errorLabel, Modifier.padding(16.dp))
    }
}
