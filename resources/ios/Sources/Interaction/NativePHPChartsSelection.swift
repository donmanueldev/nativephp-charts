import Charts
import Foundation
import SwiftUI

/// Stable version-one event sent to the PHP `_select` callback.
///
/// `pointIndex` is the original source index, even when sampling reduced the rendered data.
/// `x` preserves the public wire scalar and `localizedValue` is display-only; consumers should
/// use the raw numeric `value` for application logic.
struct NativePHPChartsSelectionPayload: Encodable {
    let version = 1
    let chartType: String
    let seriesID: String
    let seriesName: String
    let pointID: String
    let pointIndex: Int
    let xType: String
    let x: NativePHPChartsWireValue
    let label: String
    let value: Double
    let localizedValue: String

    enum CodingKeys: String, CodingKey {
        case version, x, label, value
        case chartType = "chart_type"
        case seriesID = "series_id"
        case seriesName = "series_name"
        case pointID = "point_id"
        case pointIndex = "point_index"
        case xType = "x_type"
        case localizedValue = "localized_value"
    }

    func json() -> String? {
        let encoder = JSONEncoder()
        encoder.outputFormatting = [.sortedKeys, .withoutEscapingSlashes]

        guard let data = try? encoder.encode(self) else {
            return nil
        }

        return String(data: data, encoding: .utf8)
    }
}

/// Stable version-one x-domain event sent after a viewport gesture commits.
///
/// Bounds use the configured x-axis wire type rather than renderer coordinates. The payload
/// is emitted once at gesture end, never for the intermediate preview frames.
struct NativePHPChartsViewportPayload: Encodable {
    let version = 1
    let chartType: String
    let axis = "x"
    let reason: NativePHPChartsViewportReason
    let xType: String
    let minimum: NativePHPChartsWireValue
    let maximum: NativePHPChartsWireValue

    enum CodingKeys: String, CodingKey {
        case version, axis, reason, minimum, maximum
        case chartType = "chart_type"
        case xType = "x_type"
    }

    func json() -> String? {
        let encoder = JSONEncoder()
        encoder.outputFormatting = [.sortedKeys, .withoutEscapingSlashes]
        guard let data = try? encoder.encode(self) else { return nil }
        return String(data: data, encoding: .utf8)
    }
}

/// Maps touch locations to the visible geometry while keeping coordinate conversions explicit.
enum NativePHPChartsSelection {
    enum CandidateAxis: Equatable {
        case x
        case y
    }

    static func candidateAxis(
        kind: NativePHPChartsKind,
        barOrientation: NativePHPChartsBarOrientation
    ) -> CandidateAxis {
        kind == .bar && barOrientation == .horizontal ? .y : .x
    }

    /// Chooses a candidate in two stages: candidate-axis range, then exact pixel distance.
    ///
    /// `location` and `plotFrame` are overlay coordinates. The plot-frame origin is removed
    /// before querying `ChartProxy`, whose positions and custom distance closure are plot-local.
    /// A 44-point final radius provides the minimum touch target for every mark family.
    static func closestPoint(
        to location: CGPoint,
        proxy: ChartProxy,
        plotFrame: CGRect,
        data: NativePHPChartsDataSet,
        candidateAxis: CandidateAxis,
        candidateRadius: CGFloat = 44,
        distance: (NativePHPChartsPoint, CGPoint, ChartProxy) -> CGFloat
    ) -> NativePHPChartsPoint? {
        guard plotFrame.contains(location) else {
            return nil
        }

        let plotLocation = CGPoint(x: location.x - plotFrame.minX, y: location.y - plotFrame.minY)

        let radius: CGFloat = 44
        let candidateRange: ClosedRange<Double>?
        switch candidateAxis {
        case .x:
            let lowerLocation = max(0, plotLocation.x - candidateRadius)
            let upperLocation = min(plotFrame.width, plotLocation.x + candidateRadius)
            if let lower: Double = proxy.value(atX: lowerLocation),
               let upper: Double = proxy.value(atX: upperLocation)
            {
                candidateRange = min(lower, upper)...max(lower, upper)
            } else {
                candidateRange = nil
            }
        case .y:
            let lowerLocation = max(0, plotLocation.y - candidateRadius)
            let upperLocation = min(plotFrame.height, plotLocation.y + candidateRadius)
            if let lower: Double = proxy.value(atY: lowerLocation),
               let upper: Double = proxy.value(atY: upperLocation)
            {
                candidateRange = min(lower, upper)...max(lower, upper)
            } else {
                candidateRange = nil
            }
        }

        guard let candidateRange else {
            return nil
        }

        let candidates = data.selectionCandidates(in: candidateRange)
        guard let point = candidates.min(by: { lhs, rhs in
            distance(lhs, plotLocation, proxy) < distance(rhs, plotLocation, proxy)
        }) else {
            return nil
        }

        return distance(point, plotLocation, proxy) <= radius ? point : nil
    }

