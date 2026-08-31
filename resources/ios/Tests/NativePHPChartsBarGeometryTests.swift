import XCTest
@testable import NativePHPChartsRendererCompileHarness

final class NativePHPChartsBarGeometryTests: XCTestCase {
    func testGroupedBarsShareRenderedGeometryAcrossOrientations() {
        let data = makeDataSet(values: [[30], [50]])
        let stack = NativePHPChartsStackedGeometry(data: data)
        let first = data.series[0].points[0]
        let second = data.series[1].points[0]

        let firstVertical = geometry(
            for: first,
            data: data,
            baseline: 10,
            mode: .grouped,
            orientation: .vertical,
            stack: stack
        )
        let secondHorizontal = geometry(
            for: second,
            data: data,
            baseline: 10,
            mode: .grouped,
            orientation: .horizontal,
            stack: stack
        )

        XCTAssertEqual(firstVertical.valueBounds, 10...30)
        XCTAssertEqual(firstVertical.category, -0.18, accuracy: 0.000_001)
        XCTAssertEqual(firstVertical.anchor, NativePHPChartsPlottedPosition(x: -0.18, y: 20))
        XCTAssertEqual(secondHorizontal.valueBounds, 10...50)
        XCTAssertEqual(secondHorizontal.category, 0.18, accuracy: 0.000_001)
        XCTAssertEqual(secondHorizontal.anchor, NativePHPChartsPlottedPosition(x: 30, y: 0.18))
    }

    func testStackedBarsAnchorLaterPositiveAndNegativeSegmentsAtTheirRenderedMidpoints() {
        let data = makeDataSet(values: [[10, -6], [15, -8]])
        let stack = NativePHPChartsStackedGeometry(data: data)
        let positive = data.series[1].points[0]
        let negative = data.series[1].points[1]

        let vertical = geometry(
            for: positive,
            data: data,
            baseline: 0,
            mode: .stacked,
            orientation: .vertical,
            stack: stack
        )
        let horizontal = geometry(
            for: negative,
            data: data,
            baseline: 0,
            mode: .stacked,
            orientation: .horizontal,
            stack: stack
        )

        XCTAssertEqual(vertical.valueBounds, 10...25)
        XCTAssertEqual(vertical.anchor, NativePHPChartsPlottedPosition(x: 0, y: 17.5))
        XCTAssertEqual(horizontal.valueBounds, -14 ... -6)
        XCTAssertEqual(horizontal.anchor, NativePHPChartsPlottedPosition(x: -10, y: 1))
    }

    func testHorizontalBarsSearchCandidatesAlongThePhysicalYAxis() {
        XCTAssertEqual(
            NativePHPChartsSelection.candidateAxis(kind: .bar, barOrientation: .horizontal),
            .y
        )
        XCTAssertEqual(
            NativePHPChartsSelection.candidateAxis(kind: .bar, barOrientation: .vertical),
            .x
        )
        XCTAssertEqual(
            NativePHPChartsSelection.candidateAxis(kind: .line, barOrientation: .horizontal),
            .x
        )
    }

    func testGroupedBarPlotDomainAddsRoomForBarsAtViewportBoundaries() {
        let data = makeDataSet(values: [[30, 40], [50, 60]])
        let logicalViewport = 0.0...1.0
        let renderedDomain = data.xDomain(
            for: .bar,
            barMode: .grouped,
            fallback: logicalViewport
        )

        XCTAssertLessThan(renderedDomain.lowerBound, logicalViewport.lowerBound)
        XCTAssertGreaterThan(renderedDomain.upperBound, logicalViewport.upperBound)
    }

    func testLongBarSegmentsRemainSelectableNearBothRenderedEnds() {
        XCTAssertEqual(
            NativePHPChartsSelection.segmentDistance(
                from: CGPoint(x: 20, y: 25),
                start: CGPoint(x: 20, y: 20),
                end: CGPoint(x: 20, y: 300)
            ),
            0
        )
        XCTAssertEqual(
            NativePHPChartsSelection.segmentDistance(
                from: CGPoint(x: 20, y: 295),
                start: CGPoint(x: 20, y: 20),
                end: CGPoint(x: 20, y: 300)
            ),
            0
        )
        XCTAssertEqual(
            NativePHPChartsSelection.segmentDistance(
                from: CGPoint(x: 25, y: 40),
                start: CGPoint(x: 20, y: 40),
                end: CGPoint(x: 300, y: 40)
            ),
            0
        )
        XCTAssertEqual(
            NativePHPChartsSelection.segmentDistance(
                from: CGPoint(x: 295, y: 40),
                start: CGPoint(x: 20, y: 40),
                end: CGPoint(x: 300, y: 40)
            ),
            0
        )
        XCTAssertEqual(
            NativePHPChartsSelection.segmentDistance(
                from: CGPoint(x: 65, y: 300),
                start: CGPoint(x: 20, y: 20),
                end: CGPoint(x: 20, y: 300)
            ),
            45
        )
    }

    private func geometry(
        for point: NativePHPChartsPoint,
        data: NativePHPChartsDataSet,
        baseline: Double,
        mode: NativePHPChartsBarMode,
        orientation: NativePHPChartsBarOrientation,
        stack: NativePHPChartsStackedGeometry
    ) -> NativePHPChartsBarGeometry {
        NativePHPChartsBarGeometry.resolve(
            point: point,
            data: data,
            baseline: baseline,
            mode: mode,
            orientation: orientation,
            stackedGeometry: stack
        )
    }

    private func makeDataSet(values: [[Double]]) -> NativePHPChartsDataSet {
        let series = values.enumerated().map { seriesIndex, values in
            let points = values.enumerated().map { pointIndex, value in
                NativePHPChartsPoint(
                    id: "point-\(pointIndex)",
                    label: "Point \(pointIndex)",
                    value: value,
                    x: .string("Point \(pointIndex)"),
                    plotX: Double(pointIndex),
                    index: pointIndex,
                    seriesID: "series-\(seriesIndex)",
                    seriesIndex: seriesIndex,
                    errorMin: nil,
                    errorMax: nil,
                    open: nil,
                    high: nil,
                    low: nil,
                    close: nil
                )
            }

            return NativePHPChartsSeries(
                id: "series-\(seriesIndex)",
                name: "Series \(seriesIndex)",
                colorValue: "#000000",
                points: points,
                index: seriesIndex,
                style: nil,
                fillTo: nil
            )
        }

        return NativePHPChartsDataSet(series: series, xType: .category, categoryLabels: [:])
    }
}
