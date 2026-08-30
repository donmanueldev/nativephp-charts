import SwiftUI

struct NativePHPChartsRadarAxis: Decodable, Hashable, Identifiable {
    let id: String
    let label: String
    let maximum: Double
}

struct NativePHPChartsRadarValue: Decodable, Hashable {
    let axis: String
    let value: Double
}

struct NativePHPChartsRadarSeries: Decodable, Hashable, Identifiable {
    let id: String
    let name: String
    let colorValue: String
    let values: [NativePHPChartsRadarValue]

    enum CodingKeys: String, CodingKey {
        case id, name, values
        case colorValue = "color"
    }

    var color: Color { Color(argb: ColorParser.parse(colorValue, default: 0xFF6366F1)) }
}

struct NativePHPChartsRadarSelection: Hashable, Identifiable {
    let series: NativePHPChartsRadarSeries
    let axis: NativePHPChartsRadarAxis
    let value: NativePHPChartsRadarValue
    let index: Int

    var id: String { "\(series.id):\(axis.id)" }
}

struct NativePHPChartsRadarWireInput: Equatable {
    let axesJSON: String
    let seriesJSON: String
    let styleJSON: String
    let legendJSON: String
    let locale: String
    let valueFormat: String
    let currencyCode: String
    let minimumFractionDigits: Int
    let maximumFractionDigits: Int
    let animated: Bool
    let emptyLabel: String
    let accessibilityLabel: String
    let onSelect: Int
    let gridLevels: Int
    let fillOpacity: Double

    init(
        axesJSON: String = "[]",
        seriesJSON: String = "[]",
        styleJSON: String = "{}",
        legendJSON: String = "{}",
        locale: String = "",
        valueFormat: String = "number",
        currencyCode: String = "",
        minimumFractionDigits: Int = -1,
        maximumFractionDigits: Int = -1,
        animated: Bool = true,
        emptyLabel: String = "No data",
        accessibilityLabel: String = "Chart",
        onSelect: Int = 0,
        gridLevels: Int = 5,
        fillOpacity: Double = 0.22
    ) {
        self.axesJSON = axesJSON
        self.seriesJSON = seriesJSON
        self.styleJSON = styleJSON
        self.legendJSON = legendJSON
        self.locale = locale
        self.valueFormat = valueFormat
        self.currencyCode = currencyCode
        self.minimumFractionDigits = minimumFractionDigits
        self.maximumFractionDigits = maximumFractionDigits
        self.animated = animated
        self.emptyLabel = emptyLabel
        self.accessibilityLabel = accessibilityLabel
        self.onSelect = onSelect
        self.gridLevels = gridLevels
        self.fillOpacity = fillOpacity
    }

    init(node: NativeUINode) {
        self.init(
            axesJSON: node.props.getString("axes_json", default: "[]"),
            seriesJSON: node.props.getString("series_json", default: "[]"),
            styleJSON: node.props.getString("style_json", default: "{}"),
            legendJSON: node.props.getString("legend_json", default: "{}"),
            locale: node.props.getString("locale", default: ""),
            valueFormat: node.props.getString("value_format", default: "number"),
            currencyCode: node.props.getString("currency_code", default: ""),
            minimumFractionDigits: node.props.getInt("minimum_fraction_digits", default: -1),
            maximumFractionDigits: node.props.getInt("maximum_fraction_digits", default: -1),
            animated: node.props.getBool("animated", default: true),
            emptyLabel: node.props.getString("empty_label", default: "No data"),
            accessibilityLabel: node.props.getString("a11y_label", default: "Chart"),
            onSelect: node.props.getInt("on_select", default: 0),
            gridLevels: node.props.getInt("grid_levels", default: 5),
            fillOpacity: Double(node.props.getFloat("fill_opacity", default: 0.22))
        )
    }
}

