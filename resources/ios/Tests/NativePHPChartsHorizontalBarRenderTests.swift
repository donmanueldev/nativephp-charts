import AppKit
import SwiftUI
import XCTest
@testable import NativePHPChartsRendererCompileHarness

/// Renders real Swift Charts output to catch sign-convention drift between marks and scales.
@MainActor
final class NativePHPChartsHorizontalBarRenderTests: XCTestCase {
    func testHorizontalBarsRenderEveryCategory() throws {
        let snapshot = NativePHPChartsSnapshot(
            input: .testing(
                seriesJSON: """
                [{"id":"s","name":"Sales","points":[
                  {"id":"a","label":"A","value":10},
                  {"id":"b","label":"B","value":20},
                  {"id":"c","label":"C","value":30}
                ]}]
                """,
                barOrientation: "horizontal",
                animated: false
            ),
            kind: .bar
        )
        XCTAssertEqual(snapshot.availability, .available)

        let bands = try barBands(in: snapshot)

        XCTAssertEqual(bands.count, 3, "every category must be drawn, got bands \(bands)")
        // Source order top to bottom: A (10) is shortest, C (30) is longest.
        if bands.count == 3 {
            XCTAssertLessThan(bands[0].width, bands[1].width)
            XCTAssertLessThan(bands[1].width, bands[2].width)
        }
    }

    /// Returns each vertical run of rows that contain bar-colored pixels, top to bottom, with its widest run.
    private func barBands(in snapshot: NativePHPChartsSnapshot) throws -> [(top: Int, width: Int)] {
        let view = NativePHPChartsPlot(
            nodeID: 1,
            kind: .bar,
            snapshot: snapshot,
            selectedPointID: .constant(nil)
        )
        .frame(width: 320, height: 240)
        .environment(\.colorScheme, .light)

        let renderer = ImageRenderer(content: view)
        renderer.scale = 1
        let image = try XCTUnwrap(renderer.cgImage)
        let width = image.width
        let height = image.height
        var pixels = [UInt8](repeating: 0, count: width * height * 4)
        let context = try XCTUnwrap(CGContext(
            data: &pixels,
            width: width,
            height: height,
            bitsPerComponent: 8,
            bytesPerRow: width * 4,
            space: CGColorSpaceCreateDeviceRGB(),
            bitmapInfo: CGImageAlphaInfo.premultipliedLast.rawValue
        ))
        context.draw(image, in: CGRect(x: 0, y: 0, width: width, height: height))

        // Bitmap memory row 0 is the visual top row.
        var bands: [(top: Int, width: Int)] = []
        var inBand = false
        for row in 0..<height {
            var barCount = 0
            for column in 0..<width {
                let offset = (row * width + column) * 4
                let red = pixels[offset], green = pixels[offset + 1], blue = pixels[offset + 2]
                // The host stub parses every color to the indigo fallback #6366F1.
                if blue > 200, red < 140, green < 140 { barCount += 1 }
            }
            if barCount > 0 {
                if !inBand {
                    bands.append((top: row, width: barCount))
                    inBand = true
                } else if let last = bands.last, barCount > last.width {
                    bands[bands.count - 1].width = barCount
                }
            } else {
                inBand = false
            }
        }
        return bands
    }
}
