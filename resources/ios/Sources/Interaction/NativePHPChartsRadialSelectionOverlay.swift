import Charts
import SwiftUI

struct NativePHPChartsRadialSelectionOverlay: View {
    let snapshot: NativePHPChartsRadialSnapshot
    let selectedSegmentID: String?
    let revealProgress: CGFloat
    let proxy: ChartProxy
    let onSelect: (NativePHPChartsRadialSegment?) -> Void

    var body: some View {
        GeometryReader { geometry in
            if let anchor = proxy.plotFrame {
                let plotFrame = geometry[anchor]

                Rectangle()
                    .fill(.clear)
                    .contentShape(Rectangle())
                    .frame(width: plotFrame.width, height: plotFrame.height)
                    .position(x: plotFrame.midX, y: plotFrame.midY)
                    .gesture(
                        SpatialTapGesture().onEnded { gesture in
                            onSelect(segment(at: gesture.location, in: plotFrame.size))
                        }
                    )
            }
        }
    }

    private func segment(at location: CGPoint, in size: CGSize) -> NativePHPChartsRadialSegment? {
        guard snapshot.data.total > 0, size.width > 0, size.height > 0 else { return nil }

        let center = CGPoint(x: size.width / 2, y: size.height / 2)
        let deltaX = location.x - center.x
        let deltaY = location.y - center.y
        let radius = hypot(deltaX, deltaY)
        let baseRadius = min(size.width, size.height) / 2
        let innerRadius = baseRadius * snapshot.configuration.innerRadiusRatio

        var degrees = atan2(deltaY, deltaX) * 180 / .pi + 90
        if degrees < 0 { degrees += 360 }
        guard degrees <= 360 * revealProgress else { return nil }

        let angleValue = Double(degrees / 360) * snapshot.data.total
        guard let segment = snapshot.data.segment(containing: angleValue) else { return nil }

        let outerRatio: CGFloat = selectedSegmentID == segment.id ? 1 : 0.94
        guard radius >= innerRadius, radius <= baseRadius * outerRatio else { return nil }

        let rawStart = CGFloat(segment.lowerBound / snapshot.data.total) * 360
        let rawEnd = CGFloat(segment.upperBound / snapshot.data.total) * 360
        let effectiveGap = min(snapshot.configuration.style.gap, (rawEnd - rawStart) * 0.45)
        let halfGap = effectiveGap / 2

        guard degrees >= rawStart + halfGap, degrees <= rawEnd - halfGap else { return nil }
        return segment
    }
}
