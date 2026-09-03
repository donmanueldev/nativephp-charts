import Charts
import SwiftUI

/// Owns Cartesian gesture arbitration and the visual selection layer above Swift Charts.
///
/// Scrubbing previews selection on every drag frame but commits only when the drag ends.
/// Viewport gestures likewise preview the domain locally and emit one callback at completion.
/// PHP disallows scrub plus one-finger pan; this layer repeats the arbitration defensively.
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
        let bodyWidth = resolvedCandlestickBodyWidth(proxy: proxy, plotWidth: plotFrame.width)
        return NativePHPChartsSelection.closestPoint(
            to: location,
            proxy: proxy,
            plotFrame: plotFrame,
            data: snapshot.data,
            candidateAxis: NativePHPChartsSelection.candidateAxis(
                kind: kind,
                barOrientation: snapshot.configuration.barOrientation
            ),
            candidateRadius: bodyWidth.map(NativePHPChartsSelection.candlestickCandidateRadius) ?? 44,
            distance: { point, location, proxy in
                selectionDistance(for: point, to: location, proxy: proxy, candlestickBodyWidth: bodyWidth)
            }
        )
    }

    private func resolvedCandlestickBodyWidth(proxy: ChartProxy, plotWidth: CGFloat) -> CGFloat? {
        guard kind == .candlestick, let series = snapshot.data.series.first else { return nil }
        let style = candlestickStyle(for: series)
        let width = style.width.map(NativePHPChartsCandlestickBodyWidth.fixed) ?? .ratio(0.62)
        return candlestickBodyWidth(width, proxy: proxy, plotWidth: plotWidth)
    }

    private func selectionDistance(
        for point: NativePHPChartsPoint,
        to location: CGPoint,
        proxy: ChartProxy,
        candlestickBodyWidth: CGFloat?
    ) -> CGFloat {
        if kind == .bar {
            return NativePHPChartsSelection.barDistance(
                geometry: snapshot.domain.barGeometry(
                    for: point,
                    data: snapshot.data,
                    mode: snapshot.configuration.barMode,
                    orientation: snapshot.configuration.barOrientation
                ),
                to: location,
                proxy: proxy
            )
        }

        if kind == .candlestick,
           let candlestickBodyWidth,
           let series = snapshot.data.series(id: point.seriesID),
           let geometry = NativePHPChartsCandlestickGeometry(
               point: point,
               x: snapshot.data.renderX(for: point, kind: kind),
               style: candlestickStyle(for: series)
           )
        {
            return NativePHPChartsSelection.candlestickDistance(
                geometry: geometry,
                bodyWidth: candlestickBodyWidth,
                to: location,
                proxy: proxy
            )
        }

        return NativePHPChartsSelection.pointDistance(
            at: plottedPosition(for: point),
            to: location,
            proxy: proxy
        )
    }

    private func candlestickBodyWidth(
        _ width: NativePHPChartsCandlestickBodyWidth,
        proxy: ChartProxy,
        plotWidth: CGFloat
    ) -> CGFloat {
        switch width {
        case let .fixed(value):
            return value
        case let .ratio(ratio):
            let spacing: CGFloat
            if let gap = snapshot.data.minimumXGap,
               let lower = proxy.position(forX: gap.lower),
               let upper = proxy.position(forX: gap.upper)
            {
                spacing = abs(upper - lower)
            } else {
                spacing = plotWidth
            }
            return max(spacing * ratio, 1)
        }
    }

    private func candlestickStyle(for series: NativePHPChartsSeries) -> NativePHPChartsStyle.Bar {
        NativePHPChartsStyle.Bar(
            radius: series.style?.bar.radius ?? snapshot.configuration.style.bar.radius,
            width: series.style?.bar.width ?? snapshot.configuration.style.bar.width
        )
    }

    private func panGesture(in plotFrame: CGRect) -> some Gesture {
        DragGesture(minimumDistance: 8, coordinateSpace: .local)
            .onChanged { gesture in
                guard plotFrame.contains(gesture.startLocation) else { return }

                guard var state = viewportGesture ?? initialViewportGesture() else { return }
                let physicalTranslation = isHorizontalBar
                    ? gesture.translation.height
                    : gesture.translation.width
                state.updatePan(
                    translation: NativePHPChartsViewportInteraction.logicalTranslation(
                        Double(physicalTranslation),
                        reversed: isHorizontalBar
                    )
                )
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

    /// Updates only renderer state while the gesture is active; no PHP event is sent here.
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

    /// Commits one semantic viewport event after a domain-changing gesture finishes.
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
        return NativePHPChartsViewportInteraction.logicalFraction(
            Double(coordinate),
            reversed: isHorizontalBar
        )
    }

    @ViewBuilder
    private func selectionIndicator(in plotFrame: CGRect) -> some View {
        if let point = selectedPoint,
           let series = snapshot.data.series(id: point.seriesID),
           let position = selectionPosition(for: point, in: plotFrame),
           isInsidePlot(position, plotFrame: plotFrame)
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

    private func selectionPosition(
        for point: NativePHPChartsPoint,
        in plotFrame: CGRect
    ) -> CGPoint? {
        let plottedPosition = plottedPosition(for: point)
        return NativePHPChartsSelection.position(
            x: plottedPosition.x,
            y: plottedPosition.y,
            proxy: proxy,
            plotFrame: plotFrame
        )
    }

    /// Returns the physical Swift Charts coordinates of the selected mark's visible anchor.
    ///
    /// Horizontal bars are rendered at `-category` to keep labels in source order from top to
    /// bottom, so their selection anchor must mirror that sign convention.
    private func plottedPosition(for point: NativePHPChartsPoint) -> NativePHPChartsPlottedPosition {
        if kind == .bar {
            let geometry = snapshot.domain.barGeometry(
                for: point,
                data: snapshot.data,
                mode: snapshot.configuration.barMode,
                orientation: snapshot.configuration.barOrientation
            )
            let anchor = geometry.anchor

            return geometry.orientation == .horizontal
                ? NativePHPChartsPlottedPosition(x: anchor.x, y: -anchor.y)
                : anchor
        }

        if kind == .candlestick,
           let series = snapshot.data.series(id: point.seriesID),
           let geometry = NativePHPChartsCandlestickGeometry(
               point: point,
               x: snapshot.data.renderX(for: point, kind: kind),
               style: candlestickStyle(for: series)
           )
        {
            return geometry.anchor
        }

        let x = snapshot.data.renderX(
            for: point,
            kind: kind,
            barMode: snapshot.configuration.barMode
        )
        guard kind == .area else {
            return NativePHPChartsPlottedPosition(x: x, y: point.value)
        }

        let bounds = snapshot.domain.areaBounds(for: point, mode: snapshot.configuration.areaMode)
        return NativePHPChartsPlottedPosition(
            x: x,
            y: snapshot.domain.areaOuterY(for: point, bounds: bounds)
        )
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
                        Text("\(series.name) · \(NativePHPChartsCandlestickPresentation.value(for: point, formatter: snapshot.formatter))")
                            .lineLimit(1)
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