    static func candlestickCandidateRadius(bodyWidth: CGFloat) -> CGFloat {
        44 + (bodyWidth / 2)
    }

    /// Converts a logical plot position back into overlay coordinates for UI adornments.
    static func position(
        x: Double,
        y: Double,
        proxy: ChartProxy,
        plotFrame: CGRect
    ) -> CGPoint? {
        guard let x = proxy.position(forX: x),
              let y = proxy.position(forY: y)
        else {
            return nil
        }

        return CGPoint(x: x + plotFrame.minX, y: y + plotFrame.minY)
    }

    static func pointDistance(
        at position: NativePHPChartsPlottedPosition,
        to location: CGPoint,
        proxy: ChartProxy
    ) -> CGFloat {
        guard let x = proxy.position(forX: position.x),
              let y = proxy.position(forY: position.y)
        else {
            return .greatestFiniteMagnitude
        }

        return hypot(x - location.x, y - location.y)
    }

    /// Measures against the full rendered bar segment instead of only its midpoint.
    static func barDistance(
        geometry: NativePHPChartsBarGeometry,
        to location: CGPoint,
        proxy: ChartProxy
    ) -> CGFloat {
        let start: CGPoint
        let end: CGPoint

        switch geometry.orientation {
        case .vertical:
            guard let category = proxy.position(forX: geometry.category),
                  let lower = proxy.position(forY: geometry.valueBounds.lowerBound),
                  let upper = proxy.position(forY: geometry.valueBounds.upperBound)
            else {
                return .greatestFiniteMagnitude
            }
            start = CGPoint(x: category, y: lower)
            end = CGPoint(x: category, y: upper)
        case .horizontal:
            guard let category = proxy.position(forY: geometry.category),
                  let lower = proxy.position(forX: geometry.valueBounds.lowerBound),
                  let upper = proxy.position(forX: geometry.valueBounds.upperBound)
            else {
                return .greatestFiniteMagnitude
            }
            start = CGPoint(x: lower, y: category)
            end = CGPoint(x: upper, y: category)
        }

        return segmentDistance(from: location, start: start, end: end)
    }

    /// Measures against both the visible candle body and wick in plot-local coordinates.
    static func candlestickDistance(
        geometry: NativePHPChartsCandlestickGeometry,
        bodyWidth: CGFloat,
        to location: CGPoint,
        proxy: ChartProxy
    ) -> CGFloat {
        guard let x = proxy.position(forX: geometry.x),
              let open = proxy.position(forY: geometry.open),
              let high = proxy.position(forY: geometry.high),
              let low = proxy.position(forY: geometry.low),
              let close = proxy.position(forY: geometry.close)
        else {
            return .greatestFiniteMagnitude
        }

        let body = CGRect(
            x: x - (bodyWidth / 2),
            y: min(open, close),
            width: bodyWidth,
            height: max(abs(close - open), 1)
        )
        return min(
            rectangleDistance(from: location, to: body),
            segmentDistance(
                from: location,
                start: CGPoint(x: x, y: high),
                end: CGPoint(x: x, y: low)
            )
        )
    }