struct NativePHPChartsRadarSnapshot {
    let axes: [NativePHPChartsRadarAxis]
    let series: [NativePHPChartsRadarSeries]
    let style: NativePHPChartsStyle
    let legend: NativePHPChartsLegendConfiguration
    let formatter: NativePHPChartsRadarFormatter
    let animated: Bool
    let emptyLabel: String
    let accessibilityLabel: String
    let onSelect: Int
    let gridLevels: Int
    let fillOpacity: Double
    let animationID: Int

    init(input: NativePHPChartsRadarWireInput) {
        axes = Self.decode([NativePHPChartsRadarAxis].self, input.axesJSON)
        series = Self.decode([NativePHPChartsRadarSeries].self, input.seriesJSON)
        style = NativePHPChartsStyle.decode(input.styleJSON)
        legend = NativePHPChartsLegendConfiguration.decode(input.legendJSON)
        formatter = NativePHPChartsRadarFormatter(input: input)
        animated = input.animated
        emptyLabel = input.emptyLabel
        accessibilityLabel = input.accessibilityLabel
        onSelect = input.onSelect
        gridLevels = min(max(input.gridLevels, 2), 10)
        fillOpacity = min(max(style.area.opacity ?? input.fillOpacity, 0), 1)

        var hasher = Hasher()
        hasher.combine(axes)
        hasher.combine(series)
        animationID = hasher.finalize()
    }

    var isEmpty: Bool { axes.count < 3 || series.allSatisfy(\.values.isEmpty) }
    var legendVisible: Bool { legend.visible ?? (series.count > 1) }

    var selections: [NativePHPChartsRadarSelection] {
        series.flatMap { item in
            item.values.enumerated().compactMap { index, value in
                guard let axis = axes[safe: index], axis.id == value.axis else { return nil }
                return NativePHPChartsRadarSelection(series: item, axis: axis, value: value, index: index)
            }
        }
    }

    func selection(id: String?) -> NativePHPChartsRadarSelection? {
        selections.first { $0.id == id }
    }

    private static func decode<Value: Decodable>(_ type: Value.Type, _ json: String) -> Value where Value: RangeReplaceableCollection {
        guard let data = json.data(using: .utf8), let value = try? JSONDecoder().decode(type, from: data) else {
            return Value()
        }
        return value
    }
}

final class NativePHPChartsRadarFormatter {
    private let numberFormatter: NumberFormatter

    init(input: NativePHPChartsRadarWireInput) {
        numberFormatter = NativePHPChartsFormatter.makeNumberFormatter(
            locale: input.locale.isEmpty ? .current : Locale(identifier: input.locale),
            configuration: NativePHPChartsNumberFormat(
                style: input.valueFormat,
                currencyCode: input.currencyCode.isEmpty ? nil : input.currencyCode,
                minimumFractionDigits: input.minimumFractionDigits >= 0 ? input.minimumFractionDigits : nil,
                maximumFractionDigits: input.maximumFractionDigits >= 0 ? input.maximumFractionDigits : nil
            )
        )
    }

    func value(_ value: Double) -> String {
        numberFormatter.string(from: value as NSNumber) ?? String(value)
    }
}

enum NativePHPChartsRadarAccessibility {
    static func summary(
        selections: [NativePHPChartsRadarSelection],
        formatter: NativePHPChartsRadarFormatter,
        selected: NativePHPChartsRadarSelection?
    ) -> String {
        let descriptions = selections.prefix(18).map {
            "\($0.series.name), \($0.axis.label): \(formatter.value($0.value.value))"
        }
        var result = descriptions.joined(separator: ". ")
        if selections.count > 18 { result += ". (+\(selections.count - 18))" }
        if let selected {
            result += ". \(selected.series.name), \(selected.axis.label), \(formatter.value(selected.value.value))"
        }
        return result
    }

