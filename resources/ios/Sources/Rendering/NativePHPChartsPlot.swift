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
    @State private var viewportDomain: ClosedRange<Double>?

    init(
        nodeID: Int,
        kind: NativePHPChartsKind,
        snapshot: NativePHPChartsSnapshot,
        selectedPointID: Binding<String?>
    ) {
        self.nodeID = nodeID
        self.kind = kind
        self.snapshot = snapshot
        _selectedPointID = selectedPointID
        _viewportDomain = State(initialValue: Self.configuredViewportDomain(snapshot: snapshot))
    }

    var body: some View {
        Chart {
            NativePHPChartsAnnotations(snapshot: snapshot)

            if yAxisVisible, snapshot.domain.y.contains(snapshot.domain.baseline) {
                if isHorizontalBar {
                    RuleMark(x: .value("Baseline", snapshot.domain.baseline))
                        .foregroundStyle(axisColor)
                        .lineStyle(StrokeStyle(lineWidth: snapshot.configuration.style.grid.width ?? 1))
                } else {
                    RuleMark(y: .value("Baseline", snapshot.domain.baseline))
                        .foregroundStyle(axisColor)
                        .lineStyle(StrokeStyle(lineWidth: snapshot.configuration.style.grid.width ?? 1))
                }
            }

            if let xBaseline, xAxisVisible, snapshot.domain.x.contains(xBaseline) {
                if isHorizontalBar {
                    RuleMark(y: .value("X baseline", xBaseline))
                        .foregroundStyle(axisColor)
                        .lineStyle(StrokeStyle(lineWidth: snapshot.configuration.style.grid.width ?? 1))
                } else {
                    RuleMark(x: .value("X baseline", xBaseline))
                        .foregroundStyle(axisColor)
                        .lineStyle(StrokeStyle(lineWidth: snapshot.configuration.style.grid.width ?? 1))
                }
            }

            NativePHPChartsMarks(
                kind: kind,
                snapshot: snapshot,
                progress: revealProgress,
                viewport: viewportDomain
            )
        }
        .chartXScale(domain: horizontalDomain ?? viewportDomain ?? categoryDomain)
        .chartYScale(domain: isHorizontalBar ? (viewportDomain ?? categoryDomain) : snapshot.domain.y)
        .chartLegend(.hidden)
        .chartPlotStyle { plotArea in
            plotArea.clipped()
        }
        .chartXAxis { xAxis }
        .chartYAxis { yAxis }
        .chartXAxisLabel(position: .bottom, alignment: .center) {
            if let title = physicalXAxisTitle {
                Text(title)
                    .font(snapshot.configuration.style.axisFont(scale: spatialAxisFontScale))
                    .foregroundStyle(axisLabelColor)
            }
        }
        .chartYAxisLabel(position: .leading, alignment: .center) {
            if let title = physicalYAxisTitle {
                Text(title)
                    .font(snapshot.configuration.style.axisFont(scale: spatialAxisFontScale))
                    .foregroundStyle(axisLabelColor)
            }
        }
        .chartOverlay { proxy in
            NativePHPChartsSelectionOverlay(
                kind: kind,
                snapshot: snapshot,
                selectedPoint: selectedPoint,
                proxy: proxy,
                onPreview: preview,
                onCommit: select,
                viewport: viewportDomain,
                fullViewport: snapshot.domain.x,
                onViewportPreview: { viewportDomain = $0 },
                onViewportCommit: commitViewport
            )
        }
        .animation(revealAnimation, value: revealProgress)
        .task(id: snapshot.data.animationID) {
            await revealChart()
        }
        .task(id: configuredViewportDomain) {
            let configuredDomain = configuredViewportDomain
            if viewportDomain != configuredDomain {
                viewportDomain = configuredDomain
            }
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
                actions: accessiblePointActions,
                onSelect: select
            )
        }
    }

    private var selectedPoint: NativePHPChartsPoint? {
        snapshot.data.point(selectionID: selectedPointID)
    }

    private var spatialAxisFontScale: CGFloat {
        CGFloat(NativePHPChartsTypography.spatialScale(Double(axisFontScale)))
    }

    private var isHorizontalBar: Bool {
        kind == .bar && snapshot.configuration.barOrientation == .horizontal
    }

    private var categoryDomain: ClosedRange<Double> {
        snapshot.data.xDomain(
            for: kind,
            barMode: snapshot.configuration.barMode,
            fallback: viewportDomain ?? snapshot.domain.x
        )
    }

    private var horizontalDomain: ClosedRange<Double>? {
        isHorizontalBar ? snapshot.domain.y : nil
    }

    private var configuredViewportDomain: ClosedRange<Double>? {
        Self.configuredViewportDomain(snapshot: snapshot)
    }

    private static func configuredViewportDomain(
        snapshot: NativePHPChartsSnapshot
    ) -> ClosedRange<Double>? {
        guard snapshot.configuration.viewport.enabled,
              let minimum = snapshot.configuration.xAxis.plotValue(
                  snapshot.configuration.viewport.minimum,
                  formatter: snapshot.formatter
              ),
              let maximum = snapshot.configuration.xAxis.plotValue(
                  snapshot.configuration.viewport.maximum,
                  formatter: snapshot.formatter
              ),
              minimum < maximum
        else { return nil }

        return minimum...maximum
    }

    private var physicalXAxisTitle: String? {
        isHorizontalBar ? snapshot.configuration.yAxis.title : snapshot.configuration.xAxis.title
    }

    private var physicalYAxisTitle: String? {
        isHorizontalBar ? snapshot.configuration.xAxis.title : snapshot.configuration.yAxis.title
    }

    private var xBaseline: Double? {
        snapshot.configuration.xAxis.plotValue(
            snapshot.configuration.xAxis.baseline,
            formatter: snapshot.formatter
        )
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
        if isHorizontalBar, let interval = snapshot.configuration.yAxis.interval {
            horizontalValueAxis(values: .stride(by: interval))
        } else if isHorizontalBar {
            horizontalValueAxis(values: .automatic(desiredCount: yLabelCount))
        } else if let interval = snapshot.configuration.xAxis.plotInterval {
            AxisMarks(values: .stride(by: interval)) { value in
                xAxisMark(value)
            }
        } else if kind == .bar {
            AxisMarks(values: snapshot.data.axisValues(desiredCount: xLabelCount)) { value in
                xAxisMark(value)
            }
        } else {
            AxisMarks(
                values: snapshot.data.axisValues(
                    desiredCount: xLabelCount,
                    in: viewportDomain ?? categoryDomain
                )
            ) { value in
                xAxisMark(value)
            }
        }
    }

    @AxisContentBuilder
    private func horizontalValueAxis(values: AxisMarkValues) -> some AxisContent {
        AxisMarks(values: values) { value in
            AxisGridLine().foregroundStyle(gridVisible ? gridColor : .clear)
            AxisTick().foregroundStyle(yAxisVisible ? axisColor : .clear)
            AxisValueLabel {
                if let number = value.as(Double.self) {
                    Text(snapshot.formatter.y(number))
                        .font(snapshot.configuration.style.axisFont(scale: spatialAxisFontScale))
                }
            }
            .foregroundStyle(yAxisVisible ? axisLabelColor : .clear)
        }
    }

    @AxisMarkBuilder
    private func xAxisMark(_ value: AxisValue) -> some AxisMark {
        AxisGridLine().foregroundStyle(gridVisible ? gridColor : .clear)
        AxisTick().foregroundStyle(xAxisVisible ? axisColor : .clear)
        AxisValueLabel(
            anchor: xAxisLabelAnchor(for: value),
            collisionResolution: .greedy(minimumSpacing: 6)
        ) {
            if let x = value.as(Double.self) {
                Text(snapshot.formatter.x(x, data: snapshot.data))
                    .font(snapshot.configuration.style.axisFont(scale: spatialAxisFontScale))
                    .lineLimit(1)
            }
        }
        .foregroundStyle(xAxisVisible ? axisLabelColor : .clear)
    }

    private func xAxisLabelAnchor(for value: AxisValue) -> UnitPoint? {
        guard let x = value.as(Double.self) else {
            return nil
        }

        let domain = viewportDomain ?? categoryDomain
        let tolerance = max(domain.upperBound - domain.lowerBound, 1) * 0.000_001

        if abs(x - domain.lowerBound) <= tolerance {
            return .topLeading
        }
        if abs(x - domain.upperBound) <= tolerance {
            return .topTrailing
        }

        return .top
    }

    @AxisContentBuilder
    private var yAxis: some AxisContent {
        if isHorizontalBar {
            AxisMarks(values: snapshot.data.axisValues(desiredCount: xLabelCount)) { value in
                AxisTick().foregroundStyle(xAxisVisible ? axisColor : .clear)
                AxisValueLabel {
                    if let x = value.as(Double.self) {
                        Text(snapshot.formatter.x(x, data: snapshot.data))
                            .font(snapshot.configuration.style.axisFont(scale: spatialAxisFontScale))
                    }
                }
                .foregroundStyle(xAxisVisible ? axisLabelColor : .clear)
            }
        } else if let interval = snapshot.configuration.yAxis.interval {
            yAxisMarks(values: .stride(by: interval))
        } else {
            yAxisMarks(values: .automatic(desiredCount: yLabelCount))
        }
    }

    @AxisContentBuilder
    private func yAxisMarks(values: AxisMarkValues) -> some AxisContent {
        AxisMarks(values: values) { value in
            AxisGridLine().foregroundStyle(gridVisible ? gridColor : .clear)
            AxisTick().foregroundStyle(yAxisVisible ? axisColor : .clear)
            AxisValueLabel {
                if let number = value.as(Double.self) {
                    Text(snapshot.formatter.y(number))
                        .font(snapshot.configuration.style.axisFont(scale: spatialAxisFontScale))
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

    private func preview(_ point: NativePHPChartsPoint?) {
        selectedPointID = point?.selectionID
    }

    private func commitViewport(
        _ domain: ClosedRange<Double>,
        reason: NativePHPChartsViewportReason
    ) {
        viewportDomain = domain
        guard snapshot.configuration.onViewportChange > 0,
              let json = NativePHPChartsViewportPayload(
                  chartType: kind.rawValue,
                  reason: reason,
                  xType: snapshot.data.xType.rawValue,
                  minimum: snapshot.formatter.xWire(domain.lowerBound),
                  maximum: snapshot.formatter.xWire(domain.upperBound)
              ).json()
        else { return }

        NativeElementBridge.sendTextChangeEvent(
            snapshot.configuration.onViewportChange,
            nodeId: nodeID,
            text: json
        )
    }

    private var previousAccessiblePoint: NativePHPChartsPoint? {
        let points = snapshot.data.points
        guard let current = points.firstIndex(where: { $0.selectionID == selectedPointID }),
              current > points.startIndex
        else { return nil }

        return points[points.index(before: current)]
    }

    private var nextAccessiblePoint: NativePHPChartsPoint? {
        let points = snapshot.data.points
        guard !points.isEmpty else { return nil }

        guard let current = points.firstIndex(where: { $0.selectionID == selectedPointID }) else {
            return points.first
        }

        let next = points.index(after: current)
        return next < points.endIndex ? points[next] : nil
    }

    private var accessiblePointActions: [NativePHPChartsAccessibilityAction<NativePHPChartsPoint>] {
        var actions: [NativePHPChartsAccessibilityAction<NativePHPChartsPoint>] = []

        if let previousAccessiblePoint {
            actions.append(
                NativePHPChartsAccessibilityAction(
                    dataID: snapshot.data.animationID,
                    direction: .previous,
                    targetID: previousAccessiblePoint.selectionID,
                    label: accessibilityActionLabel(for: previousAccessiblePoint),
                    target: previousAccessiblePoint
                )
            )
        }
        if let nextAccessiblePoint {
            actions.append(
                NativePHPChartsAccessibilityAction(
                    dataID: snapshot.data.animationID,
                    direction: .next,
                    targetID: nextAccessiblePoint.selectionID,
                    label: accessibilityActionLabel(for: nextAccessiblePoint),
                    target: nextAccessiblePoint
                )
            )
        }

        return actions
    }

    private func accessibilityActionLabel(for point: NativePHPChartsPoint) -> String {
        let seriesName = snapshot.data.series(id: point.seriesID)?.name ?? ""
        let pointDescription = "\(snapshot.formatter.x(point: point, data: snapshot.data)), \(snapshot.formatter.y(point.value))"
        return seriesName.isEmpty ? pointDescription : "\(seriesName), \(pointDescription)"
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
