import Charts
import SwiftUI

struct LineChartRenderer: View {
    let node: NativeUINode

    private var series: ChartSeries? {
        ChartSeries.decode(from: node.props.getString("series_json", default: "[]")).first
    }

    private var style: ChartStyle {
        ChartStyle.decode(from: node.props.getString("style_json", default: "{}"))
    }

    private var locale: Locale {
        let identifier = node.props.getString("locale", default: "")

        return identifier.isEmpty ? .current : Locale(identifier: identifier)
    }

    private var formatter: NumberFormatter {
        let formatter = NumberFormatter()
        formatter.locale = locale
        formatter.numberStyle = switch node.props.getString("value_format", default: "number") {
        case "currency": .currency
        case "percent": .percent
        default: .decimal
        }

        let currencyCode = node.props.getString("currency_code", default: "")
        if !currencyCode.isEmpty {
            formatter.currencyCode = currencyCode
        }

        let minimumFractionDigits = node.props.getInt("minimum_fraction_digits", default: -1)
        let maximumFractionDigits = node.props.getInt("maximum_fraction_digits", default: -1)
        if minimumFractionDigits >= 0 {
            formatter.minimumFractionDigits = minimumFractionDigits
        }
        if maximumFractionDigits >= 0 {
            formatter.maximumFractionDigits = maximumFractionDigits
        }

        return formatter
    }

    private var emptyLabel: String {
        node.props.getString("empty_label", default: "No data")
    }

    private var accessibilityLabel: String {
        node.props.getString("a11y_label", default: "Chart")
    }

    var body: some View {
        Group {
            if let series, !series.points.isEmpty {
                chart(for: series)
            } else {
                ContentUnavailableView {
                    Label(emptyLabel, systemImage: "chart.line.flattrend.xyaxis")
                }
                .accessibilityElement(children: .ignore)
                .accessibilityLabel(accessibilityLabel)
                .accessibilityValue(emptyLabel)
            }
        }
    }

    @ViewBuilder
    private func chart(for series: ChartSeries) -> some View {
        let lineColor = style.line.color.map(color) ?? series.swiftUIColor
        let pointColor = style.points.color.map(color) ?? lineColor
        let gridColor = style.grid.color.map(color) ?? Color.secondary.opacity(0.18)
        let axisColor = style.axis.color.map(color) ?? Color.secondary.opacity(0.35)
        let labelColor = style.axis.labelColor.map(color) ?? Color.secondary
        let axisFont = font(named: style.axis.font, size: style.axis.fontSize)
        let axisVisible = style.axis.visible ?? true
        let gridVisible = style.grid.visible ?? node.props.getBool("show_grid", default: true)
        let pointsVisible = style.points.visible ?? node.props.getBool("show_points", default: true)
        let labelCount = style.axis.labelCount ?? 4

        Chart {
            if yDomain.contains(0) {
                RuleMark(y: .value("Baseline", 0))
                    .foregroundStyle(axisColor)
                    .lineStyle(StrokeStyle(lineWidth: style.grid.width ?? 1))
            }

            ForEach(Array(series.points.enumerated()), id: \.offset) { _, point in
                LineMark(
                    x: .value("Label", point.label),
                    y: .value(series.name, point.value)
                )
                .foregroundStyle(lineColor)
                .lineStyle(StrokeStyle(lineWidth: style.line.width ?? 2.5, lineCap: .round, lineJoin: .round))
                .interpolationMethod(style.line.interpolation == "smooth" ? .catmullRom : .linear)

                if pointsVisible || series.points.count == 1 {
                    PointMark(
                        x: .value("Label", point.label),
                        y: .value(series.name, point.value)
                    )
                    .foregroundStyle(pointColor)
                    .symbolSize(pow(style.points.size ?? 5, 2))
                }
            }
        }
        .chartYScale(domain: yDomain)
        .chartXAxis {
            AxisMarks(values: .automatic(desiredCount: min(max(series.points.count, 1), labelCount))) { _ in
                AxisGridLine().foregroundStyle(gridVisible ? gridColor : .clear)
                AxisTick().foregroundStyle(axisVisible ? axisColor : .clear)
                AxisValueLabel().font(axisFont).foregroundStyle(axisVisible ? labelColor : .clear)
            }
        }
        .chartYAxis {
            AxisMarks(values: .automatic(desiredCount: labelCount)) { value in
                AxisGridLine().foregroundStyle(gridVisible ? gridColor : .clear)
                AxisTick().foregroundStyle(axisVisible ? axisColor : .clear)
                AxisValueLabel {
                    if let number = value.as(Double.self) {
                        Text(format(number)).font(axisFont)
                    }
                }
                .foregroundStyle(axisVisible ? labelColor : .clear)
            }
        }
        .animation(node.props.getBool("animated", default: true) ? .easeInOut(duration: 0.35) : nil, value: series.animationID)
        .accessibilityElement(children: .ignore)
        .accessibilityLabel(accessibilityLabel)
        .accessibilityValue(series.accessibilitySummary(using: formatter))
    }