    static func adjacent(
        to selectedID: String?,
        in selections: [NativePHPChartsRadarSelection]
    ) -> (previous: NativePHPChartsRadarSelection?, next: NativePHPChartsRadarSelection?) {
        guard !selections.isEmpty else { return (nil, nil) }
        guard let index = selections.firstIndex(where: { $0.id == selectedID }) else {
            return (nil, selections.first)
        }
        return (
            index > selections.startIndex ? selections[index - 1] : nil,
            index + 1 < selections.endIndex ? selections[index + 1] : nil
        )
    }
}

enum NativePHPChartsRadarSelectionPayload {
    static func json(
        selection: NativePHPChartsRadarSelection,
        formatter: NativePHPChartsRadarFormatter
    ) -> String? {
        NativePHPChartsSelectionPayload(
            chartType: "radar",
            seriesID: selection.series.id,
            seriesName: selection.series.name,
            pointID: selection.axis.id,
            pointIndex: selection.index,
            xType: "category",
            x: .string(selection.axis.id),
            label: selection.axis.label,
            value: selection.value.value,
            localizedValue: formatter.value(selection.value.value)
        ).json()
    }
}

enum NativePHPChartsRadarPathGeometry {
    static func steppedVertices(_ points: [CGPoint], interpolation: String) -> [CGPoint]? {
        guard let first = points.first, points.count > 2,
              interpolation == "step_before" || interpolation == "step_after"
        else { return nil }

        var vertices = [first]
        var previous = first
        for point in Array(points.dropFirst()) + [first] {
            if interpolation == "step_before" {
                vertices.append(CGPoint(x: point.x, y: previous.y))
            } else {
                vertices.append(CGPoint(x: previous.x, y: point.y))
            }
            vertices.append(point)
            previous = point
        }
        return vertices
    }
}

struct NativePHPChartsRadarChartRenderer: View {
    let node: NativeUINode

    @State private var snapshot: NativePHPChartsRadarSnapshot
    @State private var selectedID: String?

    init(node: NativeUINode) {
        self.node = node
        _snapshot = State(initialValue: NativePHPChartsRadarSnapshot(input: NativePHPChartsRadarWireInput(node: node)))
    }

    var body: some View {
        Group {
            if snapshot.isEmpty { emptyState } else { content }
        }
        .onChange(of: wireInput) { _, input in
            let updated = NativePHPChartsRadarSnapshot(input: input)
            snapshot = updated
            if updated.selection(id: selectedID) == nil { selectedID = nil }
        }
    }

    private var wireInput: NativePHPChartsRadarWireInput { NativePHPChartsRadarWireInput(node: node) }

    @ViewBuilder
    private var content: some View {
        let plot = NativePHPChartsRadarPlot(nodeID: node.id, snapshot: snapshot, selectedID: $selectedID)
        if !snapshot.legendVisible {
            plot
        } else {
            switch snapshot.legend.position {
            case "top": VStack(spacing: 10) { legend; plot }
            case "leading": HStack(spacing: 12) { legend; plot }
            case "trailing": HStack(spacing: 12) { plot; legend }
            default: VStack(spacing: 10) { plot; legend }
            }
        }
    }

    private var legend: some View {
        NativePHPChartsRadarLegend(series: snapshot.series, configuration: snapshot.legend, style: snapshot.style)
    }

    private var emptyState: some View {
        ContentUnavailableView { Label(snapshot.emptyLabel, systemImage: "chart.line.uptrend.xyaxis") }
            .accessibilityElement(children: .ignore)
            .accessibilityLabel(snapshot.accessibilityLabel)
            .accessibilityValue(snapshot.emptyLabel)
    }
}

private struct NativePHPChartsRadarPlot: View {
    let nodeID: Int
    let snapshot: NativePHPChartsRadarSnapshot
    @Binding var selectedID: String?

    @Environment(\.accessibilityReduceMotion) private var reduceMotion
    @State private var revealProgress: CGFloat = 0

