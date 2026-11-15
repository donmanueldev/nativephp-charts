import XCTest
@testable import NativePHPChartsRendererCompileHarness

final class NativePHPChartsTooltipPlacementTests: XCTestCase {
    private let plot = CGRect(x: 40, y: 0, width: 300, height: 200)

    func testWideTooltipIsClampedByItsRealHalfWidth() {
        // A 240-point shared tooltip needs 120 points on each side; the old fixed 110 clipped it.
        let x = NativePHPChartsTooltipPlacement.x(anchor: plot.minX, plotFrame: plot, tooltipWidth: 240)

        XCTAssertEqual(x - 120, plot.minX)
    }

    func testNarrowTooltipStaysCloseToItsAnchorNearTheEdge() {
        let x = NativePHPChartsTooltipPlacement.x(anchor: plot.maxX - 30, plotFrame: plot, tooltipWidth: 60)

        XCTAssertEqual(x, plot.maxX - 30)
    }

    func testUnmeasuredTooltipKeepsThePreviousVerticalOffsets() {
        XCTAssertEqual(NativePHPChartsTooltipPlacement.y(anchor: 100, plotFrame: plot, tooltipHeight: nil), 62)
        XCTAssertEqual(NativePHPChartsTooltipPlacement.y(anchor: 0, plotFrame: plot, tooltipHeight: nil), 30)
    }

    func testTallTooltipStaysInsideThePlot() {
        let y = NativePHPChartsTooltipPlacement.y(anchor: 10, plotFrame: plot, tooltipHeight: 60)

        XCTAssertGreaterThanOrEqual(y - 30, plot.minY)
    }

    func testOHLCComponentsKeepAbbreviationsAndOrder() throws {
        let input = NativePHPChartsWireInput(node: NativeUINode())
        let configuration = NativePHPChartsConfiguration.decode(input, kind: .candlestick)
        let formatter = NativePHPChartsFormatter(input: input, configuration: configuration)
        let point = NativePHPChartsPoint(
            id: "p", label: "Mon", value: 12, x: .string("Mon"), plotX: 0, index: 0,
            seriesID: "s", seriesIndex: 0, errorMin: nil, errorMax: nil,
            open: 10, high: 14, low: 8, close: 12
        )

        let components = try XCTUnwrap(NativePHPChartsCandlestickPresentation.components(for: point, formatter: formatter))

        XCTAssertEqual(components.map(\.abbreviation), ["O", "H", "L", "C"])
        XCTAssertEqual(components.map(\.value), ["10", "14", "8", "12"])
    }
}
