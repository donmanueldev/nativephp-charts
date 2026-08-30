import Charts
import SwiftUI

struct NativePHPChartsSelectionOverlay: View {
    let kind: NativePHPChartsKind
    let snapshot: NativePHPChartsSnapshot
    let selectedPoint: NativePHPChartsPoint?
    let proxy: ChartProxy
    let onPreview: (NativePHPChartsPoint?) -> Void
    let onCommit: (NativePHPChartsPoint?) -> Void
    let viewport: ClosedRange<Double>?
    let fullViewport: ClosedRange<Double>
    let onViewportPreview: (ClosedRange<Double>) -> Void
    let onViewportCommit: (ClosedRange<Double>, NativePHPChartsViewportReason) -> Void

    @State private var viewportGesture: NativePHPChartsViewportInteraction.State?

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
        selectionTarget(
            viewportTarget(
                Rectangle()
                    .fill(.clear)
                    .contentShape(Rectangle()),
                in: plotFrame
            ),
            in: plotFrame
        )
    }

    @ViewBuilder
    private func selectionTarget<Content: View>(
        _ content: Content,
        in plotFrame: CGRect
    ) -> some View {
        if snapshot.configuration.selection.enabled,
           snapshot.configuration.selection.mode == "scrub"
        {
            content.simultaneousGesture(scrubGesture(in: plotFrame))
        } else if snapshot.configuration.selection.enabled {
            content.simultaneousGesture(tapGesture(in: plotFrame))
        } else {
            content
        }
    }

    @ViewBuilder
    private func viewportTarget<Content: View>(
        _ content: Content,
        in plotFrame: CGRect
    ) -> some View {
        let viewport = snapshot.configuration.viewport
        let canPan = viewport.enabled && viewport.pan && snapshot.configuration.selection.mode != "scrub"
        let canZoom = viewport.enabled && viewport.zoom

        if canPan, canZoom {
            content
                .simultaneousGesture(panGesture(in: plotFrame))
                .simultaneousGesture(zoomGesture(in: plotFrame))
        } else if canPan {
            content.gesture(panGesture(in: plotFrame))
        } else if canZoom {
            content.gesture(zoomGesture(in: plotFrame))
        } else {
            content
        }
    }

    private func tapGesture(in plotFrame: CGRect) -> some Gesture {
        SpatialTapGesture().onEnded { gesture in
            guard snapshot.configuration.selection.enabled,
                  snapshot.configuration.selection.mode == "tap"
            else { return }

            onCommit(closestPoint(to: gesture.location, in: plotFrame))
        }
    }

    private func scrubGesture(in plotFrame: CGRect) -> some Gesture {
        DragGesture(minimumDistance: 0, coordinateSpace: .local)
            .onChanged { gesture in
                guard snapshot.configuration.selection.enabled,
                      snapshot.configuration.selection.mode == "scrub"
                else { return }

                onPreview(closestPoint(to: gesture.location, in: plotFrame))
            }
            .onEnded { gesture in
                guard snapshot.configuration.selection.enabled,
                      snapshot.configuration.selection.mode == "scrub"
                else { return }

                onCommit(closestPoint(to: gesture.location, in: plotFrame))
            }
    }

    private func closestPoint(to location: CGPoint, in plotFrame: CGRect) -> NativePHPChartsPoint? {
        NativePHPChartsSelection.closestPoint(
            to: location,
            proxy: proxy,
            plotFrame: plotFrame,
            data: snapshot.data,
            x: selectionX(for:),
            y: selectionY(for:)
        )
    }

    private func panGesture(in plotFrame: CGRect) -> some Gesture {
        DragGesture(minimumDistance: 8, coordinateSpace: .local)
            .onChanged { gesture in
                guard plotFrame.contains(gesture.startLocation) else { return }

                guard var state = viewportGesture ?? initialViewportGesture() else { return }
                let translation = isHorizontalBar ? gesture.translation.height : gesture.translation.width
                state.updatePan(translation: Double(translation))
                updateViewportGesture(&state, in: plotFrame)
            }
            .onEnded { _ in
                finishViewportGesture()
            }
    }

    private func zoomGesture(in plotFrame: CGRect) -> some Gesture {
        MagnifyGesture()
            .onChanged { gesture in
                guard plotFrame.contains(gesture.startLocation) else { return }

                guard var state = viewportGesture ?? initialViewportGesture() else { return }
                state.updateZoom(
                    magnification: Double(gesture.magnification),
                    focalFraction: focalFraction(for: gesture.startLocation, in: plotFrame)
                )
                updateViewportGesture(&state, in: plotFrame)
            }
            .onEnded { _ in
                finishViewportGesture()
            }
    }

    private func initialViewportGesture() -> NativePHPChartsViewportInteraction.State? {
        viewport.map(NativePHPChartsViewportInteraction.State.init(domain:))
    }

    private func updateViewportGesture(
        _ state: inout NativePHPChartsViewportInteraction.State,
        in plotFrame: CGRect
    ) {
        let axisLength = isHorizontalBar ? plotFrame.height : plotFrame.width
        guard let resolved = NativePHPChartsViewportInteraction.resolve(
            state: state,
            fullDomain: fullViewport,
            axisLength: Double(axisLength),
            configuredMinimumSpan: snapshot.configuration.viewport.minimumSpan
        ) else { return }

        state.latestDomain = resolved
        viewportGesture = state
        onViewportPreview(resolved)
    }

    private func finishViewportGesture() {
        guard let state = viewportGesture else { return }
        viewportGesture = nil
        guard let reason = state.reason,
              state.latestDomain != state.initialDomain
        else { return }

        onViewportCommit(state.latestDomain, reason)
    }

    private func focalFraction(for location: CGPoint, in plotFrame: CGRect) -> Double {
        let coordinate = isHorizontalBar
            ? (location.y - plotFrame.minY) / plotFrame.height
            : (location.x - plotFrame.minX) / plotFrame.width
        return min(max(Double(coordinate), 0), 1)
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
           ), isInsidePlot(position, plotFrame: plotFrame)
        {
            NativePHPChartsCrosshairOverlay(
                position: position,
                plotFrame: plotFrame,
                mode: snapshot.configuration.selection.crosshair,
                horizontalBar: isHorizontalBar
            )
            if snapshot.configuration.selection.tooltip == "shared" {
                NativePHPChartsSharedTooltip(
                    points: snapshot.data.points(atPlotX: point.plotX),
                    snapshot: snapshot,
                    maximumWidth: min(max(plotFrame.width - 16, 44), 240)
                )
                .position(x: tooltipX(for: position.x, in: plotFrame), y: tooltipY(for: position.y, in: plotFrame))
            } else if snapshot.configuration.selection.tooltip == "single" {
                NativePHPChartsTooltip(
                    point: point,
                    formatter: snapshot.formatter,
                    color: selectionColor(for: series),
                    maximumWidth: min(max(plotFrame.width - 16, 44), 220)
                )
                .position(x: tooltipX(for: position.x, in: plotFrame), y: tooltipY(for: position.y, in: plotFrame))
            }
        }
    }

    private func isInsidePlot(_ position: CGPoint, plotFrame: CGRect) -> Bool {
        position.x >= plotFrame.minX && position.x <= plotFrame.maxX
            && position.y >= plotFrame.minY && position.y <= plotFrame.maxY
    }

    private func selectionX(for point: NativePHPChartsPoint) -> Double {
        if isHorizontalBar { return point.value }

        return snapshot.data.renderX(
            for: point,
            kind: kind,
            barMode: snapshot.configuration.barMode
        )
    }

    private func selectionY(for point: NativePHPChartsPoint) -> Double {
        if isHorizontalBar {
            return snapshot.data.renderX(
                for: point,
                kind: kind,
                barMode: snapshot.configuration.barMode
            )
        }

        guard kind == .area else { return point.value }
        let bounds = snapshot.domain.areaBounds(for: point, mode: snapshot.configuration.areaMode)
        return snapshot.domain.areaOuterY(for: point, bounds: bounds)
    }

    private var isHorizontalBar: Bool {
        kind == .bar && snapshot.configuration.barOrientation == .horizontal
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

    private func tooltipY(for y: CGFloat, in plotFrame: CGRect) -> CGFloat {
        min(max(plotFrame.minY + 30, y - 38), plotFrame.maxY - 30)
    }
}

