import Foundation
import Testing
@testable import NativePHPChartsRendererCompileHarness

struct NativePHPChartsSharedFixtureTests {
    @Test("Swift accepts the shared valid v1 fixture with an omitted color")
    func validV1() throws {
        let item = try #require(try fixtures()["valid_v1"] as? [String: Any])
        let version = try #require(item["contract_version"] as? Int)
        let json = try #require(item["series_json"] as? String)
        let snapshot = NativePHPChartsSnapshot(input: .testing(contractVersion: version, seriesJSON: json), kind: .line)

        #expect(snapshot.availability == .available)
        #expect(try #require(snapshot.data.series.first).colorValue.isEmpty == false)
    }

    @Test("Swift rejects the shared unsupported and corrupt fixtures")
    func rejectionFixtures() throws {
        let fixtures = try fixtures()
        let unsupported = try #require(fixtures["unsupported_version"] as? [String: Any])
        let corrupt = try #require(fixtures["corrupt_json"] as? [String: Any])

        let unsupportedSnapshot = NativePHPChartsSnapshot(
            input: .testing(contractVersion: try #require(unsupported["contract_version"] as? Int), seriesJSON: "[]"),
            kind: .line
        )
        let corruptSnapshot = NativePHPChartsSnapshot(
            input: .testing(contractVersion: try #require(corrupt["contract_version"] as? Int), seriesJSON: try #require(corrupt["series_json"] as? String)),
            kind: .line
        )

        #expect(unsupportedSnapshot.availability == .unsupportedContract)
        #expect(corruptSnapshot.availability == .invalidPayload)
    }

    @Test("Swift progress selection equals the shared fixture")
    func progressSelection() throws {
        let fixture = try #require(try fixtures()["progress_selection"] as? [String: Any])
        let input = try #require(fixture["input"] as? [String: Any])
        let metric = NativePHPChartsProgressMetric(
            id: try #require(input["id"] as? String),
            label: try #require(input["label"] as? String),
            value: try #require(input["value"] as? Double),
            sourceIndex: try #require(input["index"] as? Int)
        )

        try expectJSON(NativePHPChartsProgressSelection.payload(metric: metric).json(), equals: fixture["expected"])
    }

    @Test("Swift heatmap selection equals the shared fixture")
    func heatmapSelection() throws {
        let fixture = try #require(try fixtures()["heatmap_selection"] as? [String: Any])
        let input = try #require(fixture["input"] as? [String: Any])
        let value = NativePHPChartsContributionValue(
            id: try #require(input["id"] as? String),
            date: try #require(input["date"] as? String),
            value: try #require(input["value"] as? Double),
            label: try #require(input["label"] as? String),
            sourceIndex: try #require(input["index"] as? Int)
        )

        try expectJSON(NativePHPChartsContributionSelection.payload(value: value).json(), equals: fixture["expected"])
    }

    private func fixtures() throws -> [String: Any] {
        let root = URL(fileURLWithPath: #filePath)
            .deletingLastPathComponent()
            .deletingLastPathComponent()
            .deletingLastPathComponent()
            .deletingLastPathComponent()
        let data = try Data(contentsOf: root.appendingPathComponent("fixtures/contracts/chart_contract_fixtures.json"))
        return try #require(JSONSerialization.jsonObject(with: data) as? [String: Any])
    }

    private func expectJSON(_ json: String?, equals expectedValue: Any?) throws {
        let json = try #require(json)
        let actual = try #require(JSONSerialization.jsonObject(with: Data(json.utf8)) as? NSDictionary)
        let expected = try #require(expectedValue as? NSDictionary)
        #expect(actual == expected)
    }
}
