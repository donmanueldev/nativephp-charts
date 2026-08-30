import XCTest
@testable import NativePHPChartsRendererCompileHarness

final class NativePHPChartsAxisValuesTests: XCTestCase {
    func testAxisLabelsUseTheRequestedCountInsideTheVisibleViewport() {
        let data = makeDataSet(xValues: Array(0...13).map(Double.init))

        let values = data.axisValues(desiredCount: 4, in: 3...9)

        XCTAssertEqual(values, [3, 5, 7, 9])
    }

    func testAxisLabelsKeepAllVisibleValuesWhenTheViewportHasFewerValuesThanRequested() {
        let data = makeDataSet(xValues: [0, 10, 20])

        let values = data.axisValues(desiredCount: 5, in: 10...20)

        XCTAssertEqual(values, [10, 20])
    }

    private func makeDataSet(xValues: [Double]) -> NativePHPChartsDataSet {
        let points = xValues.enumerated().map { index, x in
            NativePHPChartsPoint(
                id: "point-\(index)",
                label: "Point \(index)",
                value: Double(index),
                x: .number(x),
                plotX: x,
                index: index,
                seriesID: "series",
                seriesIndex: 0,
                errorMin: nil,
                errorMax: nil,
                open: nil,
                high: nil,
                low: nil,
                close: nil
            )
        }
        let series = NativePHPChartsSeries(
            id: "series",
            name: "Series",
            colorValue: "#000000",
            points: points,
            index: 0,
            style: nil,
            fillTo: nil
        )

        return NativePHPChartsDataSet(
            series: [series],
            xType: .number,
            categoryLabels: [:]
        )
    }
}
