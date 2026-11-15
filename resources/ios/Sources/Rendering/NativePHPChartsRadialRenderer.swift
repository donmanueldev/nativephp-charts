import SwiftUI

/// Converts EDGE node revisions into atomic pie/donut snapshots and composes plot plus legend.
///
/// A selected id survives presentation-only updates but is cleared when its segment disappears.
/// Until the first snapshot loads a neutral placeholder is shown; later reloads keep the last
/// snapshot visible.
struct NativePHPChartsRadialRenderer: View {
    private struct LoadKey: Equatable { let input: NativePHPChartsRadialWireInput; let isDark: Bool }
    let node: NativeUINode
    let kind: NativePHPChartsRadialKind

    @State private var snapshot: NativePHPChartsRadialSnapshot?
    @State private var selectedSegmentID: String?
    @Environment(\.colorScheme) private var colorScheme

    init(node: NativeUINode, kind: NativePHPChartsRadialKind) {
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
            let updated = await NativePHPChartsRadialSnapshot.load(input: wireInput, kind: kind, colorSchemeIsDark: colorScheme == .dark)
            guard Task.isCancelled == false else { return }
            snapshot = updated
            selectedSegmentID = updated.data.segment(id: selectedSegmentID)?.id
        }
    }

    private var wireInput: NativePHPChartsRadialWireInput {
        NativePHPChartsRadialWireInput(node: node, kind: kind)
    }

    private var loadKey: LoadKey { LoadKey(input: wireInput, isDark: colorScheme == .dark) }

    private func themeColor(_ value: String?, fallback: Color) -> Color {
        value.map { Color(argb: ColorParser.parse($0, default: 0xFF6366F1)) } ?? fallback
    }

    private func loaded(_ snapshot: NativePHPChartsRadialSnapshot) -> some View {
        Group {
            if snapshot.availability != .available {
                unavailableState(snapshot)
            } else if snapshot.data.isEmpty {
                emptyState(snapshot)
            } else {
                content(snapshot)
            }
        }
        .background(themeColor(snapshot.configuration.theme?.background, fallback: .clear))
        .foregroundStyle(themeColor(snapshot.configuration.theme?.foreground, fallback: .primary))
    }

    @ViewBuilder
    private func content(_ snapshot: NativePHPChartsRadialSnapshot) -> some View {
        let plot = NativePHPChartsRadialPlot(
            nodeID: node.id,
            kind: kind,
            snapshot: snapshot,
            selectedSegmentID: $selectedSegmentID
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

    private func legendVisible(_ snapshot: NativePHPChartsRadialSnapshot) -> Bool {
        snapshot.configuration.legend.visible ?? (snapshot.data.segments.count > 1)
    }

    private func legend(_ snapshot: NativePHPChartsRadialSnapshot) -> some View {
        NativePHPChartsRadialLegend(
            data: snapshot.data,
            formatter: snapshot.formatter,
            configuration: snapshot.configuration.legend
        )
    }

    private func emptyState(_ snapshot: NativePHPChartsRadialSnapshot) -> some View {
        ContentUnavailableView {
            Label(snapshot.configuration.emptyLabel, systemImage: emptyIcon)
        }
        .accessibilityElement(children: .ignore)
        .accessibilityLabel(snapshot.configuration.accessibilityLabel)
        .accessibilityValue(snapshot.configuration.emptyLabel)
    }

    private func unavailableState(_ snapshot: NativePHPChartsRadialSnapshot) -> some View {
        ContentUnavailableView { Label(wireInput.errorLabel, systemImage: "exclamationmark.triangle") }
            .accessibilityElement(children: .ignore)
            .accessibilityLabel(snapshot.configuration.accessibilityLabel)
            .accessibilityValue(wireInput.errorLabel)
    }

    private var emptyIcon: String {
        kind == .donut ? "chart.pie.fill" : "chart.pie"
    }
}
