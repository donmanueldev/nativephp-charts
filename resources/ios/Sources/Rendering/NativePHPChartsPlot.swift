import Charts
import SwiftUI

struct NativePHPChartsPlot: View {
    let nodeID: Int
    let kind: NativePHPChartsKind
    let snapshot: NativePHPChartsSnapshot
    @Binding var selectedPointID: String?

    @Environment(\.accessibilityReduceMotion) private var reduceMotion
    @ScaledMetric(relativeTo: .caption2) private var axisFontScale = 1.0
    @State private var revealProgress: CGFloat = 0

    var body: some View {
        Chart {
            if snapshot.domain.y.contains(0) {
                RuleMark(y: .value("Baseline", 0))
                    .foregroundStyle(axisColor)
                    .lineStyle(StrokeStyle(lineWidth: snapshot.configuration.style.grid.width ?? 1))
            }

            NativePHPChartsMarks(kind: kind, snapshot: snapshot, progress: revealProgress)
        }
        .chartXScale(domain: snapshot.data.xDomain(for: kind, fallback: snapshot.domain.x))
        .chartYScale(domain: snapshot.domain.y)
        .chartLegend(.hidden)
        .chartXAxis { xAxis }
        .chartYAxis { yAxis }
        .chartOverlay { proxy in
            NativePHPChartsSelectionOverlay(
                kind: kind,
                snapshot: snapshot,
                selectedPoint: selectedPoint,
                proxy: proxy,
                onSelect: select
            )
        }
        .animation(revealAnimation, value: revealProgress)
        .task(id: snapshot.data.animationID) {
            await revealChart()
        }
        .onChange(of: reduceMotion) { _, shouldReduceMotion in
            if shouldReduceMotion {
                withAnimation(nil) { revealProgress = 1 }
            }
        }
        .accessibilityElement(children: .contain)
        .accessibilityLabel(snapshot.configuration.accessibilityLabel)
        .accessibilityValue(accessibilitySummary)
        .accessibilityAdjustableAction(moveAccessibleSelection)
    }

    private var selectedPoint: NativePHPChartsPoint? {
        snapshot.data.point(selectionID: selectedPointID)
    }

    private var accessibilitySummary: String {
        NativePHPChartsAccessibility.summary(
            data: snapshot.data,
            formatter: snapshot.formatter,
            selectedPoint: selectedPoint
        )
    }

    private var revealAnimation: Animation? {
        NativePHPChartsAnimation.resolved(
            enabled: snapshot.configuration.animated,
            reduceMotion: reduceMotion
        )
    }

    @AxisContentBuilder
    private var xAxis: some AxisContent {
        if kind == .bar {
            AxisMarks(values: snapshot.data.axisValues(desiredCount: xLabelCount)) { value in
                xAxisMark(value)
            }
        } else {
            AxisMarks(values: .automatic(desiredCount: xLabelCount)) { value in
                xAxisMark(value)
            }
        }
    }

    @AxisMarkBuilder
    private func xAxisMark(_ value: AxisValue) -> some AxisMark {
        AxisGridLine().foregroundStyle(gridVisible ? gridColor : .clear)
        AxisTick().foregroundStyle(xAxisVisible ? axisColor : .clear)
        AxisValueLabel {
            if let x = value.as(Double.self) {
                Text(snapshot.formatter.x(x, data: snapshot.data))
                    .font(snapshot.configuration.style.axisFont(scale: axisFontScale))
            }
        }
        .foregroundStyle(xAxisVisible ? axisLabelColor : .clear)
    }

    @AxisContentBuilder
    private var yAxis: some AxisContent {
        AxisMarks(values: .automatic(desiredCount: yLabelCount)) { value in
            AxisGridLine().foregroundStyle(gridVisible ? gridColor : .clear)
            AxisTick().foregroundStyle(yAxisVisible ? axisColor : .clear)
            AxisValueLabel {
                if let number = value.as(Double.self) {
                    Text(snapshot.formatter.y(number))
                        .font(snapshot.configuration.style.axisFont(scale: axisFontScale))
                }
            }
            .foregroundStyle(yAxisVisible ? axisLabelColor : .clear)
        }
    }

    private func select(_ point: NativePHPChartsPoint?) {
        selectedPointID = point?.selectionID

        guard let point,
              snapshot.configuration.onSelect > 0,
              let series = snapshot.data.series(id: point.seriesID),
              let json = NativePHPChartsSelectionPayload(
                  chartType: kind.rawValue,
                  seriesID: series.id,
                  seriesName: series.name,
                  pointID: point.id,
                  pointIndex: point.index,
                  xType: snapshot.data.xType.rawValue,
                  x: point.x ?? .string(point.label),
                  label: point.label,
                  value: point.value,
                  localizedValue: snapshot.formatter.y(point.value)
              ).json()
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
        let points = snapshot.data.points
        guard !points.isEmpty else { return }

        let current = points.firstIndex { $0.selectionID == selectedPointID }
        let target: Int

        switch direction {
        case .increment:
            target = min((current ?? -1) + 1, points.count - 1)
        case .decrement:
            target = max((current ?? points.count) - 1, 0)
        @unknown default:
            return
        }

        if current != target { select(points[target]) }
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

    private var gridVisible: Bool {
        snapshot.configuration.style.grid.visible ?? snapshot.configuration.showGrid
    }

    private var xAxisVisible: Bool {
        snapshot.configuration.xAxis.visible
            ?? snapshot.configuration.style.axis.visible
            ?? legacyAxisVisibility
    }

    private var yAxisVisible: Bool {
        snapshot.configuration.yAxis.visible
            ?? snapshot.configuration.style.axis.visible
            ?? legacyAxisVisibility
    }

    private var legacyAxisVisibility: Bool {
        kind == .bar ? snapshot.configuration.showPoints : true
    }

    private var gridColor: Color {
        snapshot.configuration.style.color(
            snapshot.configuration.style.grid.color,
            fallback: .secondary.opacity(0.16)
        )
    }

    private var axisColor: Color {
        snapshot.configuration.style.color(
            snapshot.configuration.style.axis.color,
            fallback: .secondary.opacity(0.35)
        )
    }

    private var axisLabelColor: Color {
        snapshot.configuration.style.color(
            snapshot.configuration.style.axis.labelColor,
            fallback: .secondary
        )
    }

    private var xLabelCount: Int {
        snapshot.configuration.xAxis.labelCount
            ?? snapshot.configuration.style.axis.labelCount
            ?? min(max(snapshot.data.points.count, 2), 6)
    }

    private var yLabelCount: Int {
        snapshot.configuration.yAxis.labelCount
            ?? snapshot.configuration.style.axis.labelCount
            ?? 4
    }
}