    var body: some View {
        GeometryReader { geometry in
            let points = plottedSelections(in: geometry.size)
            ZStack {
                Canvas { context, size in draw(context: &context, size: size, points: points) }
                Rectangle().fill(.clear).contentShape(Rectangle())
                    .gesture(SpatialTapGesture().onEnded { select(nearest(to: $0.location, points: points)) })
                if let selected = points.first(where: { $0.selection.id == selectedID }) {
                    Text("\(selected.selection.axis.label) · \(snapshot.formatter.value(selected.selection.value.value))")
                        .font(.caption.weight(.semibold)).foregroundStyle(.white)
                        .padding(.horizontal, 9).padding(.vertical, 6)
                        .background(.black.opacity(0.84), in: Capsule())
                        .position(x: selected.point.x, y: max(20, selected.point.y - 30))
                }
            }
        }
        .animation(NativePHPChartsAnimation.resolved(enabled: snapshot.animated, reduceMotion: reduceMotion), value: revealProgress)
        .task(id: snapshot.animationID) { await revealChart() }
        .accessibilityRepresentation {
            NativePHPChartsAccessibilityRepresentation(
                label: snapshot.accessibilityLabel,
                value: accessibilitySummary,
                actions: accessibilityActions,
                onSelect: select
            )
        }
    }

    private var selected: NativePHPChartsRadarSelection? { snapshot.selection(id: selectedID) }

    private var accessibilitySummary: String {
        NativePHPChartsRadarAccessibility.summary(selections: snapshot.selections, formatter: snapshot.formatter, selected: selected)
    }

    private var accessibilityActions: [NativePHPChartsAccessibilityAction<NativePHPChartsRadarSelection>] {
        let adjacent = NativePHPChartsRadarAccessibility.adjacent(to: selectedID, in: snapshot.selections)
        return [
            adjacent.previous.map { accessibilityAction($0, direction: .previous) },
            adjacent.next.map { accessibilityAction($0, direction: .next) },
        ].compactMap { $0 }
    }

    private func accessibilityAction(
        _ selection: NativePHPChartsRadarSelection,
        direction: NativePHPChartsAccessibilityAction<NativePHPChartsRadarSelection>.Direction
    ) -> NativePHPChartsAccessibilityAction<NativePHPChartsRadarSelection> {
        NativePHPChartsAccessibilityAction(
            dataID: snapshot.animationID,
            direction: direction,
            targetID: selection.id,
            label: "\(selection.series.name), \(selection.axis.label), \(snapshot.formatter.value(selection.value.value))",
            target: selection
        )
    }

    private func select(_ selection: NativePHPChartsRadarSelection?) {
        selectedID = selection?.id
        guard let selection,
              snapshot.onSelect > 0,
              let json = NativePHPChartsRadarSelectionPayload.json(selection: selection, formatter: snapshot.formatter)
        else { return }
        NativeElementBridge.sendTextChangeEvent(snapshot.onSelect, nodeId: nodeID, text: json)
    }

    private func nearest(
        to location: CGPoint,
        points: [(selection: NativePHPChartsRadarSelection, point: CGPoint)]
    ) -> NativePHPChartsRadarSelection? {
        points.min { distance($0.point, location) < distance($1.point, location) }
            .flatMap { distance($0.point, location) <= 44 ? $0.selection : nil }
    }

    private func plottedSelections(in size: CGSize) -> [(selection: NativePHPChartsRadarSelection, point: CGPoint)] {
        let center = CGPoint(x: size.width / 2, y: size.height / 2)
        let radius = min(size.width, size.height) * 0.34
        return snapshot.selections.map { selection in
            (selection, point(index: selection.index, ratio: selection.value.value / selection.axis.maximum, center: center, radius: radius))
        }
    }

