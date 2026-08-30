import Foundation
import SwiftUI

enum NativePHPChartsKind: String, Codable {
    case line
    case area
    case bar
    case scatter
    case candlestick
}

enum NativePHPChartsXAxisType: String, Codable {
    case category
    case number
    case date
    case datetime
}

enum NativePHPChartsWireValue: Hashable, Codable {
    case string(String)
    case number(Double)

    init(from decoder: Decoder) throws {
        let container = try decoder.singleValueContainer()

        if let number = try? container.decode(Double.self) {
            self = .number(number)
        } else {
            self = .string(try container.decode(String.self))
        }
    }

    func encode(to encoder: Encoder) throws {
        var container = encoder.singleValueContainer()

        switch self {
        case let .number(value):
            try container.encode(value)
        case let .string(value):
            try container.encode(value)
        }
    }

    var stringValue: String {
        switch self {
        case let .number(value): String(value)
        case let .string(value): value
        }
    }

    var numberValue: Double? {
        switch self {
        case let .number(value): value
        case let .string(value): Double(value)
        }
    }
}

struct NativePHPChartsWirePoint: Decodable {
    let id: String?
    let label: String
    let value: Double
    let x: NativePHPChartsWireValue?
    let errorMin: Double?
    let errorMax: Double?
    let sourceIndex: Int?
    let open: Double?
    let high: Double?
    let low: Double?
    let close: Double?

    enum CodingKeys: String, CodingKey {
        case id, label, value, x, open, high, low, close
        case errorMin = "error_min"
        case errorMax = "error_max"
        case sourceIndex = "source_index"
    }
}

struct NativePHPChartsWireSeries: Decodable {
    let id: String
    let name: String
    let color: String
    let points: [NativePHPChartsWirePoint]
    let style: NativePHPChartsStyle?
    let fillTo: String?

    enum CodingKeys: String, CodingKey {
        case id, name, color, points, style
        case fillTo = "fill_to"
    }
}

struct NativePHPChartsPoint: Identifiable, Hashable {
    let id: String
    let label: String
    let value: Double
    let x: NativePHPChartsWireValue?
    let plotX: Double
    let index: Int
    let seriesID: String
    let seriesIndex: Int
    let errorMin: Double?
    let errorMax: Double?
    let open: Double?
    let high: Double?
    let low: Double?
    let close: Double?

    var selectionID: String {
        "\(seriesID.utf8.count):\(seriesID)\(id)"
    }
}

struct NativePHPChartsSeries: Identifiable {
    let id: String
    let name: String
    let colorValue: String
    let points: [NativePHPChartsPoint]
    let index: Int
    let style: NativePHPChartsStyle?
    let fillTo: String?

    var color: Color {
        Color(argb: ColorParser.parse(colorValue, default: 0xFF6366F1))
    }
}

struct NativePHPChartsDataSet {
    let series: [NativePHPChartsSeries]
    let xType: NativePHPChartsXAxisType
    let categoryLabels: [Int: String]
    let points: [NativePHPChartsPoint]
    let xValues: [Double]
    let animationID: Int
    private let pointsBySelectionID: [String: NativePHPChartsPoint]
    private let seriesByID: [String: NativePHPChartsSeries]
    private let pointsByX: [Double: [NativePHPChartsPoint]]
    private let pointsBySeriesAndX: [String: [Double: NativePHPChartsPoint]]
    private let groupedBarGeometry: NativePHPChartsGroupedBarGeometry

    init(
        series: [NativePHPChartsSeries],
        xType: NativePHPChartsXAxisType,
        categoryLabels: [Int: String]
    ) {
        self.series = series
        self.xType = xType
        self.categoryLabels = categoryLabels

        let points = series.flatMap(\.points)
        self.points = points
        xValues = Array(Set(points.map(\.plotX))).sorted()
        pointsBySelectionID = Dictionary(
            points.map { ($0.selectionID, $0) },
            uniquingKeysWith: { existing, _ in existing }
        )
        seriesByID = Dictionary(
            series.map { ($0.id, $0) },
            uniquingKeysWith: { existing, _ in existing }
        )
        pointsByX = Dictionary(grouping: points, by: \.plotX)
        pointsBySeriesAndX = Dictionary(
            series.map { series in
                (
                    series.id,
                    Dictionary(
                        series.points.map { ($0.plotX, $0) },
                        uniquingKeysWith: { existing, _ in existing }
                    )
                )
            },
            uniquingKeysWith: { existing, _ in existing }
        )
        groupedBarGeometry = NativePHPChartsGroupedBarGeometry(
            points: points,
            xValues: xValues,
            seriesCount: series.count
        )
        var hasher = Hasher()
        hasher.combine(xType)

        for series in series {
            hasher.combine(series.id)
            hasher.combine(series.colorValue)
            for point in series.points {
                hasher.combine(point)
            }
        }

        animationID = hasher.finalize()
    }

    var isEmpty: Bool {
        points.isEmpty
    }

    func point(selectionID: String?) -> NativePHPChartsPoint? {
        guard let selectionID else {
            return nil
        }

        return pointsBySelectionID[selectionID]
    }

    func series(id: String) -> NativePHPChartsSeries? {
        seriesByID[id]
    }

    func points(atPlotX plotX: Double) -> [NativePHPChartsPoint] {
        pointsByX[plotX, default: []]
    }

    func fillTarget(for series: NativePHPChartsSeries, point: NativePHPChartsPoint) -> NativePHPChartsPoint? {
        guard let fillTo = series.fillTo else { return nil }
        return pointsBySeriesAndX[fillTo]?[point.plotX]
    }

