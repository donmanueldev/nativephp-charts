import Foundation

struct NativePHPChartsDomain {
    let x: ClosedRange<Double>
    let y: ClosedRange<Double>
    let baseline: Double
    private let stackedGeometry: NativePHPChartsStackedGeometry

    init(
        data: NativePHPChartsDataSet,
        configuration: NativePHPChartsConfiguration,
        formatter: NativePHPChartsFormatter,
        kind: NativePHPChartsKind
    ) {
        let automaticX = NativePHPChartsDomain.makeXDomain(data: data)
        x = NativePHPChartsDomain.explicitDomain(
            automatic: automaticX,
            minimum: configuration.xAxis.plotValue(configuration.xAxis.minimum, formatter: formatter),
            maximum: configuration.xAxis.plotValue(configuration.xAxis.maximum, formatter: formatter)
        )

        let stack = NativePHPChartsStackedGeometry(data: data)
        stackedGeometry = stack
        let values: [Double]

        if configuration.areaMode == .stacked || kind == .bar && configuration.barMode == .stacked {
            values = stack.ranges.flatMap { [$0.lowerBound, $0.upperBound] }
        } else {
            values = data.points.flatMap { point in
                [point.value, point.errorMin, point.errorMax].compactMap { $0 }
            }
        }

        let includesZero = configuration.beginAtZero || kind == .area
        let baseline = configuration.yAxis.baseline?.numberValue
        let automaticY = NativePHPChartsDomain.makeYDomain(
            values: baseline.map { values + [$0] } ?? values,
            beginAtZero: includesZero
        )
        let yDomain = NativePHPChartsDomain.explicitDomain(
            automatic: automaticY,
            minimum: configuration.yAxis.minimum?.numberValue,
            maximum: configuration.yAxis.maximum?.numberValue
        )
        y = yDomain
        self.baseline = baseline ?? NativePHPChartsDomain.makeBaseline(
            values: values,
            includesZero: includesZero,
            domain: yDomain
        )
    }

    private static func explicitDomain(
        automatic: ClosedRange<Double>,
        minimum: Double?,
        maximum: Double?
    ) -> ClosedRange<Double> {
        let lower = minimum ?? automatic.lowerBound
        let upper = maximum ?? automatic.upperBound

        return lower < upper ? lower...upper : automatic
    }

    func areaBounds(for point: NativePHPChartsPoint, mode: NativePHPChartsAreaMode) -> ClosedRange<Double> {
        guard mode == .stacked else {
            return min(0, point.value)...max(0, point.value)
        }

        return stackedGeometry.bounds(for: point)
    }

    func areaOuterY(for point: NativePHPChartsPoint, bounds: ClosedRange<Double>) -> Double {
        point.value >= 0 ? bounds.upperBound : bounds.lowerBound
    }

    func barGeometry(
        for point: NativePHPChartsPoint,
        data: NativePHPChartsDataSet,
        mode: NativePHPChartsBarMode,
        orientation: NativePHPChartsBarOrientation
    ) -> NativePHPChartsBarGeometry {
        NativePHPChartsBarGeometry.resolve(
            point: point,
            data: data,
            baseline: baseline,
            mode: mode,
            orientation: orientation,
            stackedGeometry: stackedGeometry
        )
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
}

struct NativePHPChartsPlottedPosition: Equatable {
    let x: Double
    let y: Double
}

struct NativePHPChartsBarGeometry: Equatable {
    let category: Double
    let valueBounds: ClosedRange<Double>
    let orientation: NativePHPChartsBarOrientation

    static func resolve(
        point: NativePHPChartsPoint,
        data: NativePHPChartsDataSet,
        baseline: Double,
        mode: NativePHPChartsBarMode,
        orientation: NativePHPChartsBarOrientation,
        stackedGeometry: NativePHPChartsStackedGeometry
    ) -> NativePHPChartsBarGeometry {
        NativePHPChartsBarGeometry(
            category: data.renderX(for: point, kind: .bar, barMode: mode),
            valueBounds: mode == .stacked
                ? stackedGeometry.bounds(for: point)
                : min(baseline, point.value)...max(baseline, point.value),
            orientation: orientation
        )
    }

    var anchor: NativePHPChartsPlottedPosition {
        let value = (valueBounds.lowerBound + valueBounds.upperBound) / 2

        switch orientation {
        case .vertical:
            return NativePHPChartsPlottedPosition(x: category, y: value)
        case .horizontal:
            return NativePHPChartsPlottedPosition(x: value, y: category)
        }
    }
}

struct NativePHPChartsStackedGeometry {
    let ranges: [ClosedRange<Double>]
    private let boundsBySelectionID: [String: ClosedRange<Double>]

    init(data: NativePHPChartsDataSet) {
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

        boundsBySelectionID = bounds
        ranges = Array(bounds.values)
    }

    func bounds(for point: NativePHPChartsPoint) -> ClosedRange<Double> {
        boundsBySelectionID[point.selectionID] ?? min(0, point.value)...max(0, point.value)
    }
}

struct NativePHPChartsGroupedBarGeometry {
    private let positions: [String: Double]
    private let xValues: [Double]
    private let outerPadding: Double

    init(points: [NativePHPChartsPoint], xValues: [Double], seriesCount: Int) {
        guard !points.isEmpty, seriesCount > 0 else {
            positions = [:]
            self.xValues = []
            outerPadding = 0
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
        self.xValues = xValues
        let markPadding = max(slotSpacing * 0.55, groupSpacing * 0.08)
        outerPadding = centerIndex * slotSpacing + markPadding
    }

    func domain(containing logicalDomain: ClosedRange<Double>) -> ClosedRange<Double> {
        guard let minimum = xValues.first(where: logicalDomain.contains),
              let maximum = xValues.last(where: logicalDomain.contains)
        else {
            return logicalDomain
        }

        let lowerBound = min(logicalDomain.lowerBound, minimum - outerPadding)
        let upperBound = max(logicalDomain.upperBound, maximum + outerPadding)
        return lowerBound...upperBound
    }

    func x(for point: NativePHPChartsPoint) -> Double {
        positions[point.selectionID] ?? point.plotX
    }
}
