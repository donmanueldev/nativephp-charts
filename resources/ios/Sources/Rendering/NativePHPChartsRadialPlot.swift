import Charts
import SwiftUI

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
                    angle: .value("Value", segment.value),
                    innerRadius: .ratio(innerRadius),
                    outerRadius: .ratio(outerRadius(for: segment)),
                    angularInset: snapshot.configuration.style.gap
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
        .accessibilityElement(children: .ignore)
        .accessibilityLabel(snapshot.configuration.accessibilityLabel)
        .accessibilityValue(accessibilitySummary)
        .accessibilityAdjustableAction(moveAccessibleSelection)
    }

    private var selectedSegment: NativePHPChartsRadialSegment? {
        snapshot.data.segment(id: selectedSegmentID)
    }

    private var innerRadius: CGFloat {
        CGFloat(snapshot.configuration.innerRadiusRatio)
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

    private func moveAccessibleSelection(_ direction: AccessibilityAdjustmentDirection) {
        let segments = snapshot.data.selectableSegments
        guard !segments.isEmpty else { return }

        let current = segments.firstIndex { $0.id == selectedSegmentID }
        let target: Int

        switch direction {
        case .increment:
            target = min((current ?? -1) + 1, segments.count - 1)
        case .decrement:
            target = max((current ?? segments.count) - 1, 0)
        @unknown default:
            return
        }

        guard current != target else { return }
        let segment = segments[target]
        select(segment)
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
