import Charts
import SwiftUI

/// Renders radial value-space bounds and owns committed segment selection.
struct NativePHPChartsRadialPlot: View {
    let nodeID: Int
    let kind: NativePHPChartsRadialKind
    let snapshot: NativePHPChartsRadialSnapshot
    @Binding var selectedSegmentID: String?

    @Environment(\.accessibilityReduceMotion) private var reduceMotion
    @State private var revealProgress: CGFloat = 0

    var body: some View {
        Chart {
            ForEach(snapshot.data.segments) { segment in
                SectorMark(
                    angle: .value("Value", angularRange(for: segment)),
                    innerRadius: .ratio(innerRadius),
                    outerRadius: .ratio(outerRadius(for: segment))
                )
                .foregroundStyle(segment.color)
                .opacity(opacity(for: segment))
                .cornerRadius(snapshot.configuration.style.cornerRadius)
            }
        }
        .chartLegend(.hidden)
        .mask(NativePHPChartsAngularRevealShape(progress: revealProgress))
        .chartOverlay { proxy in
            NativePHPChartsRadialSelectionOverlay(
                snapshot: snapshot,
                selectedSegmentID: selectedSegmentID,
                revealProgress: revealProgress,
                proxy: proxy,
                onSelect: select
            )
        }
        .animation(revealAnimation, value: revealProgress)
        .task(id: snapshot.data.animationID) {
            await revealChart()
        }
        .onChange(of: snapshot.data.animationID) { _, _ in
            synchronizeSelection()
        }
        .onChange(of: reduceMotion) { _, shouldReduceMotion in
            if shouldReduceMotion {
                withAnimation(nil) { revealProgress = 1 }
            }
        }
        .accessibilityRepresentation {
            NativePHPChartsAccessibilityRepresentation(
                label: snapshot.configuration.accessibilityLabel,
                value: accessibilitySummary,
                actions: accessibleSegmentActions,
                onSelect: select
            )
        }
    }

    private var selectedSegment: NativePHPChartsRadialSegment? {
        snapshot.data.segment(id: selectedSegmentID)
    }

    private var innerRadius: CGFloat {
        CGFloat(snapshot.configuration.innerRadiusRatio)
    }

    /// Converts the visual gap from degrees back into the data set's cumulative value space.
    ///
    /// `SectorMark` requires a half-open range so adjacent segment boundaries do not overlap.
    /// The gap is capped below half the raw sector width, preserving a selectable interior.
    private func angularRange(for segment: NativePHPChartsRadialSegment) -> Range<Double> {
        let rawDegrees = segment.value / snapshot.data.total * 360
        let gapDegrees = min(Double(snapshot.configuration.style.gap), rawDegrees * 0.45)
        let halfGap = snapshot.data.total * gapDegrees / 720

        return (segment.lowerBound + halfGap)..<(segment.upperBound - halfGap)
    }

    private var accessibilitySummary: String {
        NativePHPChartsRadialAccessibility.summary(
            data: snapshot.data,
            formatter: snapshot.formatter,
            selectedSegment: selectedSegment
        )
    }

    private var revealAnimation: Animation? {
        NativePHPChartsAnimation.resolved(
            enabled: snapshot.configuration.animated,
            reduceMotion: reduceMotion
        )
    }

    private func outerRadius(for segment: NativePHPChartsRadialSegment) -> CGFloat {
        selectedSegmentID == segment.id ? 1 : 0.94
    }

    private func opacity(for segment: NativePHPChartsRadialSegment) -> Double {
        let opacity = snapshot.configuration.style.opacity
        guard let selectedSegmentID else { return opacity }
        return selectedSegmentID == segment.id ? opacity : opacity * 0.62
    }

    /// Updates selection and emits only a newly selected, non-nil segment to PHP.
    ///
    /// Re-selecting the same segment or tapping outside the plot does not duplicate callbacks.
    private func select(_ segment: NativePHPChartsRadialSegment?) {
        let previousID = selectedSegmentID
        selectedSegmentID = segment?.id

        guard previousID != segment?.id,
              let segment,
              snapshot.configuration.onSelect > 0,
              let json = NativePHPChartsRadialSelection.payload(
                  kind: kind,
                  segment: segment,
                  formatter: snapshot.formatter
              )
        else {
            return
        }

        NativeElementBridge.sendTextChangeEvent(
            snapshot.configuration.onSelect,
            nodeId: nodeID,
            text: json
        )
    }

    private var previousAccessibleSegment: NativePHPChartsRadialSegment? {
        let segments = snapshot.data.selectableSegments
        guard let current = segments.firstIndex(where: { $0.id == selectedSegmentID }),
              current > segments.startIndex
        else { return nil }

        return segments[segments.index(before: current)]
    }

    private var nextAccessibleSegment: NativePHPChartsRadialSegment? {
        let segments = snapshot.data.selectableSegments
        guard !segments.isEmpty else { return nil }

        guard let current = segments.firstIndex(where: { $0.id == selectedSegmentID }) else {
            return segments.first
        }

        let next = segments.index(after: current)
        return next < segments.endIndex ? segments[next] : nil
    }

    private var accessibleSegmentActions: [NativePHPChartsAccessibilityAction<NativePHPChartsRadialSegment>] {
        var actions: [NativePHPChartsAccessibilityAction<NativePHPChartsRadialSegment>] = []

        if let previousAccessibleSegment {
            actions.append(
                NativePHPChartsAccessibilityAction(
                    dataID: snapshot.data.animationID,
                    direction: .previous,
                    targetID: previousAccessibleSegment.id,
                    label: accessibilityActionLabel(for: previousAccessibleSegment),
                    target: previousAccessibleSegment
                )
            )
        }
        if let nextAccessibleSegment {
            actions.append(
                NativePHPChartsAccessibilityAction(
                    dataID: snapshot.data.animationID,
                    direction: .next,
                    targetID: nextAccessibleSegment.id,
                    label: accessibilityActionLabel(for: nextAccessibleSegment),
                    target: nextAccessibleSegment
                )
            )
        }

        return actions
    }

    private func accessibilityActionLabel(for segment: NativePHPChartsRadialSegment) -> String {
        "\(segment.label), \(snapshot.formatter.value(segment.value))"
    }

    private func synchronizeSelection() {
        guard let segment = selectedSegment else {
            selectedSegmentID = nil
            return
        }

        if segment.value <= 0 {
            selectedSegmentID = nil
        }
    }

    @MainActor
    private func revealChart() async {
        guard snapshot.configuration.animated, !reduceMotion else {
            withAnimation(nil) { revealProgress = 1 }
            return
        }

        withAnimation(nil) { revealProgress = 0 }
        await Task.yield()
        guard !Task.isCancelled else { return }
        withAnimation(revealAnimation) { revealProgress = 1 }
    }
}
