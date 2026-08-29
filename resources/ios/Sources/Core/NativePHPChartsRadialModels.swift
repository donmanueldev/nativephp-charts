import Foundation
import SwiftUI

private struct NativePHPChartsWireSegment: Decodable {
    let id: String
    let label: String
    let value: Double
    let color: String
}

struct NativePHPChartsRadialSegment: Identifiable, Hashable {
    let id: String
    let label: String
    let value: Double
    let colorValue: String
    let index: Int
    let lowerBound: Double
    let upperBound: Double

    var color: Color {
        Color(argb: ColorParser.parse(colorValue, default: 0xFF6366F1))
    }

    var midpoint: Double {
        lowerBound + ((upperBound - lowerBound) / 2)
    }
}

struct NativePHPChartsRadialDataSet {
    let segments: [NativePHPChartsRadialSegment]
    let total: Double
    let animationID: Int
    private let segmentsByID: [String: NativePHPChartsRadialSegment]
    let selectableSegments: [NativePHPChartsRadialSegment]

    init(segments: [NativePHPChartsRadialSegment], total: Double) {
        self.segments = segments
        self.total = total
        segmentsByID = Dictionary(uniqueKeysWithValues: segments.map { ($0.id, $0) })
        selectableSegments = segments.filter { $0.value > 0 }

        var hasher = Hasher()
        for segment in segments {
            hasher.combine(segment)
        }
        animationID = hasher.finalize()
    }

    var isEmpty: Bool {
        segments.isEmpty || total <= 0
    }

    func segment(id: String?) -> NativePHPChartsRadialSegment? {
        guard let id else { return nil }
        return segmentsByID[id]
    }

    func segment(containing angleValue: Double) -> NativePHPChartsRadialSegment? {
        guard !selectableSegments.isEmpty, angleValue.isFinite else { return nil }

        let target = min(max(angleValue, 0), total)
        var lower = 0
        var upper = selectableSegments.count

        while lower < upper {
            let middle = (lower + upper) / 2
            if selectableSegments[middle].upperBound < target {
                lower = middle + 1
            } else {
                upper = middle
            }
        }

        return selectableSegments[min(lower, selectableSegments.count - 1)]
    }

    static func decode(_ json: String) -> NativePHPChartsRadialDataSet {
        guard let data = json.data(using: .utf8),
              let decoded = try? JSONDecoder().decode([NativePHPChartsWireSegment].self, from: data)
        else {
            return NativePHPChartsRadialDataSet(segments: [], total: 0)
        }

        var seenIDs: Set<String> = []
        var cumulative = 0.0
        var segments: [NativePHPChartsRadialSegment] = []

        for (index, wire) in decoded.enumerated() {
            guard !wire.id.isEmpty,
                  !seenIDs.contains(wire.id),
                  wire.value.isFinite,
                  wire.value >= 0
            else {
                continue
            }

            let upperBound = cumulative + wire.value
            guard upperBound.isFinite else { continue }

            seenIDs.insert(wire.id)
            let lowerBound = cumulative
            cumulative = upperBound
            segments.append(
                NativePHPChartsRadialSegment(
                    id: wire.id,
                    label: wire.label,
                    value: wire.value,
                    colorValue: wire.color,
                    index: index,
                    lowerBound: lowerBound,
                    upperBound: cumulative
                )
            )
        }

        return NativePHPChartsRadialDataSet(segments: segments, total: cumulative)
    }
}
