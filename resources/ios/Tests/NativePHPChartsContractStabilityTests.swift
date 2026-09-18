import Foundation
import Testing
@testable import NativePHPChartsRendererCompileHarness

struct NativePHPChartsContractStabilityTests {
    @Test("Cartesian rejects unsupported contract versions")
    func cartesianContractVersion() {
        let snapshot = NativePHPChartsSnapshot(
            input: .testing(contractVersion: 2, seriesJSON: "[]"),
            kind: .line
        )

        #expect(snapshot.availability == .unsupportedContract)
        #expect(snapshot.data.isEmpty)
    }

    @Test("Cartesian distinguishes malformed payloads from empty data")
    func cartesianMalformedVersusEmpty() {
        let empty = NativePHPChartsSnapshot(input: .testing(seriesJSON: "[]"), kind: .line)
        let malformed = NativePHPChartsSnapshot(input: .testing(seriesJSON: "{"), kind: .line)

        #expect(empty.availability == .available)
        #expect(empty.data.isEmpty)
        #expect(malformed.availability == .invalidPayload)
    }

    @Test("Radial rejects an entire snapshot when one segment is invalid")
    func radialWholeSnapshotRejection() {
        let snapshot = NativePHPChartsRadialSnapshot(
            input: .testing(segmentsJSON: """
            [
              {"id":"ok","label":"OK","value":1,"color":"#000000"},
              {"id":"bad","label":"Bad","value":-1,"color":"#000000"}
            ]
            """),
            kind: .pie
        )

        #expect(snapshot.availability == .invalidPayload)
        #expect(snapshot.data.segments.isEmpty)
    }

    @Test("Radar rejects misaligned series instead of rendering a partial polygon")
    func radarWholeSnapshotRejection() {
        let input = NativePHPChartsRadarWireInput(
            axesJSON: """
            [{"id":"a","label":"A","maximum":10},{"id":"b","label":"B","maximum":10},{"id":"c","label":"C","maximum":10}]
            """,
            seriesJSON: """
            [{"id":"s","name":"S","color":"#000000","values":[{"axis":"a","value":1},{"axis":"wrong","value":2},{"axis":"c","value":3}]}]
            """
        )

        let snapshot = NativePHPChartsRadarSnapshot(input: input)
        #expect(snapshot.availability == .invalidPayload)
        #expect(snapshot.selections.isEmpty)
    }

    @Test("Explicit point identity survives reordering and disappears on deletion")
    func cartesianSelectionMutation() throws {
        let first = NativePHPChartsSnapshot(input: .testing(seriesJSON: series(points: [("a", 1), ("b", 2)])), kind: .line)
        let selectionID = try #require(first.data.points.last).selectionID
        let reordered = NativePHPChartsSnapshot(input: .testing(seriesJSON: series(points: [("b", 3), ("a", 4)])), kind: .line)
        let removed = NativePHPChartsSnapshot(input: .testing(seriesJSON: series(points: [("a", 4)])), kind: .line)

        #expect(reordered.data.point(selectionID: selectionID)?.id == "b")
        #expect(removed.data.point(selectionID: selectionID) == nil)
    }

    @Test("Radial selection identity survives updates and clears after removal")
    func radialSelectionMutation() {
        let updated = NativePHPChartsRadialSnapshot(input: .testing(segmentsJSON: """
        [{"id":"b","label":"B","value":8},{"id":"a","label":"A","value":1}]
        """), kind: .donut)
        let removed = NativePHPChartsRadialSnapshot(input: .testing(segmentsJSON: """
        [{"id":"a","label":"A","value":1}]
        """), kind: .donut)

        #expect(updated.data.segment(id: "b")?.value == 8)
        #expect(removed.data.segment(id: "b") == nil)
    }

    @Test("Missing explicit colors safely fall back when a supplied palette is empty")
    func emptyPaletteFallback() throws {
        let input = NativePHPChartsWireInput.testing(seriesJSON: series(points: [("a", 1)]))
        let configuration = NativePHPChartsConfiguration.decode(input, kind: .line)
        let formatter = NativePHPChartsFormatter(input: input, configuration: configuration)
        let cartesian = NativePHPChartsDataSet.decode(seriesJSON: series(points: [("a", 1)]), xAxis: configuration.xAxis, formatter: formatter, palette: [])
        let radial = NativePHPChartsRadialDataSet.decode("""
        [{"id":"a","label":"A","value":1}]
        """, palette: [])

        #expect(try #require(cartesian.series.first).colorValue.isEmpty == false)
        #expect(try #require(radial.segments.first).colorValue.isEmpty == false)
    }

    private func series(points: [(String, Double)]) -> String {
        let encoded = points.map { "{\"id\":\"\($0.0)\",\"label\":\"\($0.0)\",\"value\":\($0.1),\"x\":\"\($0.0)\"}" }.joined(separator: ",")
        return "[{\"id\":\"s\",\"name\":\"Series\",\"points\":[\(encoded)]}]"
    }
}
