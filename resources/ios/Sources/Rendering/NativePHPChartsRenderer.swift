import SwiftUI

/// Converts EDGE node revisions into atomic Cartesian snapshots and composes plot plus legend.
///
/// Selection identity survives unrelated property updates when the point still exists. It is
/// cleared when a new payload removes the selected point, preventing stale tooltips and events.
struct NativePHPChartsRenderer: View {
    let node: NativeUINode
    let kind: NativePHPChartsKind

    @State private var snapshot: NativePHPChartsSnapshot
    @State private var selectedPointID: String?

    init(node: NativeUINode, kind: NativePHPChartsKind) {
        self.node = node
        self.kind = kind
        _snapshot = State(initialValue: NativePHPChartsSnapshot(input: NativePHPChartsWireInput(node: node), kind: kind))
    }

    var body: some View {
        Group {
            if snapshot.data.isEmpty {
                emptyState
            } else {
                content
            }
        }
        .onChange(of: wireInput) { _, input in
            let updated = NativePHPChartsSnapshot(input: input, kind: kind)
            snapshot = updated

            if updated.data.point(selectionID: selectedPointID) == nil {
                selectedPointID = nil
            }
        }
    }

    private var wireInput: NativePHPChartsWireInput {
        NativePHPChartsWireInput(node: node)
    }

    @ViewBuilder
    private var content: some View {
        let plot = NativePHPChartsPlot(
            nodeID: node.id,
            kind: kind,
            snapshot: snapshot,
            selectedPointID: $selectedPointID
        )

        if !legendVisible {
            plot
        } else {
            switch snapshot.configuration.legend.position {
            case "top":
                VStack(spacing: 10) { legend; plot }
            case "leading":
                HStack(spacing: 12) { legend; plot }
            case "trailing":
                HStack(spacing: 12) { plot; legend }
            default:
                VStack(spacing: 10) { plot; legend }
            }
        }
    }

    private var legendVisible: Bool {
        snapshot.configuration.legend.visible ?? (snapshot.data.series.count > 1)
    }

    private var legend: some View {
        NativePHPChartsLegend(
            data: snapshot.data,
            configuration: snapshot.configuration.legend,
            kind: kind,
            style: snapshot.configuration.style
        )
    }

    private var emptyState: some View {
        ContentUnavailableView {
            Label(snapshot.configuration.emptyLabel, systemImage: emptyIcon)
        }
        .accessibilityElement(children: .ignore)
        .accessibilityLabel(snapshot.configuration.accessibilityLabel)
        .accessibilityValue(snapshot.configuration.emptyLabel)
    }

    private var emptyIcon: String {
        switch kind {
        case .line: "chart.line.flattrend.xyaxis"
        case .area: "chart.xyaxis.line"
        case .bar: "chart.bar"
        case .scatter: "chart.dots.scatter"
        case .candlestick: "chart.xyaxis.line"
        }
    }
}
