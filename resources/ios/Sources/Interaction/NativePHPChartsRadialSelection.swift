import Foundation

/// Adapts radial identity to the shared version-one point-selection payload.
enum NativePHPChartsRadialSelection {
    static func payload(
        kind: NativePHPChartsRadialKind,
        segment: NativePHPChartsRadialSegment,
        formatter: NativePHPChartsRadialFormatter
    ) -> String? {
        NativePHPChartsSelectionPayload(
            chartType: kind.rawValue,
            seriesID: segment.id,
            seriesName: segment.label,
            pointID: segment.id,
            pointIndex: segment.index,
            xType: NativePHPChartsXAxisType.category.rawValue,
            x: .string(segment.label),
            label: segment.label,
            value: segment.value,
            localizedValue: formatter.value(segment.value)
        ).json()
    }
}

enum NativePHPChartsRadialAccessibility {
    static func summary(
        data: NativePHPChartsRadialDataSet,
        formatter: NativePHPChartsRadialFormatter,
        selectedSegment: NativePHPChartsRadialSegment?
    ) -> String {
        let visibleSegments = data.segments.prefix(12).map { segment in
            "\(segment.label): \(formatter.value(segment.value))"
        }
        let remainder = max(0, data.segments.count - visibleSegments.count)
        var summary = visibleSegments.joined(separator: ", ")

        if remainder > 0 {
            summary += ". (+\(remainder))"
        }
        if let selectedSegment {
            summary += ". \(selectedSegment.label), \(formatter.value(selectedSegment.value))"
        }

        return summary
    }
}