    private func draw(
        context: inout GraphicsContext,
        size: CGSize,
        points: [(selection: NativePHPChartsRadarSelection, point: CGPoint)]
    ) {
        let center = CGPoint(x: size.width / 2, y: size.height / 2)
        let radius = min(size.width, size.height) * 0.34
        let gridColor = snapshot.style.color(snapshot.style.grid.color, fallback: .secondary.opacity(0.2))
        let axisColor = snapshot.style.color(snapshot.style.axis.color, fallback: .secondary.opacity(0.28))

        if snapshot.style.grid.visible ?? true {
            for level in 1...snapshot.gridLevels {
                let vertices = snapshot.axes.indices.map {
                    point(index: $0, ratio: Double(level) / Double(snapshot.gridLevels), center: center, radius: radius)
                }
                context.stroke(path(vertices, interpolation: "linear"), with: .color(gridColor), lineWidth: snapshot.style.grid.width ?? 1)
            }
        }
        if snapshot.style.axis.visible ?? true {
            for index in snapshot.axes.indices {
                let outer = point(index: index, ratio: 1, center: center, radius: radius)
                context.stroke(Path { $0.move(to: center); $0.addLine(to: outer) }, with: .color(axisColor), lineWidth: 1)
                let labelPoint = point(index: index, ratio: 1.17, center: center, radius: radius)
                let labelColor = snapshot.style.color(snapshot.style.axis.labelColor, fallback: .secondary)
                context.draw(
                    context.resolve(Text(snapshot.axes[index].label).font(snapshot.style.axisFont(scale: 1)).foregroundStyle(labelColor)),
                    at: labelPoint
                )
            }
        }

        for series in snapshot.series {
            let seriesPoints = points.filter { $0.selection.series.id == series.id }.map(\.point)
            let polygon = path(seriesPoints, interpolation: snapshot.style.line.interpolation ?? "linear")
            let color = resolvedLineColor(for: series)
            if snapshot.style.area.gradient ?? false {
                context.fill(
                    polygon,
                    with: .linearGradient(
                        Gradient(colors: [color.opacity(snapshot.fillOpacity), color.opacity(snapshot.fillOpacity * 0.35)]),
                        startPoint: CGPoint(x: center.x, y: center.y - radius),
                        endPoint: CGPoint(x: center.x, y: center.y + radius)
                    )
                )
            } else {
                context.fill(polygon, with: .color(color.opacity(snapshot.fillOpacity)))
            }
            context.stroke(
                polygon,
                with: .color(color),
                style: StrokeStyle(lineWidth: snapshot.style.line.width ?? 2, dash: snapshot.style.line.dash ?? [])
            )
            if snapshot.style.points.visible ?? true {
                let pointColor = resolvedPointColor(for: series)
                let diameter = snapshot.style.points.size ?? 7
                for plotted in seriesPoints {
                    context.fill(
                        Path(ellipseIn: CGRect(x: plotted.x - diameter / 2, y: plotted.y - diameter / 2, width: diameter, height: diameter)),
                        with: .color(pointColor)
                    )
                }
            }
        }
    }

    private func resolvedLineColor(for series: NativePHPChartsRadarSeries) -> Color {
        snapshot.style.color(snapshot.series.count == 1 ? snapshot.style.line.color : nil, fallback: series.color)
    }

    private func resolvedPointColor(for series: NativePHPChartsRadarSeries) -> Color {
        snapshot.style.color(snapshot.series.count == 1 ? snapshot.style.points.color : nil, fallback: resolvedLineColor(for: series))
    }

    private func point(index: Int, ratio: Double, center: CGPoint, radius: CGFloat) -> CGPoint {
        let angle = -Double.pi / 2 + (2 * Double.pi * Double(index) / Double(max(snapshot.axes.count, 1)))
        let progressRatio = CGFloat(ratio) * revealProgress
        return CGPoint(
            x: center.x + CGFloat(cos(angle)) * radius * progressRatio,
            y: center.y + CGFloat(sin(angle)) * radius * progressRatio
        )
    }

