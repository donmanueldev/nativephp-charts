import XCTest
@testable import NativePHPChartsRendererCompileHarness

final class NativePHPChartsViewportInteractionTests: XCTestCase {
    func testPanMovesTheVisibleDomainAndReportsPan() throws {
        var state = NativePHPChartsViewportInteraction.State(domain: 20...40)
        state.updatePan(translation: -100)

        let domain = try XCTUnwrap(
            NativePHPChartsViewportInteraction.resolve(
                state: state,
                fullDomain: 0...100,
                axisLength: 200,
                configuredMinimumSpan: 5
            )
        )

        XCTAssertEqual(domain.lowerBound, 30, accuracy: 0.000_001)
        XCTAssertEqual(domain.upperBound, 50, accuracy: 0.000_001)
        XCTAssertEqual(state.reason, .pan)
    }

    func testZoomUsesTheFocalPointAndHonorsTheMinimumSpan() throws {
        var state = NativePHPChartsViewportInteraction.State(domain: 20...60)
        state.updateZoom(magnification: 20, focalFraction: 0.25)

        let domain = try XCTUnwrap(
            NativePHPChartsViewportInteraction.resolve(
                state: state,
                fullDomain: 0...100,
                axisLength: 300,
                configuredMinimumSpan: 8
            )
        )

        XCTAssertEqual(domain.lowerBound, 28, accuracy: 0.000_001)
        XCTAssertEqual(domain.upperBound, 36, accuracy: 0.000_001)
        XCTAssertEqual(state.reason, .zoom)
    }

    func testCombinedGestureReportsPanZoomOnceBothInputsMove() throws {
        var state = NativePHPChartsViewportInteraction.State(domain: 20...60)
        state.updatePan(translation: 30)
        state.updateZoom(magnification: 2, focalFraction: 0.75)

        let domain = try XCTUnwrap(
            NativePHPChartsViewportInteraction.resolve(
                state: state,
                fullDomain: 0...100,
                axisLength: 300,
                configuredMinimumSpan: 5
            )
        )

        XCTAssertEqual(domain.lowerBound, 33, accuracy: 0.000_001)
        XCTAssertEqual(domain.upperBound, 53, accuracy: 0.000_001)
        XCTAssertEqual(state.reason, .panZoom)
    }

    func testClampingPreservesSpanAtBothDomainEdges() throws {
        var state = NativePHPChartsViewportInteraction.State(domain: 10...30)
        state.updatePan(translation: 500)

        let lowerEdge = try XCTUnwrap(
            NativePHPChartsViewportInteraction.resolve(
                state: state,
                fullDomain: 0...100,
                axisLength: 200,
                configuredMinimumSpan: nil
            )
        )

        state.updatePan(translation: -1_000)
        let upperEdge = try XCTUnwrap(
            NativePHPChartsViewportInteraction.resolve(
                state: state,
                fullDomain: 0...100,
                axisLength: 200,
                configuredMinimumSpan: nil
            )
        )

        XCTAssertEqual(lowerEdge, 0...20)
        XCTAssertEqual(upperEdge, 80...100)
    }

    func testStationaryRecognizersDoNotInventAViewportReason() {
        var state = NativePHPChartsViewportInteraction.State(domain: 20...40)
        state.updatePan(translation: 0)
        state.updateZoom(magnification: 1, focalFraction: 0.5)

        XCTAssertNil(state.reason)
    }

    func testHorizontalAxisReversesPhysicalGestureCoordinates() {
        XCTAssertEqual(
            NativePHPChartsViewportInteraction.logicalTranslation(40, reversed: true),
            -40
        )
        XCTAssertEqual(
            NativePHPChartsViewportInteraction.logicalFraction(0.2, reversed: true),
            0.8,
            accuracy: 0.000_001
        )
        XCTAssertEqual(
            NativePHPChartsViewportInteraction.logicalTranslation(40, reversed: false),
            40
        )
        XCTAssertEqual(
            NativePHPChartsViewportInteraction.logicalFraction(0.2, reversed: false),
            0.2,
            accuracy: 0.000_001
        )
    }

    func testHorizontalAxisPanAndZoomResolveInLogicalDomainCoordinates() throws {
        var state = NativePHPChartsViewportInteraction.State(domain: 20...60)
        state.updatePan(
            translation: NativePHPChartsViewportInteraction.logicalTranslation(30, reversed: true)
        )
        state.updateZoom(
            magnification: 2,
            focalFraction: NativePHPChartsViewportInteraction.logicalFraction(0.25, reversed: true)
        )

        let domain = try XCTUnwrap(
            NativePHPChartsViewportInteraction.resolve(
                state: state,
                fullDomain: 0...100,
                axisLength: 200,
                configuredMinimumSpan: nil
            )
        )
        XCTAssertEqual(domain.lowerBound, 38, accuracy: 0.000_001)
        XCTAssertEqual(domain.upperBound, 58, accuracy: 0.000_001)
    }

    func testInvalidGeometryDoesNotProduceADomain() {
        let state = NativePHPChartsViewportInteraction.State(domain: 20...40)

        XCTAssertNil(
            NativePHPChartsViewportInteraction.resolve(
                state: state,
                fullDomain: 0...100,
                axisLength: 0,
                configuredMinimumSpan: nil
            )
        )
    }
}
