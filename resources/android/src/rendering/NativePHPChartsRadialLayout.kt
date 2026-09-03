package com.donmanueldev.plugins.nativephp_charts.ui

import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Rect
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.unit.IntSize
import kotlin.math.PI
import kotlin.math.atan2
import kotlin.math.cos
import kotlin.math.min
import kotlin.math.sin
import kotlin.math.sqrt

/** One visible segment's final Canvas-space angles and reusable path. */
internal data class NativePHPChartsRadialDatum(
    val segment: NativePHPChartsRadialSegment,
    val startAngle: Float,
    val sweepAngle: Float,
    val path: Path,
)

/**
 * Pie/donut geometry in Canvas pixels. Angles use Compose convention: zero points
 * right and positive sweeps move clockwise because screen y increases downward.
 */
internal data class NativePHPChartsRadialLayout(
    val center: Offset,
    val outerRadius: Float,
    val innerRadius: Float,
    val data: List<NativePHPChartsRadialDatum>,
) {
    /**
     * Hit-tests the exact annulus and visible post-gap sweeps. Donut taps inside
     * the hole and all taps outside the outer radius intentionally select nothing.
     */
    fun segmentAt(location: Offset): NativePHPChartsRadialDatum? {
        val dx = location.x - center.x
        val dy = location.y - center.y
        val radius = sqrt((dx * dx) + (dy * dy))
        if (radius < innerRadius || radius > outerRadius) return null
        val angle = normalizeNativePHPChartsAngle(Math.toDegrees(atan2(dy, dx).toDouble()).toFloat())
        return data.firstOrNull { datum ->
            val delta = normalizeNativePHPChartsAngle(angle - datum.startAngle)
            delta <= datum.sweepAngle
        }
    }
}

/** Resolves normalized segment values and dp styling into radial Canvas geometry. */
internal object NativePHPChartsRadialLayoutEngine {
    /**
     * Starts the first segment at 12 o'clock, preserves wire order clockwise, and
     * removes half the configured angular gap from each edge without consuming
     * the segment's value-proportional position in the full circle.
     */
    fun build(
        configuration: NativePHPChartsRadialConfiguration,
        size: IntSize,
        density: Float,
    ): NativePHPChartsRadialLayout {
        val center = Offset(size.width / 2f, size.height / 2f)
        val outerRadius = (min(size.width, size.height) / 2f - (12f * density)).coerceAtLeast(0f)
        val innerRadius = outerRadius * configuration.innerRadiusRatio
        var revealStart = 0f
        val data = configuration.visibleSegments.map { segment ->
            val rawSweep = (segment.value / configuration.total * 360.0).toFloat()
            val gap = configuration.style.gap.coerceAtMost(rawSweep * 0.45f)
            val start = -90f + revealStart + (gap / 2f)
            val sweep = (rawSweep - gap).coerceAtLeast(0f)
            NativePHPChartsRadialDatum(
                segment = segment,
                startAngle = start,
                sweepAngle = sweep,
                path = nativePHPChartsRadialPath(
                    center = center,
                    outerRadius = outerRadius,
                    innerRadius = innerRadius,
                    startAngle = start,
                    sweepAngle = sweep,
                    requestedCornerRadius = configuration.style.cornerRadius * density,
                ),
            ).also { revealStart += rawSweep }
        }
        return NativePHPChartsRadialLayout(center, outerRadius, innerRadius, data)
    }
}

/**
 * Builds the pie or donut mark from the angles and radii used by hit testing.
 * Visual corner rounding does not shrink the selectable sector. Requested corner
 * radius is bounded by ring thickness and available arc length; degenerate radii
 * or sweeps return an empty path.
 */
internal fun nativePHPChartsRadialPath(
    center: Offset,
    outerRadius: Float,
    innerRadius: Float,
    startAngle: Float,
    sweepAngle: Float,
    requestedCornerRadius: Float,
): Path {
    if (outerRadius <= 0f || sweepAngle <= 0f) return Path()
    val thickness = outerRadius - innerRadius
    val cornerRadius = requestedCornerRadius
        .coerceAtMost(thickness / 2f)
        .coerceAtMost(outerRadius * degreesToRadiansNativePHPCharts(sweepAngle) / 4f)
        .coerceAtLeast(0f)
    return if (innerRadius <= 0f) {
        nativePHPChartsPiePath(center, outerRadius, startAngle, sweepAngle, cornerRadius)
    } else {
        nativePHPChartsDonutPath(center, outerRadius, innerRadius, startAngle, sweepAngle, cornerRadius)
    }
}

