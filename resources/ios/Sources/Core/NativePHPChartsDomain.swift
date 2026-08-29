import Foundation

struct NativePHPChartsDomain {
    let x: ClosedRange<Double>
    let y: ClosedRange<Double>
    let baseline: Double
    private let stackedBounds: [String: ClosedRange<Double>]

    init(
        data: NativePHPChartsDataSet,
        configuration: NativePHPChartsConfiguration,
        kind: NativePHPChartsKind
    ) {
        x = NativePHPChartsDomain.makeXDomain(data: data)

        let stack = NativePHPChartsDomain.makeStackedBounds(data: data)
        stackedBounds = stack
        let values: [Double]

        if configuration.areaMode == .stacked {
            values = stack.values.flatMap { [$0.lowerBound, $0.upperBound] }
        } else {
            values = data.points.map(\.value)
        }

        let includesZero = configuration.beginAtZero || kind == .area
        let yDomain = NativePHPChartsDomain.makeYDomain(
            values: values,
            beginAtZero: includesZero
        )
        y = yDomain
        baseline = NativePHPChartsDomain.makeBaseline(
            values: values,
            includesZero: includesZero,
            domain: yDomain
        )
    }

    func areaBounds(for point: NativePHPChartsPoint, mode: NativePHPChartsAreaMode) -> ClosedRange<Double> {
        guard mode == .stacked else {
            return min(0, point.value)...max(0, point.value)
        }

        return stackedBounds[point.selectionID] ?? min(0, point.value)...max(0, point.value)
    }

    func areaOuterY(for point: NativePHPChartsPoint, bounds: ClosedRange<Double>) -> Double {
        point.value >= 0 ? bounds.upperBound : bounds.lowerBound
    }

    private static func makeXDomain(data: NativePHPChartsDataSet) -> ClosedRange<Double> {
        let minimum = data.xValues.first ?? 0
        let maximum = data.xValues.last ?? 0

        if data.xType == .category {
            return (minimum - 0.35)...(maximum + 0.35)
        }

        let span = maximum - minimum
        let padding = span == 0 ? max(abs(maximum) * 0.05, 1) : span * 0.05

        return (minimum - padding)...(maximum + padding)
    }

    private static func makeYDomain(values: [Double], beginAtZero: Bool) -> ClosedRange<Double> {
        let minimum = values.min() ?? 0
        let maximum = values.max() ?? 0
        let lower = beginAtZero ? min(0, minimum) : minimum
        let upper = beginAtZero ? max(0, maximum) : maximum
        let span = upper - lower
        let padding = span == 0 ? max(abs(upper) * 0.1, 1) : span * 0.1

        return (lower - padding)...(upper + padding)
    }

    private static func makeBaseline(
        values: [Double],
        includesZero: Bool,
        domain: ClosedRange<Double>
    ) -> Double {
        guard !includesZero else {
            return 0
        }

        let minimum = values.min() ?? 0
        let maximum = values.max() ?? 0
        if minimum <= 0, maximum >= 0 {
            return 0
        }

        return minimum > 0 ? domain.lowerBound : domain.upperBound
    }

    private static func makeStackedBounds(data: NativePHPChartsDataSet) -> [String: ClosedRange<Double>] {
        var positive: [Double: Double] = [:]
        var negative: [Double: Double] = [:]
        var bounds: [String: ClosedRange<Double>] = [:]

        for series in data.series {
            for point in series.points {
                if point.value >= 0 {
                    let start = positive[point.plotX, default: 0]
                    let end = start + point.value
                    bounds[point.selectionID] = start...end
                    positive[point.plotX] = end
                } else {
                    let start = negative[point.plotX, default: 0]
                    let end = start + point.value
                    bounds[point.selectionID] = end...start
                    negative[point.plotX] = end
                }
            }
        }

        return bounds
    }
}

struct NativePHPChartsGroupedBarGeometry {
    let domain: ClosedRange<Double>?
    private let positions: [String: Double]

    init(points: [NativePHPChartsPoint], xValues: [Double], seriesCount: Int) {
        guard !points.isEmpty, seriesCount > 0 else {
            positions = [:]
            domain = nil
            return
        }

        let positiveGaps = zip(xValues, xValues.dropFirst())
            .map { $1 - $0 }
            .filter { $0 > 0 }
        let groupSpacing = positiveGaps.min() ?? 1
        let slotSpacing = groupSpacing * 0.72 / Double(seriesCount)
        let centerIndex = Double(seriesCount - 1) / 2

        positions = Dictionary(uniqueKeysWithValues: points.map { point in
            let offset = (Double(point.seriesIndex) - centerIndex) * slotSpacing
            return (point.selectionID, point.plotX + offset)
        })

        let renderedValues = positions.values
        guard let minimum = renderedValues.min(), let maximum = renderedValues.max() else {
            domain = nil
            return
        }

        let padding = max(slotSpacing * 0.55, groupSpacing * 0.08)
        domain = (minimum - padding)...(maximum + padding)
    }

    func x(for point: NativePHPChartsPoint) -> Double {
        positions[point.selectionID] ?? point.plotX
    }
}
