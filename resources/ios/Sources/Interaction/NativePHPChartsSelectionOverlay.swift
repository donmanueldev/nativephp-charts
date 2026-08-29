import Charts
import SwiftUI

struct NativePHPChartsSelectionOverlay: View {
    let kind: NativePHPChartsKind
    let snapshot: NativePHPChartsSnapshot
    let selectedPoint: NativePHPChartsPoint?
    let proxy: ChartProxy
    let onSelect: (NativePHPChartsPoint?) -> Void

    var body: some View {
        GeometryReader { geometry in
            if let anchor = proxy.plotFrame {
                let plotFrame = geometry[anchor]

                ZStack(alignment: .topLeading) {
                    hitTarget(in: plotFrame)
                    selectionIndicator(in: plotFrame)
                }
            }
        }
    }

    private func hitTarget(in plotFrame: CGRect) -> some View {
        Rectangle()
            .fill(.clear)
            .contentShape(Rectangle())
            .gesture(
                SpatialTapGesture().onEnded { gesture in
                    guard snapshot.configuration.selection.enabled else { return }

                    onSelect(
                        NativePHPChartsSelection.closestPoint(
                            to: gesture.location,
                            proxy: proxy,
                            plotFrame: plotFrame,
                            data: snapshot.data,
                            x: selectionX(for:),
                            y: selectionY(for:)
                        )
                    )
                }
            )
    }

    @ViewBuilder
    private func selectionIndicator(in plotFrame: CGRect) -> some View {
        if let point = selectedPoint,
           let series = snapshot.data.series(id: point.seriesID),
           let position = NativePHPChartsSelection.position(
               x: selectionX(for: point),
               y: selectionY(for: point),
               proxy: proxy,
               plotFrame: plotFrame
           )
        {
            NativePHPChartsRuleMarkOverlay(position: position, plotFrame: plotFrame)
            NativePHPChartsTooltip(
                point: point,
                formatter: snapshot.formatter,
                color: selectionColor(for: series),
                maximumWidth: min(max(plotFrame.width - 16, 44), 220)
            )
            .position(
                x: tooltipX(for: position.x, in: plotFrame),
                y: min(max(plotFrame.minY + 20, position.y - 28), plotFrame.maxY - 20)
            )
        }
    }

    private func selectionX(for point: NativePHPChartsPoint) -> Double {
        snapshot.data.renderX(for: point, kind: kind)
    }

    private func selectionY(for point: NativePHPChartsPoint) -> Double {
        guard kind == .area else { return point.value }
        let bounds = snapshot.domain.areaBounds(for: point, mode: snapshot.configuration.areaMode)
        return snapshot.domain.areaOuterY(for: point, bounds: bounds)
    }

    private func selectionColor(for series: NativePHPChartsSeries) -> Color {
        if kind == .scatter, snapshot.data.series.count == 1 {
            return snapshot.configuration.style.color(
                snapshot.configuration.style.points.color,
                fallback: series.color
            )
        }

        guard kind != .bar, snapshot.data.series.count == 1 else { return series.color }
        return snapshot.configuration.style.color(
            snapshot.configuration.style.line.color,
            fallback: series.color
        )
    }

    private func tooltipX(for x: CGFloat, in plotFrame: CGRect) -> CGFloat {
        let halfWidth = min(110, max((plotFrame.width - 16) / 2, 0))
        return min(max(x, plotFrame.minX + halfWidth), plotFrame.maxX - halfWidth)
    }
}

private struct NativePHPChartsRuleMarkOverlay: View {
    let position: CGPoint
    let plotFrame: CGRect

    var body: some View {
        Rectangle()
            .fill(Color.secondary.opacity(0.35))
            .frame(width: 1, height: plotFrame.height)
            .position(x: position.x, y: plotFrame.midY)
            .allowsHitTesting(false)
            .accessibilityHidden(true)
    }
}