    private var yDomain: ClosedRange<Double> {
        let values = series?.points.map(\.value) ?? []
        let minimum = values.min() ?? 0
        let maximum = values.max() ?? 0
        let lower = node.props.getBool("begin_at_zero", default: true) ? min(0, minimum) : minimum
        let upper = node.props.getBool("begin_at_zero", default: true) ? max(0, maximum) : maximum
        let padding = max((upper - lower) * 0.1, 1)

        return (lower - padding)...(upper + padding)
    }

    private func format(_ value: Double) -> String {
        formatter.string(from: value as NSNumber) ?? String(value)
    }

    private func color(_ value: String) -> Color {
        Color(argb: ColorParser.parse(value, default: 0xFF6366F1))
    }

    private func font(named token: String?, size: CGFloat?) -> Font {
        let size = size ?? 10

        if let token, !token.isEmpty, let font = NativeUIFontResolver.font(token, size: size) {
            return font
        }

        return .system(size: size)
    }
}

private struct ChartStyle: Decodable {
    let line: Line
    let points: Points
    let grid: Grid
    let axis: Axis

    static func decode(from json: String) -> ChartStyle {
        guard let data = json.data(using: .utf8), let style = try? JSONDecoder().decode(ChartStyle.self, from: data) else {
            return ChartStyle()
        }

        return style
    }

    init(line: Line = Line(), points: Points = Points(), grid: Grid = Grid(), axis: Axis = Axis()) {
        self.line = line
        self.points = points
        self.grid = grid
        self.axis = axis
    }

    struct Line: Decodable {
        let color: String?
        let width: CGFloat?
        let interpolation: String?

        init(color: String? = nil, width: CGFloat? = nil, interpolation: String? = nil) {
            self.color = color
            self.width = width
            self.interpolation = interpolation
        }
    }

    struct Points: Decodable {
        let visible: Bool?
        let color: String?
        let size: CGFloat?

        init(visible: Bool? = nil, color: String? = nil, size: CGFloat? = nil) {
            self.visible = visible
            self.color = color
            self.size = size
        }
    }

    struct Grid: Decodable {
        let visible: Bool?
        let color: String?
        let width: CGFloat?

        init(visible: Bool? = nil, color: String? = nil, width: CGFloat? = nil) {
            self.visible = visible
            self.color = color
            self.width = width
        }
    }

    struct Axis: Decodable {
        let visible: Bool?
        let color: String?
        let labelColor: String?
        let font: String?
        let fontSize: CGFloat?
        let labelCount: Int?

        enum CodingKeys: String, CodingKey {
            case visible, color, font
            case labelColor = "label_color"
            case fontSize = "font_size"
            case labelCount = "label_count"
        }

        init(visible: Bool? = nil, color: String? = nil, labelColor: String? = nil, font: String? = nil, fontSize: CGFloat? = nil, labelCount: Int? = nil) {
            self.visible = visible
            self.color = color
            self.labelColor = labelColor
            self.font = font
            self.fontSize = fontSize
            self.labelCount = labelCount
        }
    }
}

private struct ChartSeries: Decodable, Identifiable {
    let id: String
    let name: String
    let color: String
    let points: [ChartPoint]

    var swiftUIColor: Color {
        Color(argb: ColorParser.parse(color, default: 0xFF6366F1))
    }

    var animationID: String {
        "\(id)-\(points.map { "\($0.label):\($0.value)" }.joined(separator: ","))"
    }

    func accessibilitySummary(using formatter: NumberFormatter) -> String {
        let values = points.map { "\($0.label): \(formatter.string(from: $0.value as NSNumber) ?? String($0.value))" }.joined(separator: ", ")

        return name.isEmpty ? values : "\(name). \(values)"
    }

    static func decode(from json: String) -> [ChartSeries] {
        guard let data = json.data(using: .utf8) else {
            return []
        }

        return (try? JSONDecoder().decode([ChartSeries].self, from: data)) ?? []
    }
}

private struct ChartPoint: Decodable {
    let label: String
    let value: Double
}
