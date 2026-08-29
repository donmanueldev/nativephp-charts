import Foundation
import SwiftUI

enum NativePHPChartsKind: String, Codable {
    case line
    case area
    case bar
    case scatter
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
}

struct NativePHPChartsWireSeries: Decodable {
    let id: String
    let name: String
    let color: String
    let points: [NativePHPChartsWirePoint]
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

    func renderX(for point: NativePHPChartsPoint, kind: NativePHPChartsKind) -> Double {
        guard kind == .bar else {
            return point.plotX
        }

        return groupedBarGeometry.x(for: point)
    }

    func xDomain(for kind: NativePHPChartsKind, fallback: ClosedRange<Double>) -> ClosedRange<Double> {
        guard kind == .bar else {
            return fallback
        }

        return groupedBarGeometry.domain ?? fallback
    }

    func axisValues(desiredCount: Int) -> [Double] {
        let count = min(max(desiredCount, 1), xValues.count)

        guard count > 0 else {
            return []
        }
        guard xValues.count > count, count > 1 else {
            return xValues
        }

        return (0..<count).map { index in
            let position = Double(index) * Double(xValues.count - 1) / Double(count - 1)
            return xValues[Int(position.rounded())]
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
                    index: pointIndex,
                    seriesID: wireSeries.id,
                    seriesIndex: seriesIndex
                )
            }

            return NativePHPChartsSeries(
                id: wireSeries.id,
                name: wireSeries.name,
                colorValue: wireSeries.color,
                points: points,
                index: seriesIndex
            )
        }

        return NativePHPChartsDataSet(series: series, xType: xAxis.type, categoryLabels: categoryLabels)
    }
}
