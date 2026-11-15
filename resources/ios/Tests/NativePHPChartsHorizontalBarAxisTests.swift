import XCTest
@testable import NativePHPChartsRendererCompileHarness

/// Guards the single physical-y convention shared by horizontal bar marks, scale, axis, and selection.
final class NativePHPChartsHorizontalBarAxisTests: XCTestCase {
    private let seriesJSON = """
    [
      {"id":"a","name":"A","points":[{"id":"p0","label":"Jan","value":4},{"id":"p1","label":"Feb","value":7},{"id":"p2","label":"Mar","value":2},{"id":"p3","label":"Apr","value":9}]},
      {"id":"b","name":"B","points":[{"id":"p0","label":"Jan","value":1},{"id":"p1","label":"Feb","value":3},{"id":"p2","label":"Mar","value":8},{"id":"p3","label":"Apr","value":5}]}
    ]
    """

    func testEveryHorizontalBarLiesInsideThePhysicalYScaleDomain() {
        let snapshot = horizontalSnapshot()
        let categoryDomain = snapshot.data.xDomain(for: .bar, barMode: .grouped, fallback: snapshot.domain.x)
        let physicalDomain = NativePHPChartsHorizontalBarAxis.physicalYDomain(categories: categoryDomain)

        XCTAssertEqual(snapshot.data.points.count, 8)
        for point in snapshot.data.points {
            let geometry = barGeometry(for: point, in: snapshot)
            let physicalY = NativePHPChartsHorizontalBarAxis.physicalY(category: geometry.category)
            XCTAssertTrue(physicalDomain.contains(physicalY), "\(point.selectionID) at \(physicalY) outside \(physicalDomain)")
            XCTAssertEqual(NativePHPChartsHorizontalBarAxis.physicalAnchor(geometry).y, physicalY)
        }
    }

    func testAxisTicksSitOnCategoryCentersInSourceOrderTopToBottom() {
        let snapshot = horizontalSnapshot()
        let ticks = snapshot.data.axisValues(desiredCount: 4)
            .map { NativePHPChartsHorizontalBarAxis.physicalY(category: $0) }

        XCTAssertEqual(ticks, snapshot.data.xValues.map { NativePHPChartsHorizontalBarAxis.physicalY(category: $0) })
        // Swift Charts draws larger y higher, so strictly decreasing ticks read top to bottom.
        XCTAssertEqual(ticks, ticks.sorted(by: >))
        XCTAssertEqual(
            ticks.map { snapshot.formatter.x(NativePHPChartsHorizontalBarAxis.category(physicalY: $0), data: snapshot.data) },
            ["Jan", "Feb", "Mar", "Apr"]
        )
    }

    func testPhysicalHitRangeMapsBackToLogicalCategories() {
        XCTAssertEqual(NativePHPChartsHorizontalBarAxis.categories(physicalY: -0.5, -1.5), 0.5...1.5)
        XCTAssertEqual(NativePHPChartsHorizontalBarAxis.categories(physicalY: -1.5, -0.5), 0.5...1.5)
    }

    func testScreenTopIsTheLogicalViewportStartSoGesturesNeedNoReversal() {
        let logical = 2.0...6.0
        let physical = NativePHPChartsHorizontalBarAxis.physicalYDomain(categories: logical)

        // The top of a Swift Charts plot shows the physical upper bound.
        XCTAssertEqual(NativePHPChartsHorizontalBarAxis.category(physicalY: physical.upperBound), logical.lowerBound)
        XCTAssertEqual(NativePHPChartsHorizontalBarAxis.category(physicalY: physical.lowerBound), logical.upperBound)
    }

    private func horizontalSnapshot() -> NativePHPChartsSnapshot {
        NativePHPChartsSnapshot(
            input: .testing(seriesJSON: seriesJSON, barOrientation: "horizontal"),
            kind: .bar
        )
    }

    private func barGeometry(for point: NativePHPChartsPoint, in snapshot: NativePHPChartsSnapshot) -> NativePHPChartsBarGeometry {
        snapshot.domain.barGeometry(
            for: point,
            data: snapshot.data,
            mode: snapshot.configuration.barMode,
            orientation: snapshot.configuration.barOrientation
        )
    }
}
