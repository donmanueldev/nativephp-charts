import XCTest

@MainActor
final class NativePHPChartsUITests: XCTestCase {
    private var app: XCUIApplication!

    override func setUpWithError() throws {
        continueAfterFailure = false
        app = XCUIApplication()
        app.launchArguments += ["-AppleLanguages", "(en)", "-AppleLocale", "en_US"]
        app.launch()
        XCTAssertTrue(chart.waitForExistence(timeout: 30), "The generated NativePHP shell did not expose the chart semantics.")

        let interactiveFrame = chart.frame
        XCTAssertGreaterThan(
            interactiveFrame.width,
            100,
            "The chart accessibility representation must preserve the full interactive width."
        )
        XCTAssertGreaterThan(
            interactiveFrame.height,
            100,
            "The chart accessibility representation must preserve the full interactive height."
        )
    }

    override func tearDownWithError() throws {
        app.terminate()
        app = nil
    }

    func testTapMutationDeletionAndEmptyStatePreserveStableIdentity() throws {
        selectCenterPoint(expectedPrefix: "Selection callbacks: 1; revenue; mar; Mar;")
        assertText(prefix: "Selection callbacks: 1; revenue; mar; Mar;")

        tapButton("Insert reorder and update")
        assertChartValue(
            NSPredicate(format: "value CONTAINS %@", "Revenue, Mar, 9"),
            message: "Stable point identity should survive insertion, reordering, and value updates."
        )

        tapButton("Delete selected point")
        assertChartValue(
            NSPredicate(format: "NOT (value CONTAINS %@)", "Revenue, Mar, 9"),
            message: "Deleting the selected point must clear native selection."
        )

        tapButton("Empty dataset")
        assertChartValue(
            NSPredicate(format: "value == %@", "No data"),
            message: "Emptying the fixture must publish native empty-state semantics."
        )
    }

    func testScrubEmitsOnlyOneCallbackWhenGestureCompletes() throws {
        tapButton("Use scrub")
        plotCoordinate().press(forDuration: 0.3)

        assertText(prefix: "Selection callbacks: 1;")
        XCTAssertFalse(anyText(prefix: "Selection callbacks: 2;").exists)
    }

    func testPanAndPinchEachEmitOneSettledViewportCallback() throws {
        tapButton("Use viewport")
        plotCoordinate(horizontalOffset: 90).press(
            forDuration: 0.05,
            thenDragTo: plotCoordinate(horizontalOffset: -90),
            withVelocity: .slow,
            thenHoldForDuration: 0
        )
        assertText(prefix: "Viewport callbacks: 1; pan;")

        tapButton("Use viewport")
        chart.pinch(withScale: 1.6, velocity: 1.0)
        assertText(prefix: "Viewport callbacks: 1; zoom;")
        XCTAssertFalse(anyText(prefix: "Viewport callbacks: 2;").exists)
    }

    func testVerticalSwipeScrollsParentWithoutSelectingPoint() throws {
        let start = plotCoordinate(verticalOffset: 30)
        let end = app.coordinate(withNormalizedOffset: CGVector(dx: 0.50, dy: 0.18))
        start.press(forDuration: 0.05, thenDragTo: end, withVelocity: .slow, thenHoldForDuration: 0)

        let sentinel = anyText(exact: "Scroll sentinel")
        XCTAssertTrue(sentinel.waitForExistence(timeout: 10))
        sentinel.tap()
        XCTAssertTrue(anyText(exact: "Selection callbacks: 0").exists)
        XCTAssertTrue(anyText(exact: "Viewport callbacks: 0").exists)
    }

    func testChartCanCloseAndReopenWithoutDuplicatingCallbacks() throws {
        scrollToButton("Toggle chart")
        tapButton("Toggle chart")
        XCTAssertTrue(anyText(exact: "Chart closed").waitForExistence(timeout: 10))

        tapButton("Toggle chart")
        XCTAssertTrue(chart.waitForExistence(timeout: 10))
        selectCenterPoint(expectedPrefix: "Selection callbacks: 1;")

        assertText(prefix: "Selection callbacks: 1;")
        XCTAssertFalse(anyText(prefix: "Selection callbacks: 2;").exists)
    }

