import SwiftUI

/// Converts EDGE node revisions into atomic Cartesian snapshots and composes plot plus legend.
///
/// Selection identity survives unrelated property updates when the point still exists. It is
/// cleared when a new payload removes the selected point, preventing stale tooltips and events.
/// Until the first snapshot loads a neutral placeholder is shown; later reloads keep the last
/// snapshot visible.
struct NativePHPChartsRenderer: View {
    private struct LoadKey: Equatable { let input: NativePHPChartsWireInput; let isDark: Bool }
    let node: NativeUINode
    let kind: NativePHPChartsKind

    @State private var snapshot: NativePHPChartsSnapshot?
    @State private var selectedPointID: String?
    @Environment(\.colorScheme) private var colorScheme

    init(node: NativeUINode, kind: NativePHPChartsKind) {
        self.node = node
        self.kind = kind
    }

    var body: some View {
        Group {
            if let snapshot {
                loaded(snapshot)
            } else {
                NativePHPChartsLoadingPlaceholder()
            }
        }
        .task(id: loadKey) {
            guard let updated = await NativePHPChartsSnapshot.load(input: wireInput, kind: kind, colorSchemeIsDark: colorScheme == .dark),
                  Task.isCancelled == false
            else { return }
            snapshot = updated
            selectedPointID = updated.data.point(selectionID: selectedPointID)?.selectionID
        }
    }

    private var wireInput: NativePHPChartsWireInput {
        NativePHPChartsWireInput(node: node)
    }

    private var loadKey: LoadKey { LoadKey(input: wireInput, isDark: colorScheme == .dark) }

    private func loaded(_ snapshot: NativePHPChartsSnapshot) -> some View {
        Group {
            if snapshot.availability != .available {
                unavailableState(snapshot)
            } else if snapshot.data.isEmpty {
                emptyState(snapshot)
            } else {
                content(snapshot)
            }
        }
        .background(snapshot.configuration.style.color(snapshot.configuration.theme?.background, fallback: .clear))
        .foregroundStyle(snapshot.configuration.style.color(snapshot.configuration.theme?.foreground, fallback: .primary))
    }

    @ViewBuilder
    private func content(_ snapshot: NativePHPChartsSnapshot) -> some View {
        let plot = NativePHPChartsPlot(
            nodeID: node.id,
            kind: kind,
            snapshot: snapshot,
            selectedPointID: $selectedPointID
        )

        let legend = legend(snapshot)

        if !legendVisible(snapshot) {
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

    private func legendVisible(_ snapshot: NativePHPChartsSnapshot) -> Bool {
        snapshot.configuration.legend.visible ?? (snapshot.data.series.count > 1)
    }

    private func legend(_ snapshot: NativePHPChartsSnapshot) -> some View {
        NativePHPChartsLegend(
            data: snapshot.data,
            configuration: snapshot.configuration.legend,
            kind: kind,
            style: snapshot.configuration.style
        )
    }

    private func emptyState(_ snapshot: NativePHPChartsSnapshot) -> some View {
        ContentUnavailableView {
            Label(snapshot.configuration.emptyLabel, systemImage: emptyIcon)
        }
        .accessibilityElement(children: .ignore)
        .accessibilityLabel(snapshot.configuration.accessibilityLabel)
        .accessibilityValue(snapshot.configuration.emptyLabel)
    }

    private func unavailableState(_ snapshot: NativePHPChartsSnapshot) -> some View {
        ContentUnavailableView {
            Label(wireInput.errorLabel, systemImage: "exclamationmark.triangle")
        }
        .accessibilityElement(children: .ignore)
        .accessibilityLabel(snapshot.configuration.accessibilityLabel)
        .accessibilityValue(wireInput.errorLabel)
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
