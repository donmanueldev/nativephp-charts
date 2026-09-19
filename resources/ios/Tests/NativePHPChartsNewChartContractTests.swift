import Foundation
import Testing
@testable import NativePHPChartsRendererCompileHarness

struct NativePHPChartsNewChartContractTests {
    @Test("Progress requires stable ids and values in the unit interval")
    func progressValidation() throws {
        let valid = NativePHPChartsProgressSnapshot(input: .testing(metricsJSON: """
        [{"id":"delivery","label":"Delivery","value":0.75,"color":"#22C55E"}]
        """))
        let invalid = NativePHPChartsProgressSnapshot(input: .testing(metricsJSON: """
        [{"id":"delivery","label":"Delivery","value":1.2}]
        """))

        #expect(valid.availability == .available)
        #expect(try #require(valid.metrics.first).id == "delivery")
        #expect(invalid.availability == .invalidPayload)
    }

    @Test("Contribution heatmap validates dates, ids, values, and source order")
    func heatmapValidation() throws {
        let snapshot = NativePHPChartsContributionSnapshot(input: .testing(valuesJSON: """
        [
          {"id":"day-1","date":"2026-03-01","value":2,"label":"Two"},
          {"id":"day-2","date":"2026-03-02","value":4}
        ]
        """))

        #expect(snapshot.availability == .available)
        #expect(snapshot.values.map(\.id) == ["day-1", "day-2"])
        #expect(snapshot.values.map(\.sourceIndex) == [0, 1])
    }

    @Test("New chart callbacks retain the PointSelection v1 wire shape", arguments: [
        ("progress", "delivery", "delivery", "Delivery", 0.75),
        ("contribution_heatmap", "day-1", "day-1", "2026-03-01", 2.0),
    ])
    func selectionPayload(_ chartType: String, _ seriesID: String, _ pointID: String, _ label: String, _ value: Double) throws {
        let json = try #require(NativePHPChartsSelectionPayload(
            chartType: chartType,
            seriesID: seriesID,
            seriesName: seriesID,
            pointID: pointID,
            pointIndex: 0,
            xType: "category",
            x: .string(pointID),
            label: label,
            value: value,
            localizedValue: String(value)
        ).json())
        let object = try #require(JSONSerialization.jsonObject(with: Data(json.utf8)) as? [String: Any])

        #expect(object["version"] as? Int == 1)
        #expect(object["chart_type"] as? String == chartType)
        #expect(object["point_id"] as? String == pointID)
    }

    @Test("Progress payload repeats stable metric identity and presentation label")
    func progressSelectionSemantics() throws {
        let snapshot = NativePHPChartsProgressSnapshot(input: .testing(metricsJSON: """
        [{"id":"delivery","label":"Delivery","value":0.75}]
        """))
        let metric = try #require(snapshot.metrics.first)
        let json = try #require(NativePHPChartsProgressSelection.payload(metric: metric).json())
        let object = try #require(JSONSerialization.jsonObject(with: Data(json.utf8)) as? [String: Any])

        #expect(object["series_id"] as? String == "delivery")
        #expect(object["point_id"] as? String == "delivery")
        #expect(object["series_name"] as? String == "Delivery")
        #expect(object["x"] as? String == "Delivery")
        #expect(object["label"] as? String == "Delivery")
    }
}