    func testPerformanceHarnessCoversEveryDensityWithoutPresentationCallbacks() throws {
        openPerformanceHarness()
        assertPerformanceDensity(100)

        tapButton("1,000 points")
        assertPerformanceDensity(1_000)

        tapButton("10,000 points")
        assertPerformanceDensity(10_000, timeout: 60)
    }

    func testGalleryLineChart() throws { try verifyGalleryChart("line", index: 0) }
    func testGalleryAreaChart() throws { try verifyGalleryChart("area", index: 1) }
    func testGalleryBarChart() throws { try verifyGalleryChart("bar", index: 2) }
    func testGalleryScatterChart() throws { try verifyGalleryChart("scatter", index: 3) }
    func testGalleryPieChart() throws { try verifyGalleryChart("pie", index: 4) }
    func testGalleryDonutChart() throws { try verifyGalleryChart("donut", index: 5) }
    func testGalleryRadarChart() throws { try verifyGalleryChart("radar", index: 6) }
    func testGalleryCandlestickChart() throws { try verifyGalleryChart("candlestick", index: 7) }
    func testGalleryProgressChart() throws { try verifyGalleryChart("progress", index: 8) }
    func testGalleryContributionHeatmapChart() throws { try verifyGalleryChart("contribution heatmap", index: 9) }

    private var chart: XCUIElement {
        app.descendants(matching: .any)
            .matching(NSPredicate(format: "label == %@", "iOS behavior chart"))
            .firstMatch
    }

    private var chartValue: String {
        chart.value as? String ?? ""
    }

    private func selectCenterPoint(expectedPrefix: String) {
        chart.tap()
        XCTAssertTrue(
            anyText(prefix: expectedPrefix).waitForExistence(timeout: 5),
            "The chart center did not select the expected stable point: \(expectedPrefix)"
        )
    }

    private func plotCoordinate(
        horizontalOffset: CGFloat = 0,
        verticalOffset: CGFloat = -30
    ) -> XCUICoordinate {
        let applicationFrame = app.frame
        let target = CGPoint(
            x: chart.frame.midX + horizontalOffset,
            y: chart.frame.midY + verticalOffset
        )

        return app.coordinate(withNormalizedOffset: CGVector(
            dx: target.x / applicationFrame.width,
            dy: target.y / applicationFrame.height
        ))
    }

    private func galleryChart(_ chartType: String) -> XCUIElement {
        app.descendants(matching: .any)
            .matching(NSPredicate(format: "label == %@", "iOS gallery \(chartType) chart"))
            .firstMatch
    }

    private func verifyGalleryChart(_ chartType: String, index: Int) throws {
        openGallery()

        let next = app.buttons["Next chart"]
        let chartOrder = ["line", "area", "bar", "scatter", "pie", "donut", "radar", "candlestick", "progress", "contribution heatmap"]
        if index > 0 {
            for position in 1...index {
                let targetChart = galleryChart(chartOrder[position])
                for _ in 0..<3 {
                    XCTAssertTrue(next.waitForExistence(timeout: 10), "Missing gallery navigation for \(chartType)")
                    next.coordinate(withNormalizedOffset: CGVector(dx: 0.50, dy: 0.50)).tap()
                    if targetChart.waitForExistence(timeout: 10) {
                        break
                    }
                }
                XCTAssertTrue(
                    targetChart.exists,
                    "Gallery did not settle on \(chartOrder[position]) while advancing to \(chartType)"
                )
            }
        }

        let chart = galleryChart(chartType)
        XCTAssertTrue(chart.waitForExistence(timeout: 20), "Missing generated-shell gallery chart: \(chartType)")
        let attachmentName = chartType.replacingOccurrences(of: " ", with: "-")
        attachScreenshot(named: "nativephp-charts-ios-\(attachmentName)")

        if chartType == "line" {
            chart.tap()
        } else {
            gallerySelectionCoordinate(for: chartType).tap()
        }
        assertText(prefix: "Gallery selection 1: \(chartType.replacingOccurrences(of: " ", with: "_"));")
        XCTAssertFalse(anyText(prefix: "Gallery selection 2:").exists)
        attachScreenshot(named: "nativephp-charts-ios-\(attachmentName)-selected")
    }

