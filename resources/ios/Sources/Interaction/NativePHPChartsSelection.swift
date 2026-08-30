import Charts
import Foundation
import SwiftUI

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

enum NativePHPChartsSelection {
    static func closestPoint(
        to location: CGPoint,
        proxy: ChartProxy,
        plotFrame: CGRect,
        data: NativePHPChartsDataSet,
        x: (NativePHPChartsPoint) -> Double,
        y: (NativePHPChartsPoint) -> Double
    ) -> NativePHPChartsPoint? {
        guard plotFrame.contains(location) else {
            return nil
        }

        let plotLocation = CGPoint(x: location.x - plotFrame.minX, y: location.y - plotFrame.minY)

        let radius: CGFloat = 44
        let lowerLocation = max(0, plotLocation.x - radius)
        let upperLocation = min(plotFrame.width, plotLocation.x + radius)

        guard let lowerX: Double = proxy.value(atX: lowerLocation),
              let upperX: Double = proxy.value(atX: upperLocation)
        else {
            return nil
        }

        let candidates = data.selectionCandidates(in: min(lowerX, upperX)...max(lowerX, upperX))
        guard let point = candidates.min(by: { lhs, rhs in
            distance(x: x(lhs), y: y(lhs), to: plotLocation, proxy: proxy)
                < distance(x: x(rhs), y: y(rhs), to: plotLocation, proxy: proxy)
        }) else {
            return nil
        }

        return distance(x: x(point), y: y(point), to: plotLocation, proxy: proxy) <= radius ? point : nil
    }

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

    private static func distance(
        x: Double,
        y: Double,
        to location: CGPoint,
        proxy: ChartProxy
    ) -> CGFloat {
        guard let x = proxy.position(forX: x),
              let y = proxy.position(forY: y)
        else {
            return .greatestFiniteMagnitude
        }

        return hypot(x - location.x, y - location.y)
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
                "\(formatter.x(point: point, data: data)): \(formatter.y(point.value))"
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
            value += ". \(series.name), \(selectedPoint.label), \(formatter.y(selectedPoint.value))"
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
            Text("\(point.label) · \(formatter.y(point.value))")
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
