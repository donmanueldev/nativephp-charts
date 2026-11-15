import Testing
@testable import NativePHPChartsRendererCompileHarness

struct NativePHPChartsDecodedSeriesCacheTests {
    @Test("Concurrent loads of one payload decode consistently and malformed payloads return nil")
    func concurrentDecode() async throws {
        let json = #"[{"id":"cache-test-series","name":"S","points":[{"id":"a","label":"A","value":1},{"id":"b","label":"B","value":2}]}]"#
        let cache = NativePHPChartsDecodedSeriesCache()

        let results = await withTaskGroup(of: [NativePHPChartsWireSeries]?.self) { group in
            for _ in 0..<8 { group.addTask { await cache.series(for: json) } }
            return await group.reduce(into: []) { $0.append($1) }
        }

        #expect(results.count == 8)
        #expect(results.allSatisfy { $0?.first?.points.count == 2 })
        #expect(await cache.series(for: "{") == nil)
    }
}

struct NativePHPChartsSnapshotLoadTests {
    @Test("An unreadable file-v1 payload resolves to the unavailable state, not empty data")
    func missingFileIsUnavailable() async throws {
        let input = NativePHPChartsWireInput.testing(
            seriesJSON: "[]",
            seriesJSONFile: "/nonexistent/nativephp-charts-missing-series.json"
        )

        let snapshot = try #require(await NativePHPChartsSnapshot.load(input: input, kind: .line))

        #expect(snapshot.availability == .unavailable)
        #expect(snapshot.data.isEmpty)
    }
}
