import XCTest
@testable import NativePHPChartsRendererCompileHarness

final class NativePHPChartsTypographyTests: XCTestCase {
    func testSpatialScaleStillRespondsWithinReadableBounds() {
        XCTAssertEqual(NativePHPChartsTypography.spatialScale(0.5), 0.85)
        XCTAssertEqual(NativePHPChartsTypography.spatialScale(1.25), 1.25)
        XCTAssertEqual(NativePHPChartsTypography.spatialScale(3.5), 1.6)
    }
}
