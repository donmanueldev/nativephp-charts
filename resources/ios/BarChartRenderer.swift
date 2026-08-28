import Charts
import SwiftUI

struct BarChartRenderer: View {
    let node: NativeUINode
    @State private var selectedLabel: String?

    private var series: BarChartSeries? { BarChartSeries.decode(node.props.getString("series_json", default: "[]")).first }
    private var formatter: NumberFormatter {
        let formatter = NumberFormatter()
        formatter.locale = locale
        formatter.numberStyle = switch node.props.getString("value_format", default: "number") { case "currency": .currency; case "percent": .percent; default: .decimal }
        let code = node.props.getString("currency_code", default: "")
        if !code.isEmpty { formatter.currencyCode = code }
        let minDigits = node.props.getInt("minimum_fraction_digits", default: -1)
        let maxDigits = node.props.getInt("maximum_fraction_digits", default: -1)
        if minDigits >= 0 { formatter.minimumFractionDigits = minDigits }
        if maxDigits >= 0 { formatter.maximumFractionDigits = maxDigits }
        return formatter
    }
    private var locale: Locale { let value = node.props.getString("locale", default: ""); return value.isEmpty ? .current : Locale(identifier: value) }

    var body: some View {
        Group {
            if let series, !series.points.isEmpty { chart(series) }
            else {
                ContentUnavailableView { Label(node.props.getString("empty_label", default: "No data"), systemImage: "chart.bar") }
                    .accessibilityElement(children: .ignore)
                    .accessibilityLabel(node.props.getString("a11y_label", default: "Chart"))
                    .accessibilityValue(node.props.getString("empty_label", default: "No data"))
            }
        }
    }

    @ViewBuilder private func chart(_ series: BarChartSeries) -> some View {
        let axisVisible = node.props.getBool("show_points", default: true)
        let gridVisible = node.props.getBool("show_grid", default: true)
        Chart {
            ForEach(series.points) { point in
                BarMark(x: .value("Label", point.label), y: .value(series.name, point.value))
                    .foregroundStyle(series.color)
                    .cornerRadius(5)
            }
            if yDomain.contains(0) { RuleMark(y: .value("Baseline", 0)).foregroundStyle(Color.secondary.opacity(0.35)) }
        }
        .chartYScale(domain: yDomain)
        .chartXAxis { AxisMarks { _ in AxisGridLine().foregroundStyle(.clear); AxisTick().foregroundStyle(axisVisible ? Color.secondary : .clear); AxisValueLabel().foregroundStyle(axisVisible ? Color.secondary : .clear) } }
        .chartYAxis { AxisMarks { value in AxisGridLine().foregroundStyle(gridVisible ? Color.secondary.opacity(0.18) : .clear); AxisTick().foregroundStyle(axisVisible ? Color.secondary : .clear); AxisValueLabel { if let number = value.as(Double.self) { Text(format(number)) } }.foregroundStyle(axisVisible ? Color.secondary : .clear) } }
        .chartOverlay { proxy in
            GeometryReader { geometry in
                let plotFrame = geometry[proxy.plotAreaFrame]

                ZStack(alignment: .topLeading) {
                    Rectangle()
                        .fill(.clear)
                        .contentShape(Rectangle())
                        .gesture(
                            SpatialTapGesture().onEnded { gesture in
                                guard plotFrame.contains(gesture.location),
                                      let label: String = proxy.value(atX: gesture.location.x - plotFrame.origin.x),
                                      series.points.contains(where: { $0.label == label })
                                else {
                                    selectedLabel = nil

                                    return
                                }

                                selectedLabel = label
                            }
                        )

                    if let selectedPoint,
                       let x = proxy.position(forX: selectedPoint.label),
                       let y = proxy.position(forY: selectedPoint.value)
                    {
                        Text("\(selectedPoint.label) · \(format(selectedPoint.value))")
                            .font(.caption.weight(.semibold))
                            .foregroundStyle(.white)
                            .lineLimit(1)
                            .fixedSize()
                            .padding(.horizontal, 8)
                            .padding(.vertical, 5)
                            .background(Color.black.opacity(0.86), in: Capsule())
                            .position(
                                x: x + plotFrame.origin.x,
                                y: max(plotFrame.minY + 18, y + plotFrame.origin.y - 26),
                            )
                    }
                }
            }
        }
        .animation(node.props.getBool("animated", default: true) ? .easeInOut(duration: 0.35) : nil, value: series.animationID)
        .accessibilityElement(children: .ignore)
        .accessibilityLabel(node.props.getString("a11y_label", default: "Chart"))
        .accessibilityValue(series.summary(formatter))
    }

    private var yDomain: ClosedRange<Double> {
        let values = series?.points.map(\.value) ?? []; let minimum = values.min() ?? 0; let maximum = values.max() ?? 0
        let lower = node.props.getBool("begin_at_zero", default: true) ? min(0, minimum) : minimum
        let upper = node.props.getBool("begin_at_zero", default: true) ? max(0, maximum) : maximum
        let span = upper - lower; let padding = span == 0 ? (upper == 0 ? 1 : abs(upper) * 0.1) : span * 0.1
        return (lower - padding)...(upper + padding)
    }
    private func format(_ value: Double) -> String { formatter.string(from: value as NSNumber) ?? String(value) }

    private var selectedPoint: BarChartPoint? {
        guard let selectedLabel else {
            return nil
        }

        return series?.points.first(where: { $0.label == selectedLabel })
    }
}

private struct BarChartSeries: Decodable {
    let id: String; let name: String; let colorValue: String; let points: [BarChartPoint]
    enum CodingKeys: String, CodingKey { case id, name, points; case colorValue = "color" }
    var color: Color { Color(argb: ColorParser.parse(colorValue, default: 0xFF6366F1)) }
    var animationID: String { "\(id)-\(points.map { "\($0.label):\($0.value)" }.joined(separator: ","))" }
    func summary(_ formatter: NumberFormatter) -> String { "\(name). \(points.map { "\($0.label): \(formatter.string(from: $0.value as NSNumber) ?? String($0.value))" }.joined(separator: ", "))" }
    static func decode(_ json: String) -> [BarChartSeries] { guard let data = json.data(using: .utf8) else { return [] }; return (try? JSONDecoder().decode([BarChartSeries].self, from: data)) ?? [] }
}
private struct BarChartPoint: Decodable, Identifiable { let label: String; let value: Double; var id: String { label } }