private struct NativePHPChartsCrosshairOverlay: View {
    let position: CGPoint
    let plotFrame: CGRect
    let mode: String
    let horizontalBar: Bool

    var body: some View {
        ZStack {
            if showsLogicalX {
                guide(logicalX: true)
            }
            if showsLogicalY {
                guide(logicalX: false)
            }
        }
        .allowsHitTesting(false)
        .accessibilityHidden(true)
    }

    private var showsLogicalX: Bool { mode == "x" || mode == "both" }
    private var showsLogicalY: Bool { mode == "y" || mode == "both" }

    private func guide(logicalX: Bool) -> some View {
        let horizontal = horizontalBar ? logicalX : !logicalX
        return Rectangle()
            .fill(Color.secondary.opacity(0.35))
            .frame(width: horizontal ? plotFrame.width : 1, height: horizontal ? 1 : plotFrame.height)
            .position(x: horizontal ? plotFrame.midX : position.x, y: horizontal ? position.y : plotFrame.midY)
    }
}

private struct NativePHPChartsSharedTooltip: View {
    let points: [NativePHPChartsPoint]
    let snapshot: NativePHPChartsSnapshot
    let maximumWidth: CGFloat

    var body: some View {
        VStack(alignment: .leading, spacing: 4) {
            if let point = points.first {
                Text(snapshot.formatter.x(point.plotX, data: snapshot.data)).fontWeight(.semibold)
            }
            ForEach(points) { point in
                if let series = snapshot.data.series(id: point.seriesID) {
                    HStack(spacing: 6) {
                        Circle().fill(series.color).frame(width: 7, height: 7)
                        Text("\(series.name) · \(snapshot.formatter.y(point.value))").lineLimit(1)
                    }
                }
            }
        }
        .font(.caption)
        .foregroundStyle(.white)
        .padding(.horizontal, 9)
        .padding(.vertical, 7)
        .frame(maxWidth: maximumWidth)
        .background(.black.opacity(0.84), in: RoundedRectangle(cornerRadius: 9))
        .shadow(color: .black.opacity(0.18), radius: 5, y: 2)
        .accessibilityElement(children: .combine)
    }
}
