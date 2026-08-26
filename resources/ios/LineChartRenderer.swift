import Charts
import SwiftUI

/// Renders the platform-neutral `series_json` contract emitted by the PHP element.
///
/// The NativePHP wire protocol currently transports scalar props, so the validated
/// series array is encoded as JSON at the PHP boundary and decoded defensively here.
struct LineChartRenderer: View {
    let node: NativeUINode

    private var series: [ChartSeries] {
        ChartSeries.decode(from: node.props.getString("series_json", default: "[]"))
    }

    private var points: [ChartPoint] {
        series.first?.points ?? []
    }

    private var showGrid: Bool {
        node.props.getBool("show_grid", default: true)
    }

    private var showPoints: Bool {
        node.props.getBool("show_points", default: true)
    }

    private var beginAtZero: Bool {
        node.props.getBool("begin_at_zero", default: true)
    }

    private var animated: Bool {
        node.props.getBool("animated", default: true)
    }

    private var emptyLabel: String {
        node.props.getString("empty_label", default: "No data")
    }

    private var accessibilityLabel: String {
        node.props.getString("a11y_label", default: "Chart")
    }

    var body: some View {
        Group {
            if let series = series.first, !series.points.isEmpty {
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
        Chart {
            if yDomain.contains(0) {
                RuleMark(y: .value("Baseline", 0))
                    .foregroundStyle(Color.secondary.opacity(0.35))
                    .lineStyle(StrokeStyle(lineWidth: 1))
            }

            ForEach(Array(series.points.enumerated()), id: \.offset) { _, point in
                LineMark(
                    x: .value("Label", point.label),
                    y: .value(series.name, point.value)
                )
                .foregroundStyle(series.swiftUIColor)
                .lineStyle(StrokeStyle(lineWidth: 2.5, lineCap: .round, lineJoin: .round))
                .interpolationMethod(.catmullRom)

                if showPoints || series.points.count == 1 {
                    PointMark(
                        x: .value("Label", point.label),
                        y: .value(series.name, point.value)
                    )
                    .foregroundStyle(series.swiftUIColor)
                    .symbolSize(40)
                }
            }
        }
        .chartYScale(domain: yDomain)
        .chartXAxis {
            AxisMarks(values: .automatic(desiredCount: min(max(series.points.count, 1), 4))) { _ in
                AxisGridLine().foregroundStyle(showGrid ? Color.secondary.opacity(0.18) : .clear)
                AxisTick()
                AxisValueLabel()
            }
        }
        .chartYAxis {
            AxisMarks { _ in
                AxisGridLine().foregroundStyle(showGrid ? Color.secondary.opacity(0.18) : .clear)
                AxisTick()
                AxisValueLabel()
            }
        }
        .animation(animated ? .easeInOut(duration: 0.35) : nil, value: series.animationID)
        .accessibilityElement(children: .ignore)
        .accessibilityLabel(accessibilityLabel)
        .accessibilityValue(series.accessibilitySummary)
    }

    private var yDomain: ClosedRange<Double> {
        let values = points.map(\.value)
        let minimum = values.min() ?? 0
        let maximum = values.max() ?? 0
        let lower = beginAtZero ? min(0, minimum) : minimum
        let upper = beginAtZero ? max(0, maximum) : maximum
        let span = upper - lower
        let padding = max(span * 0.1, 1)

        return (lower - padding)...(upper + padding)
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

    var accessibilitySummary: String {
        let values = points.map { "\($0.label): \($0.value.formatted())" }.joined(separator: ", ")

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