private fun nativePHPChartsPiePath(
    center: Offset,
    radius: Float,
    startAngle: Float,
    sweepAngle: Float,
    cornerRadius: Float,
): Path = Path().apply {
    if (cornerRadius <= 0f) {
        moveTo(center.x, center.y)
        val start = polarNativePHPCharts(center, radius, startAngle)
        lineTo(start.x, start.y)
        arcTo(radialBoundsNativePHPCharts(center, radius), startAngle, sweepAngle, false)
        close()
        return@apply
    }
    val cornerAngle = radiansToDegreesNativePHPCharts(cornerRadius / radius).coerceAtMost(sweepAngle / 2f)
    val outerStart = polarNativePHPCharts(center, radius - cornerRadius, startAngle)
    val startControl = polarNativePHPCharts(center, radius, startAngle)
    val roundedStart = polarNativePHPCharts(center, radius, startAngle + cornerAngle)
    val roundedSweep = (sweepAngle - (2f * cornerAngle)).coerceAtLeast(0f)
    val outerEnd = polarNativePHPCharts(center, radius - cornerRadius, startAngle + sweepAngle)
    val endControl = polarNativePHPCharts(center, radius, startAngle + sweepAngle)
    moveTo(center.x, center.y)
    lineTo(outerStart.x, outerStart.y)
    quadraticTo(startControl.x, startControl.y, roundedStart.x, roundedStart.y)
    arcTo(radialBoundsNativePHPCharts(center, radius), startAngle + cornerAngle, roundedSweep, false)
    quadraticTo(endControl.x, endControl.y, outerEnd.x, outerEnd.y)
    close()
}

private fun nativePHPChartsDonutPath(
    center: Offset,
    outerRadius: Float,
    innerRadius: Float,
    startAngle: Float,
    sweepAngle: Float,
    cornerRadius: Float,
): Path = Path().apply {
    if (cornerRadius <= 0f) {
        val outerStart = polarNativePHPCharts(center, outerRadius, startAngle)
        moveTo(outerStart.x, outerStart.y)
        arcTo(radialBoundsNativePHPCharts(center, outerRadius), startAngle, sweepAngle, false)
        val innerEnd = polarNativePHPCharts(center, innerRadius, startAngle + sweepAngle)
        lineTo(innerEnd.x, innerEnd.y)
        arcTo(radialBoundsNativePHPCharts(center, innerRadius), startAngle + sweepAngle, -sweepAngle, false)
        close()
        return@apply
    }
    val outerAngle = radiansToDegreesNativePHPCharts(cornerRadius / outerRadius).coerceAtMost(sweepAngle / 2f)
    val innerAngle = radiansToDegreesNativePHPCharts(cornerRadius / innerRadius).coerceAtMost(sweepAngle / 2f)
    val outerStart = polarNativePHPCharts(center, outerRadius, startAngle + outerAngle)
    val outerEndInset = polarNativePHPCharts(center, outerRadius - cornerRadius, startAngle + sweepAngle)
    val innerEndInset = polarNativePHPCharts(center, innerRadius + cornerRadius, startAngle + sweepAngle)
    val innerEnd = polarNativePHPCharts(center, innerRadius, startAngle + sweepAngle - innerAngle)
    val innerStart = polarNativePHPCharts(center, innerRadius, startAngle + innerAngle)
    val innerStartInset = polarNativePHPCharts(center, innerRadius + cornerRadius, startAngle)
    val outerStartInset = polarNativePHPCharts(center, outerRadius - cornerRadius, startAngle)
    moveTo(outerStart.x, outerStart.y)
    arcTo(
        radialBoundsNativePHPCharts(center, outerRadius),
        startAngle + outerAngle,
        (sweepAngle - (2f * outerAngle)).coerceAtLeast(0f),
        false,
    )
    val outerEndControl = polarNativePHPCharts(center, outerRadius, startAngle + sweepAngle)
    quadraticTo(outerEndControl.x, outerEndControl.y, outerEndInset.x, outerEndInset.y)
    lineTo(innerEndInset.x, innerEndInset.y)
    val innerEndControl = polarNativePHPCharts(center, innerRadius, startAngle + sweepAngle)
    quadraticTo(innerEndControl.x, innerEndControl.y, innerEnd.x, innerEnd.y)
    arcTo(
        radialBoundsNativePHPCharts(center, innerRadius),
        startAngle + sweepAngle - innerAngle,
        -(sweepAngle - (2f * innerAngle)).coerceAtLeast(0f),
        false,
    )
    val innerStartControl = polarNativePHPCharts(center, innerRadius, startAngle)
    quadraticTo(innerStartControl.x, innerStartControl.y, innerStartInset.x, innerStartInset.y)
    lineTo(outerStartInset.x, outerStartInset.y)
    val outerStartControl = polarNativePHPCharts(center, outerRadius, startAngle)
    quadraticTo(outerStartControl.x, outerStartControl.y, outerStart.x, outerStart.y)
    close()
}

private fun radialBoundsNativePHPCharts(center: Offset, radius: Float): Rect = Rect(
    center.x - radius,
    center.y - radius,
    center.x + radius,
    center.y + radius,
)

private fun polarNativePHPCharts(center: Offset, radius: Float, angleDegrees: Float): Offset {
    val radians = degreesToRadiansNativePHPCharts(angleDegrees)
    return Offset(center.x + radius * cos(radians), center.y + radius * sin(radians))
}

private fun normalizeNativePHPChartsAngle(angle: Float): Float = ((angle % 360f) + 360f) % 360f
private fun degreesToRadiansNativePHPCharts(degrees: Float): Float = degrees * PI.toFloat() / 180f
private fun radiansToDegreesNativePHPCharts(radians: Float): Float = radians * 180f / PI.toFloat()
