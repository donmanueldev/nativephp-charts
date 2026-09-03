import SwiftUI

/// Converts EDGE node revisions into atomic pie/donut snapshots and composes plot plus legend.
///
/// A selected id survives presentation-only updates but is cleared when its segment disappears.
struct NativePHPChartsRadialRenderer: View {
    let node: NativeUINode
    let kind: NativePHPChartsRadialKind

    @State private var snapshot: NativePHPChartsRadialSnapshot
    @State private var selectedSegmentID: String?

    init(node: NativeUINode, kind: NativePHPChartsRadialKind) {
        self.node = node
        self.kind = kind
        _snapshot = State(
            initialValue: NativePHPChartsRadialSnapshot(
                input: NativePHPChartsRadialWireInput(node: node, kind: kind),
                kind: kind
            )
        )
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
            let updated = NativePHPChartsRadialSnapshot(input: input, kind: kind)
            snapshot = updated

            if updated.data.segment(id: selectedSegmentID) == nil {
                selectedSegmentID = nil
            }
        }
    }

    private var wireInput: NativePHPChartsRadialWireInput {
        NativePHPChartsRadialWireInput(node: node, kind: kind)
    }

    @ViewBuilder
    private var content: some View {
        let plot = NativePHPChartsRadialPlot(
            nodeID: node.id,
            kind: kind,
            snapshot: snapshot,
            selectedSegmentID: $selectedSegmentID
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
        snapshot.configuration.legend.visible ?? (snapshot.data.segments.count > 1)
    }

    private var legend: some View {
        NativePHPChartsRadialLegend(
            data: snapshot.data,
            formatter: snapshot.formatter,
            configuration: snapshot.configuration.legend
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
        kind == .donut ? "chart.pie.fill" : "chart.pie"
    }
}
