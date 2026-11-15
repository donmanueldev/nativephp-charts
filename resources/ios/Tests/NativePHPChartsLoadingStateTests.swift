import SwiftUI
import XCTest
@testable import NativePHPChartsRendererCompileHarness

/// `ImageRenderer` never runs `.task`, so it captures exactly the frame shown before the first
/// snapshot loads. That frame must be the neutral placeholder, never a "No data" empty state.
@MainActor
final class NativePHPChartsLoadingStateTests: XCTestCase {
    func testFirstFrameBeforeLoadingDrawsNothing() throws {
        let node = NativeUINode()
        let renderers: [(String, AnyView)] = [
            ("cartesian", AnyView(NativePHPChartsRenderer(node: node, kind: .line))),
            ("radial", AnyView(NativePHPChartsRadialRenderer(node: node, kind: .pie))),
            ("radar", AnyView(NativePHPChartsRadarChartRenderer(node: node))),
            ("progress", AnyView(NativePHPChartsProgressChartRenderer(node: node))),
            ("heatmap", AnyView(NativePHPChartsContributionHeatmapRenderer(node: node))),
        ]

        for (name, view) in renderers {
            XCTAssertEqual(try opaquePixelCount(view), 0, "\(name) drew content before its snapshot loaded")
        }
    }

    private func opaquePixelCount(_ view: AnyView) throws -> Int {
        let renderer = ImageRenderer(content: view.frame(width: 240, height: 200))
        renderer.scale = 1
        let image = try XCTUnwrap(renderer.cgImage)
        var pixels = [UInt8](repeating: 0, count: image.width * image.height * 4)
        let context = try XCTUnwrap(CGContext(
            data: &pixels,
            width: image.width,
            height: image.height,
            bitsPerComponent: 8,
            bytesPerRow: image.width * 4,
            space: CGColorSpaceCreateDeviceRGB(),
            bitmapInfo: CGImageAlphaInfo.premultipliedLast.rawValue
        ))
        context.draw(image, in: CGRect(x: 0, y: 0, width: image.width, height: image.height))
        return stride(from: 3, to: pixels.count, by: 4).filter { pixels[$0] > 0 }.count
    }
}