    func visiblePoints(
        for series: NativePHPChartsSeries,
        in viewport: ClosedRange<Double>?
    ) -> [NativePHPChartsPoint] {
        let points = series.points
        guard let viewport, points.count > 2
        else {
            return points
        }

        var visible = Array(repeating: false, count: points.count)

        for index in points.indices {
            if viewport.contains(points[index].plotX) {
                visible[index] = true
            }

            guard index > points.startIndex else { continue }

            let previousIndex = points.index(before: index)
            let segmentMinimum = min(points[previousIndex].plotX, points[index].plotX)
            let segmentMaximum = max(points[previousIndex].plotX, points[index].plotX)
            if segmentMinimum <= viewport.upperBound, segmentMaximum >= viewport.lowerBound {
                visible[previousIndex] = true
                visible[index] = true
            }
        }

        return points.enumerated().compactMap { index, point in
            visible[index] ? point : nil
        }
    }

    func categoryPlotX(_ value: NativePHPChartsWireValue?) -> Double? {
        guard case let .string(category) = value else { return nil }
        return points.first { ($0.x?.stringValue ?? $0.label) == category }?.plotX
    }

    func renderX(
        for point: NativePHPChartsPoint,
        kind: NativePHPChartsKind,
        barMode: NativePHPChartsBarMode = .grouped
    ) -> Double {
        guard kind == .bar, barMode == .grouped else {
            return point.plotX
        }

        return groupedBarGeometry.x(for: point)
    }

    func xDomain(
        for kind: NativePHPChartsKind,
        barMode: NativePHPChartsBarMode = .grouped,
        fallback: ClosedRange<Double>
    ) -> ClosedRange<Double> {
        guard kind == .bar, barMode == .grouped else {
            return fallback
        }

        return groupedBarGeometry.domain ?? fallback
    }

    func axisValues(
        desiredCount: Int,
        in domain: ClosedRange<Double>? = nil
    ) -> [Double] {
        let availableValues = domain.map { visibleDomain in
            xValues.filter(visibleDomain.contains)
        } ?? xValues
        let count = min(max(desiredCount, 1), availableValues.count)

        guard count > 0 else {
            return []
        }
        guard availableValues.count > count, count > 1 else {
            return availableValues
        }

        return (0..<count).map { index in
            let position = Double(index) * Double(availableValues.count - 1) / Double(count - 1)
            return availableValues[Int(position.rounded())]
        }
    }

    func selectionCandidates(in range: ClosedRange<Double>) -> [NativePHPChartsPoint] {
        guard !xValues.isEmpty else {
            return []
        }

        var lowerIndex = 0
        var upperIndex = xValues.count
        while lowerIndex < upperIndex {
            let middle = (lowerIndex + upperIndex) / 2
            if xValues[middle] < range.lowerBound {
                lowerIndex = middle + 1
            } else {
                upperIndex = middle
            }
        }

        var endIndex = lowerIndex
        while endIndex < xValues.count, xValues[endIndex] <= range.upperBound {
            endIndex += 1
        }

        let start = max(lowerIndex - 1, 0)
        let end = min(endIndex + 1, xValues.count)
        return xValues[start..<end].flatMap { pointsByX[$0, default: []] }
    }

    static func decode(
        seriesJSON: String,
        xAxis: NativePHPChartsAxisConfiguration,
        formatter: NativePHPChartsFormatter
    ) -> NativePHPChartsDataSet {
        guard let data = seriesJSON.data(using: .utf8),
              let decoded = try? JSONDecoder().decode([NativePHPChartsWireSeries].self, from: data)
        else {
            return NativePHPChartsDataSet(series: [], xType: xAxis.type, categoryLabels: [:])
        }

        var categoryIndexes: [String: Int] = [:]
        var categoryLabels: [Int: String] = [:]

        let series = decoded.enumerated().map { seriesIndex, wireSeries in
            let points = wireSeries.points.enumerated().map { pointIndex, wirePoint in
                let pointID: String
                if let explicitID = wirePoint.id, !explicitID.isEmpty {
                    pointID = explicitID
                } else {
                    pointID = "compat-\(wireSeries.id)-\(pointIndex)"
                }
                let plotX: Double

                switch xAxis.type {
                case .category:
                    let category = wirePoint.x?.stringValue ?? wirePoint.label
                    if let existing = categoryIndexes[category] {
                        plotX = Double(existing)
                    } else {
                        let index = categoryIndexes.count
                        categoryIndexes[category] = index
                        categoryLabels[index] = wirePoint.label
                        plotX = Double(index)
                    }
                case .number:
                    plotX = wirePoint.x?.numberValue ?? Double(pointIndex)
                case .date, .datetime:
                    plotX = wirePoint.x.flatMap { formatter.date(from: $0, type: xAxis.type)?.timeIntervalSince1970 }
                        ?? Double(pointIndex)
                }

                return NativePHPChartsPoint(
                    id: pointID,
                    label: wirePoint.label,
                    value: wirePoint.value,
                    x: wirePoint.x,
                    plotX: plotX,
                    index: wirePoint.sourceIndex ?? pointIndex,
                    seriesID: wireSeries.id,
                    seriesIndex: seriesIndex,
                    errorMin: wirePoint.errorMin,
                    errorMax: wirePoint.errorMax,
                    open: wirePoint.open,
                    high: wirePoint.high,
                    low: wirePoint.low,
                    close: wirePoint.close
                )
            }

            return NativePHPChartsSeries(
                id: wireSeries.id,
                name: wireSeries.name,
                colorValue: wireSeries.color,
                points: points,
                index: seriesIndex,
                style: wireSeries.style,
                fillTo: wireSeries.fillTo
            )
        }

        return NativePHPChartsDataSet(series: series, xType: xAxis.type, categoryLabels: categoryLabels)
    }
}