    static func rectangleDistance(from location: CGPoint, to rectangle: CGRect) -> CGFloat {
        let deltaX = max(rectangle.minX - location.x, 0, location.x - rectangle.maxX)
        let deltaY = max(rectangle.minY - location.y, 0, location.y - rectangle.maxY)
        return hypot(deltaX, deltaY)
    }

    static func segmentDistance(
        from location: CGPoint,
        start: CGPoint,
        end: CGPoint
    ) -> CGFloat {
        let deltaX = end.x - start.x
        let deltaY = end.y - start.y
        let lengthSquared = (deltaX * deltaX) + (deltaY * deltaY)

        guard lengthSquared > 0 else {
            return hypot(location.x - start.x, location.y - start.y)
        }

        let projection = ((location.x - start.x) * deltaX + (location.y - start.y) * deltaY) / lengthSquared
        let fraction = min(max(projection, 0), 1)
        let closest = CGPoint(x: start.x + (fraction * deltaX), y: start.y + (fraction * deltaY))

        return hypot(location.x - closest.x, location.y - closest.y)
    }
}

enum NativePHPChartsCandlestickPresentation {
    static func values(
        for point: NativePHPChartsPoint,
        formatter: NativePHPChartsFormatter
    ) -> String? {
        guard let open = point.open,
              let high = point.high,
              let low = point.low,
              let close = point.close
        else {
            return nil
        }

        return "O \(formatter.y(open)), H \(formatter.y(high)), L \(formatter.y(low)), C \(formatter.y(close))"
    }

    static func value(
        for point: NativePHPChartsPoint,
        formatter: NativePHPChartsFormatter
    ) -> String {
        values(for: point, formatter: formatter) ?? formatter.y(point.value)
    }
}

enum NativePHPChartsAccessibility {
    static func summary(
        data: NativePHPChartsDataSet,
        formatter: NativePHPChartsFormatter,
        selectedPoint: NativePHPChartsPoint?
    ) -> String {
        let pointLimit = 12
        let descriptions = data.series.prefix(6).map { series in
            let points = series.points.prefix(pointLimit).map { point in
                "\(formatter.x(point: point, data: data)): \(NativePHPChartsCandlestickPresentation.value(for: point, formatter: formatter))"
            }
            let remainder = max(0, series.points.count - pointLimit)
            let suffix = remainder == 0 ? "" : " (+\(remainder))"

            return "\(series.name). \(points.joined(separator: ", "))\(suffix)"
        }
        var value = descriptions.joined(separator: ". ")

        if data.series.count > 6 {
            value += ". (+\(data.series.count - 6))"
        }
        if let selectedPoint, let series = data.series(id: selectedPoint.seriesID) {
            value += ". \(series.name), \(selectedPoint.label), \(NativePHPChartsCandlestickPresentation.value(for: selectedPoint, formatter: formatter))"
        }

        return value
    }
}

struct NativePHPChartsTooltip: View {
    let point: NativePHPChartsPoint
    let formatter: NativePHPChartsFormatter
    let color: Color
    let maximumWidth: CGFloat

    var body: some View {
        HStack(spacing: 6) {
            Circle()
                .fill(color)
                .frame(width: 7, height: 7)
            Text("\(point.label) · \(NativePHPChartsCandlestickPresentation.value(for: point, formatter: formatter))")
                .lineLimit(1)
                .truncationMode(.tail)
        }
        .font(.caption.weight(.semibold))
        .foregroundStyle(.white)
        .padding(.horizontal, 9)
        .padding(.vertical, 6)
        .frame(maxWidth: maximumWidth)
        .background(.black.opacity(0.84), in: Capsule())
        .shadow(color: .black.opacity(0.18), radius: 5, y: 2)
        .accessibilityElement(children: .combine)
    }
}
