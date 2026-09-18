package com.donmanueldev.plugins.nativephp_charts.ui

import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Color
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

class NativePHPChartsInteractionTest {
    @Test
    fun `viewport reason preserves combined pan and zoom`() {
        assertEquals(NativePHPChartsViewportReason.Pan, NativePHPChartsViewportReason.from(true, false))
        assertEquals(NativePHPChartsViewportReason.Zoom, NativePHPChartsViewportReason.from(false, true))
        assertEquals(
            NativePHPChartsViewportReason.PanZoom,
            NativePHPChartsViewportReason.combine(NativePHPChartsViewportReason.Pan, NativePHPChartsViewportReason.Zoom),
        )
        assertNull(NativePHPChartsViewportReason.from(false, false))
    }

    @Test
    fun `radar navigation retains series and axis identity`() {
        val axis = NativePHPChartsRadarAxis("speed", "Speed", 10.0)
        val first = radarSelection("alpha", axis)
        val second = radarSelection("beta", axis)
        assertTrue(first.id != second.id)
        val navigation = nativePHPChartsRadarNavigation(listOf(first, second), first.id)
        assertEquals(second.id, navigation.next?.id)
        assertNull(navigation.previous)
    }

    @Test
    fun `radar hit test respects threshold`() {
        val axis = NativePHPChartsRadarAxis("speed", "Speed", 10.0)
        val selection = radarSelection("alpha", axis)
        assertEquals(
            selection,
            nativePHPChartsRadarNearestSelection(listOf(selection to Offset(20f, 20f)), Offset(22f, 20f), 3f),
        )
        assertNull(nativePHPChartsRadarNearestSelection(listOf(selection to Offset(20f, 20f)), Offset(30f, 20f), 3f))
    }

    @Test
    fun `special selection keeps accessible version one shape`() {
        val payload = nativePHPChartsSpecialSelectionPayload("progress", "id", "Label", 2, "Label", 0.5, "50%")
        assertEquals(1, payload["version"])
        assertEquals("progress", payload["chart_type"])
        assertEquals("id", payload["point_id"])
        assertEquals("50%", payload["localized_value"])
    }

    private fun radarSelection(seriesId: String, axis: NativePHPChartsRadarAxis): NativePHPChartsRadarSelection {
        val value = NativePHPChartsRadarValue(axis.id, 5.0)
        return NativePHPChartsRadarSelection(
            NativePHPChartsRadarSeries(seriesId, seriesId, Color.Blue, listOf(value)),
            axis,
            value,
            0,
        )
    }
}