    private func openGallery() {
        let button = app.buttons["Open chart gallery"]
        XCTAssertTrue(button.waitForExistence(timeout: 10), "Missing gallery navigation button")
        let next = app.buttons["Next chart"]

        for _ in 0..<5 {
            button.coordinate(withNormalizedOffset: CGVector(dx: 0.50, dy: 0.50)).tap()
            if next.waitForExistence(timeout: 5) {
                return
            }
        }

        XCTFail("Gallery navigation did not settle after five activation attempts")
    }

    private func openPerformanceHarness() {
        scrollToButton("Open performance harness")
        let button = app.buttons["Open performance harness"]
        XCTAssertTrue(button.waitForExistence(timeout: 10), "Missing performance harness navigation button")

        for _ in 0..<5 {
            button.coordinate(withNormalizedOffset: CGVector(dx: 0.50, dy: 0.50)).tap()
            if performanceChart(pointCount: 100).waitForExistence(timeout: 5) {
                return
            }
        }

        XCTFail("Performance harness navigation did not settle after five activation attempts")
    }

    private func performanceChart(pointCount: Int) -> XCUIElement {
        app.descendants(matching: .any)
            .matching(NSPredicate(format: "label == %@", "iOS performance line chart with \(pointCount) points"))
            .firstMatch
    }

    private func assertPerformanceDensity(_ pointCount: Int, timeout: TimeInterval = 20) {
        XCTAssertTrue(
            performanceChart(pointCount: pointCount).waitForExistence(timeout: timeout),
            "Missing generated-shell performance chart with \(pointCount) points"
        )
        XCTAssertTrue(
            anyText(exact: "Performance density: \(pointCount) points").waitForExistence(timeout: timeout),
            "The performance density marker did not match \(pointCount) points"
        )
        XCTAssertTrue(
            anyText(prefix: "Payload bytes: ").waitForExistence(timeout: timeout),
            "The performance harness did not expose payload bytes"
        )
        XCTAssertTrue(anyText(exact: "Performance callbacks: 0").exists)
    }

    private func gallerySelectionCoordinate(for chartType: String) -> XCUICoordinate {
        let offsets: [String: CGVector] = [
            "line": CGVector(dx: 0.50, dy: 0.50),
            "area": CGVector(dx: 0.87, dy: 0.29),
            "bar": CGVector(dx: 0.85, dy: 0.36),
            "scatter": CGVector(dx: 0.87, dy: 0.29),
            "pie": CGVector(dx: 0.70, dy: 0.46),
            "donut": CGVector(dx: 0.82, dy: 0.45),
            "radar": CGVector(dx: 0.50, dy: 0.31),
            "candlestick": CGVector(dx: 0.82, dy: 0.29),
            "progress": CGVector(dx: 0.75, dy: 0.45),
            "contribution heatmap": CGVector(dx: 0.50, dy: 0.29),
        ]

        return app.coordinate(withNormalizedOffset: offsets[chartType] ?? CGVector(dx: 0.50, dy: 0.45))
    }

    private func attachScreenshot(named name: String) {
        let attachment = XCTAttachment(screenshot: app.screenshot())
        attachment.name = name
        attachment.lifetime = .keepAlways
        add(attachment)
    }

    private func tapButton(_ label: String) {
        scrollToButton(label)
        let button = app.buttons[label]
        XCTAssertTrue(button.waitForExistence(timeout: 10), "Missing harness button: \(label)")
        button.tap()
    }

    private func scrollToButton(_ label: String) {
        let button = app.buttons[label]
        for _ in 0..<5 where !button.isHittable {
            app.swipeUp()
        }
    }

    private func assertText(prefix: String, timeout: TimeInterval = 15) {
        XCTAssertTrue(anyText(prefix: prefix).waitForExistence(timeout: timeout), "Missing text beginning with: \(prefix)")
    }

    private func assertChartValue(
        _ predicate: NSPredicate,
        message: String,
        timeout: TimeInterval = 15
    ) {
        let expectation = XCTNSPredicateExpectation(predicate: predicate, object: chart)
        XCTAssertEqual(XCTWaiter.wait(for: [expectation], timeout: timeout), .completed, message)
    }

    private func anyText(prefix: String) -> XCUIElement {
        app.staticTexts.matching(NSPredicate(format: "label BEGINSWITH %@", prefix)).firstMatch
    }

    private func anyText(exact text: String) -> XCUIElement {
        app.staticTexts.matching(NSPredicate(format: "label == %@", text)).firstMatch
    }
}
