package com.nativephp.mobile.ui.nativerender

import java.util.concurrent.CopyOnWriteArrayList

object NativeUIBridge {
    data class TextChangeEvent(
        val callbackId: Int,
        val nodeId: Int,
        val text: String,
    )

    private val textChangeEvents = CopyOnWriteArrayList<TextChangeEvent>()

    fun sendTextChangeEvent(callbackId: Int, nodeId: Int, text: String) {
        textChangeEvents += TextChangeEvent(callbackId, nodeId, text)
    }

    fun recordedTextChangeEvents(): List<TextChangeEvent> = textChangeEvents.toList()

    fun resetRecordedEvents() {
        textChangeEvents.clear()
    }
}