    private func path(_ points: [CGPoint], interpolation: String) -> Path {
        Path { path in
            guard let first = points.first else { return }
            path.move(to: first)
            if interpolation == "smooth", points.count > 2 {
                let closed = [points.last!] + points + [points[0], points[1]]
                for index in 1...points.count {
                    let p0 = closed[index - 1], p1 = closed[index], p2 = closed[index + 1], p3 = closed[index + 2]
                    path.addCurve(
                        to: p2,
                        control1: CGPoint(x: p1.x + (p2.x - p0.x) / 6, y: p1.y + (p2.y - p0.y) / 6),
                        control2: CGPoint(x: p2.x - (p3.x - p1.x) / 6, y: p2.y - (p3.y - p1.y) / 6)
                    )
                }
            } else if let vertices = NativePHPChartsRadarPathGeometry.steppedVertices(
                points,
                interpolation: interpolation
            ) {
                vertices.dropFirst().forEach { path.addLine(to: $0) }
            } else {
                points.dropFirst().forEach { path.addLine(to: $0) }
            }
            path.closeSubpath()
        }
    }

    private func distance(_ left: CGPoint, _ right: CGPoint) -> CGFloat { hypot(left.x - right.x, left.y - right.y) }

    @MainActor
    private func revealChart() async {
        guard snapshot.animated, !reduceMotion else {
            withAnimation(nil) { revealProgress = 1 }
            return
        }
        withAnimation(nil) { revealProgress = 0 }
        await Task.yield()
        guard !Task.isCancelled else { return }
        withAnimation(NativePHPChartsAnimation.resolved(enabled: true, reduceMotion: false)) { revealProgress = 1 }
    }
}

private struct NativePHPChartsRadarLegend: View {
    let series: [NativePHPChartsRadarSeries]
    let configuration: NativePHPChartsLegendConfiguration
    let style: NativePHPChartsStyle

    @ScaledMetric(relativeTo: .caption) private var fontScale = 1.0

    var body: some View {
        Group {
            if isVertical {
                VStack(alignment: horizontalAlignment, spacing: 10) { items }
            } else {
                ViewThatFits(in: .horizontal) {
                    HStack(spacing: 14) { items }.frame(maxWidth: .infinity, alignment: alignment)
                    ScrollView(.horizontal, showsIndicators: false) { HStack(spacing: 14) { items } }
                }
            }
        }
    }

    @ViewBuilder private var items: some View {
        ForEach(series) { item in
            HStack(spacing: 6) {
                Circle().fill(markerColor(for: item)).frame(width: markerSize, height: markerSize)
                Text(item.name).font(font).foregroundStyle(labelColor).lineLimit(1)
            }
            .accessibilityElement(children: .combine)
            .accessibilityLabel(item.name)
        }
    }

    private var markerSize: CGFloat { configuration.style.markerSize ?? 9 }
    private func markerColor(for seriesItem: NativePHPChartsRadarSeries) -> Color {
        style.color(series.count == 1 ? style.line.color : nil, fallback: seriesItem.color)
    }
    private var labelColor: Color {
        configuration.style.labelColor.map { Color(argb: ColorParser.parse($0, default: 0xFF6B7280)) } ?? .secondary
    }
    private var font: Font {
        let size = (configuration.style.fontSize ?? 11) * CGFloat(NativePHPChartsTypography.spatialScale(Double(fontScale)))
        if let token = configuration.style.font, let resolved = NativeUIFontResolver.font(token, size: size) { return resolved }
        return .system(size: size, weight: .medium)
    }
    private var alignment: Alignment {
        switch configuration.alignment { case "start", "leading": .leading; case "end", "trailing": .trailing; default: .center }
    }
    private var horizontalAlignment: HorizontalAlignment {
        switch configuration.alignment { case "end", "trailing": .trailing; case "center": .center; default: .leading }
    }
    private var isVertical: Bool { configuration.position == "leading" || configuration.position == "trailing" }
}

private extension Collection {
    subscript(safe index: Index) -> Element? { indices.contains(index) ? self[index] : nil }
}
