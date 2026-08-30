import SwiftUI

private struct NativePHPChartsRadarAxis: Decodable, Identifiable {
    let id: String
    let label: String
    let maximum: Double
}

private struct NativePHPChartsRadarValue: Decodable {
    let axis: String
    let value: Double
}

private struct NativePHPChartsRadarSeries: Decodable, Identifiable {
    let id: String
    let name: String
    let color: String
    let values: [NativePHPChartsRadarValue]
}

private struct NativePHPChartsRadarSelection {
    let series: NativePHPChartsRadarSeries
    let axis: NativePHPChartsRadarAxis
    let value: NativePHPChartsRadarValue
    let index: Int
    let point: CGPoint
}

struct NativePHPChartsRadarChartRenderer: View {
    let node: NativeUINode

    @State private var selectionKey: String?

    private var axes: [NativePHPChartsRadarAxis] {
        decode([NativePHPChartsRadarAxis].self, node.props.getString("axes_json", default: "[]"), fallback: [])
    }

    private var series: [NativePHPChartsRadarSeries] {
        decode([NativePHPChartsRadarSeries].self, node.props.getString("series_json", default: "[]"), fallback: [])
    }

    var body: some View {
        GeometryReader { geometry in
            let selections = selections(in: geometry.size)
            ZStack {
                Canvas { context, size in
                    draw(context: &context, size: size, selections: selections)
                }
                Rectangle()
                    .fill(.clear)
                    .contentShape(Rectangle())
                    .gesture(
                        SpatialTapGesture().onEnded { gesture in
                            let target = selections.min { distance($0.point, gesture.location) < distance($1.point, gesture.location) }
                            guard let target, distance(target.point, gesture.location) <= 44 else {
                                selectionKey = nil
                                return
                            }
                            selectionKey = key(target)
                            dispatch(target)
                        }
                    )
                if let selected = selections.first(where: { key($0) == selectionKey }) {
                    Text("\(selected.axis.label) · \(selected.value.value.formatted())")
                        .font(.caption.weight(.semibold))
                        .foregroundStyle(.white)
                        .padding(.horizontal, 9)
                        .padding(.vertical, 6)
                        .background(.black.opacity(0.84), in: Capsule())
                        .position(x: selected.point.x, y: max(20, selected.point.y - 30))
                }
            }
        }
        .accessibilityElement(children: .ignore)
        .accessibilityLabel(node.props.getString("a11y_label", default: "Radar chart"))
        .accessibilityValue("\(series.count) series, \(axes.count) axes")
    }

    private func draw(context: inout GraphicsContext, size: CGSize, selections: [NativePHPChartsRadarSelection]) {
        guard axes.count >= 3 else { return }
        let center = CGPoint(x: size.width / 2, y: size.height / 2)
        let radius = min(size.width, size.height) * 0.34
        let levels = min(max(node.props.getInt("grid_levels", default: 5), 2), 10)

        for level in 1...levels {
            let points = axes.indices.map { point(index: $0, ratio: Double(level) / Double(levels), center: center, radius: radius) }
            context.stroke(path(points), with: .color(.secondary.opacity(0.2)), lineWidth: 1)
        }
        for index in axes.indices {
            let outer = point(index: index, ratio: 1, center: center, radius: radius)
            context.stroke(Path { path in path.move(to: center); path.addLine(to: outer) }, with: .color(.secondary.opacity(0.28)), lineWidth: 1)
            let labelPoint = point(index: index, ratio: 1.17, center: center, radius: radius)
            context.draw(context.resolve(Text(axes[index].label).font(.caption2).foregroundStyle(.secondary)), at: labelPoint)
        }

        let fillOpacity = min(
            max(Double(node.props.getFloat("fill_opacity", default: 0.22)), 0),
            1
        )
        for item in series {
            let values = selections.filter { $0.series.id == item.id }
            let polygon = path(values.map(\.point))
            let color = Color(argb: ColorParser.parse(item.color, default: 0xFF6366F1))
            context.fill(polygon, with: .color(color.opacity(fillOpacity)))
            context.stroke(polygon, with: .color(color), lineWidth: 2)
            for value in values {
                context.fill(Path(ellipseIn: CGRect(x: value.point.x - 3.5, y: value.point.y - 3.5, width: 7, height: 7)), with: .color(color))
            }
        }
    }

    private func selections(in size: CGSize) -> [NativePHPChartsRadarSelection] {
        let center = CGPoint(x: size.width / 2, y: size.height / 2)
        let radius = min(size.width, size.height) * 0.34
        return series.flatMap { item in
            item.values.enumerated().compactMap { index, value in
                guard let axis = axes[safe: index] else { return nil }
                return NativePHPChartsRadarSelection(
                    series: item, axis: axis, value: value, index: index,
                    point: point(index: index, ratio: value.value / axis.maximum, center: center, radius: radius)
                )
            }
        }
    }

    private func point(index: Int, ratio: Double, center: CGPoint, radius: CGFloat) -> CGPoint {
        let angle = -Double.pi / 2 + (2 * Double.pi * Double(index) / Double(max(axes.count, 1)))
        return CGPoint(
            x: center.x + CGFloat(cos(angle)) * radius * CGFloat(ratio),
            y: center.y + CGFloat(sin(angle)) * radius * CGFloat(ratio)
        )
    }

    private func path(_ points: [CGPoint]) -> Path {
        Path { path in
            guard let first = points.first else { return }
            path.move(to: first)
            points.dropFirst().forEach { path.addLine(to: $0) }
            path.closeSubpath()
        }
    }

    private func dispatch(_ selection: NativePHPChartsRadarSelection) {
        let callback = node.props.getInt("on_select", default: 0)
        guard callback > 0,
              let json = NativePHPChartsSelectionPayload(
                  chartType: "radar", seriesID: selection.series.id, seriesName: selection.series.name,
                  pointID: selection.axis.id, pointIndex: selection.index, xType: "category",
                  x: .string(selection.axis.id), label: selection.axis.label, value: selection.value.value,
                  localizedValue: selection.value.value.formatted()
              ).json()
        else { return }
        NativeElementBridge.sendTextChangeEvent(callback, nodeId: node.id, text: json)
    }

    private func key(_ selection: NativePHPChartsRadarSelection) -> String { "\(selection.series.id):\(selection.axis.id)" }
    private func distance(_ left: CGPoint, _ right: CGPoint) -> CGFloat { hypot(left.x - right.x, left.y - right.y) }

    private func decode<Value: Decodable>(_ type: Value.Type, _ json: String, fallback: Value) -> Value {
        (try? JSONDecoder().decode(type, from: Data(json.utf8))) ?? fallback
    }
}

private extension Collection {
    subscript(safe index: Index) -> Element? { indices.contains(index) ? self[index] : nil }
}
