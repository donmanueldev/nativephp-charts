import XCTest
@testable import NativePHPChartsRendererCompileHarness

final class NativePHPChartsCandlestickGeometryTests: XCTestCase {
    func testGeometryKeepsOHLCBoundsCloseAnchorAndContractualBarStyleTogether() throws {
        let point = makePoint(open: 10, high: 14, low: 8, close: 12)

        let geometry = try XCTUnwrap(
            NativePHPChartsCandlestickGeometry(
                point: point,
                x: 3,
                style: NativePHPChartsStyle.Bar(radius: 7, width: 18)
            )
        )

        XCTAssertEqual(geometry.wickBounds, 8...14)
        XCTAssertEqual(geometry.bodyBounds, 10...12)
        XCTAssertEqual(geometry.anchor, NativePHPChartsPlottedPosition(x: 3, y: 12))
        XCTAssertEqual(geometry.bodyWidth, .fixed(18))
        XCTAssertEqual(geometry.cornerRadius, 7)
    }

    func testGeometryUsesTheSameDefaultBodyRatioAsRenderingAndRejectsIncompleteOHLC() throws {
        let complete = makePoint(open: 12, high: 14, low: 8, close: 10)
        let incomplete = makePoint(open: 12, high: nil, low: 8, close: 10)

        let geometry = try XCTUnwrap(
            NativePHPChartsCandlestickGeometry(
                point: complete,
                x: 1,
                style: NativePHPChartsStyle.Bar()
            )
        )

        XCTAssertEqual(geometry.bodyBounds, 10...12)
        XCTAssertEqual(geometry.bodyWidth, .ratio(0.62))
        XCTAssertNil(
            NativePHPChartsCandlestickGeometry(
                point: incomplete,
                x: 1,
                style: NativePHPChartsStyle.Bar()
            )
        )
    }

    func testNeutralCandlesReserveAVIsibleBodyInsideTheirWick() throws {
        let point = makePoint(open: 10, high: 14, low: 8, close: 10)
        let geometry = try XCTUnwrap(NativePHPChartsCandlestickGeometry(point: point, x: 1, style: .init()))

        XCTAssertEqual(geometry.bodyBounds, 10...10)
        XCTAssertLessThan(geometry.renderedBodyBounds.lowerBound, 10)
        XCTAssertGreaterThan(geometry.renderedBodyBounds.upperBound, 10)
    }

    func testBodyAndWickDistancesSelectTheVisibleCandleInsteadOfOnlyItsClose() {
        XCTAssertEqual(
            NativePHPChartsSelection.rectangleDistance(
                from: CGPoint(x: 18, y: 100),
                to: CGRect(x: 10, y: 80, width: 16, height: 40)
            ),
            0
        )
        XCTAssertEqual(
            NativePHPChartsSelection.segmentDistance(
                from: CGPoint(x: 18, y: 25),
                start: CGPoint(x: 18, y: 20),
                end: CGPoint(x: 18, y: 140)
            ),
            0
        )
        XCTAssertEqual(
            NativePHPChartsSelection.candlestickCandidateRadius(bodyWidth: 200),
            144
        )
    }

    func testOHLCSelectionPayloadRemainsVersionOneWithCloseAsValue() throws {
        let point = makePoint(open: 10, high: 14, low: 8, close: 12)
        let payload = NativePHPChartsSelectionPayload(
            chartType: "candlestick",
            seriesID: point.seriesID,
            seriesName: "Market",
            pointID: point.id,
            pointIndex: point.index,
            xType: "category",
            x: .string("Day"),
            label: point.label,
            value: point.close!,
            localizedValue: "12"
        )

        let json = try XCTUnwrap(payload.json())
        let object = try XCTUnwrap(
            JSONSerialization.jsonObject(with: Data(json.utf8)) as? [String: Any]
        )

        XCTAssertEqual(object["version"] as? Int, 1)
        XCTAssertEqual(object["value"] as? Double, 12)
        XCTAssertNil(object["open"])
        XCTAssertNil(object["high"])
        XCTAssertNil(object["low"])
        XCTAssertNil(object["close"])
    }

    func testAccessiblePresentationIncludesEveryOHLCValue() throws {
        let input = NativePHPChartsWireInput(node: NativeUINode())
        let configuration = NativePHPChartsConfiguration.decode(input, kind: .candlestick)
        let formatter = NativePHPChartsFormatter(input: input, configuration: configuration)
        let point = makePoint(open: 10, high: 14, low: 8, close: 12)

        let value = try XCTUnwrap(
            NativePHPChartsCandlestickPresentation.values(for: point, formatter: formatter)
        )

        XCTAssertEqual(value, "O 10, H 14, L 8, C 12")
    }

    func testDataSetPrecomputesTheClosestXPairForConstantTimeBodyWidthResolution() {
        let points = [0.0, 10.0, 12.0].map { x in
            makePoint(open: 10, high: 14, low: 8, close: 12, plotX: x, id: "day-\(x)")
        }
        let series = NativePHPChartsSeries(
            id: "market",
            name: "Market",
            colorValue: "#000000",
            points: points,
            index: 0,
            style: nil,
            fillTo: nil
        )

        let data = NativePHPChartsDataSet(series: [series], xType: .number, categoryLabels: [:])

        XCTAssertEqual(data.minimumXGap, NativePHPChartsDataSet.XGap(lower: 10, upper: 12))
    }

    func testCandlestickStyleResolvesDirectionOverridesAndStableDefaults() throws {
        let global = NativePHPChartsStyle.Candlestick(
            risingColor: "#15803D",
            fallingColor: "#B91C1C",
            neutralColor: "#64748B",
            wickWidth: 2
        )
        let series = NativePHPChartsStyle.Candlestick(risingColor: "#166534", wickWidth: 2.5)
        let resolved = global.overriding(series)

        XCTAssertEqual(resolved.colorValue(open: 10, close: 12), "#166534")
        XCTAssertEqual(resolved.colorValue(open: 12, close: 10), "#B91C1C")
        XCTAssertEqual(resolved.colorValue(open: 10, close: 10), "#64748B")
        XCTAssertEqual(resolved.resolvedWickWidth, 2.5)
        XCTAssertEqual(NativePHPChartsStyle.Candlestick().colorValue(open: 10, close: 12), "#16A35B")
        XCTAssertEqual(NativePHPChartsStyle.Candlestick().colorValue(open: 12, close: 10), "#DB2E38")
        XCTAssertEqual(NativePHPChartsStyle.Candlestick().resolvedWickWidth, 1.5)
    }

    private func makePoint(
        open: Double?,
        high: Double?,
        low: Double?,
        close: Double?,
        plotX: Double = 0,
        id: String = "day"
    ) -> NativePHPChartsPoint {
        NativePHPChartsPoint(
            id: id,
            label: "Day",
            value: close ?? 0,
            x: .number(plotX),
            plotX: plotX,
            index: 0,
            seriesID: "market",
            seriesIndex: 0,
            errorMin: low,
            errorMax: high,
            open: open,
            high: high,
            low: low,
            close: close
        )
    }
}
