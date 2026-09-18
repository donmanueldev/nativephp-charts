package com.donmanueldev.plugins.nativephp_charts.ui

import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Rect
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.unit.IntSize
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test
import java.time.LocalDate

class NativePHPChartsGeometryTest {
    @Test
    fun `selection identity survives reorder and value mutation`() {
        val series = series("sales")
        val original = datum(series, point("january", 10.0), Offset(10f, 10f))
        val reordered = datum(series, point("january", 99.0), Offset(80f, 20f))
        assertEquals(original.selectionIdentity, reordered.selectionIdentity)
    }

    @Test
    fun `same point id in different series does not collide`() {
        val first = datum(series("a"), point("same", 1.0), Offset.Zero)
        val second = datum(series("b"), point("same", 1.0), Offset.Zero)
        assertTrue(first.selectionIdentity != second.selectionIdentity)
    }

    @Test
    fun `hit index rejects geometry outside plot`() {
        val mark = datum(series("s"), point("p", 1.0), Offset(120f, 120f))
        val index = NativePHPChartsHitIndex.build(listOf(mark))
        assertNull(index.nearest(Rect(0f, 0f, 100f, 100f), Offset(99f, 99f), 40f))
    }

    @Test
    fun `hit index preserves nearest selection for unsorted input`() {
        val series = series("s")
        val right = datum(series, point("right", 1.0), Offset(80f, 50f))
        val left = datum(series, point("left", 1.0), Offset(20f, 50f))
        val index = NativePHPChartsHitIndex.build(listOf(right, left))

        assertEquals(left, index.nearest(Rect(0f, 0f, 100f, 100f), Offset(21f, 50f), 8f))
    }

    @Test
    fun `viewport culling keeps crossing segment endpoints`() {
        val series = series("s")
        val before = datum(series, point("before", 1.0), Offset(-20f, 50f))
        val after = datum(series, point("after", 2.0), Offset(120f, 50f))

        assertEquals(
            listOf(before, after),
            nativePHPChartsCullToPlot(listOf(before, after), Rect(0f, 0f, 100f, 100f)),
        )
    }

    @Test
    fun `linear pixel coalescing keeps endpoints and extrema in source order`() {
        val series = series("s")
        val data = listOf(5f, 7f, 1f, 8f, 9f, 3f).mapIndexed { index, y ->
            datum(series, point("p$index", y.toDouble()), Offset(10.1f + index * 0.1f, y))
        }

        assertEquals(
            listOf("p0", "p2", "p4", "p5"),
            nativePHPChartsCoalesceLinearToPixels(data).map { it.point.id },
        )
    }

    @Test
    fun `linear pixel coalescing never crosses physical columns`() {
        val series = series("s")
        val firstColumn = (0 until 6).map { index ->
            datum(series, point("a$index", index.toDouble()), Offset(10.1f + index * 0.1f, index.toFloat()))
        }
        val secondColumn = (0 until 6).map { index ->
            datum(series, point("b$index", index.toDouble()), Offset(11.1f + index * 0.1f, index.toFloat()))
        }

        assertEquals(
            listOf("a0", "a5", "b0", "b5"),
            nativePHPChartsCoalesceLinearToPixels(firstColumn + secondColumn).map { it.point.id },
        )
    }

    @Test
    fun `candlestick keeps neutral body selectable`() {
        val geometry = nativePHPChartsCandlestickGeometry(20f, 10f, 5f, 30f, 10f, 8f, 2f)
        assertTrue(geometry.body.height >= 3f)
        assertEquals(Offset(20f, 10f), geometry.anchor)
    }

    @Test
    fun `progress ring hit test resolves only visible ring`() {
        assertEquals(0, progressMetricIndexAt(2, 200f, 200f, 1f, Offset(100f, 12f)))
        assertNull(progressMetricIndexAt(2, 200f, 200f, 1f, Offset(100f, 100f)))
    }

    @Test
    fun `contribution layout maps every requested day once`() {
        val configuration = NativePHPChartsContributionConfiguration(
            values = emptyList(), endDate = LocalDate.parse("2026-01-10"), days = 14, weekStartsOn = 1,
            colors = listOf(Color.Green), emptyColor = Color.Gray, showMonthLabels = false,
            showWeekdayLabels = false, animated = false, emptyLabel = "", errorLabel = "",
            accessibilityLabel = "", onSelect = 0,
        )
        val layout = nativePHPChartsContributionLayout(configuration, IntSize(300, 140), 1f)
        assertEquals(14, layout.cells.size)
        assertEquals(14, layout.cells.map { it.date }.distinct().size)
    }

    private fun series(id: String) = NativePHPChartsSeries(id, id, Color.Blue, emptyList(), 0)
    private fun point(id: String, value: Double) = NativePHPChartsPoint(id, id, value, id, 0)
    private fun datum(series: NativePHPChartsSeries, point: NativePHPChartsPoint, center: Offset) =
        NativePHPChartsDatum(series, point, center)
}
