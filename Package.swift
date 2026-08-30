// swift-tools-version: 6.0

import PackageDescription

let package = Package(
    name: "NativePHPChartsNativeTests",
    platforms: [.macOS(.v14)],
    products: [
        .library(
            name: "NativePHPChartsRendererCompileHarness",
            targets: ["NativePHPChartsRendererCompileHarness"]
        ),
    ],
    targets: [
        .target(
            name: "NativePHPChartsRendererCompileHarness",
            path: "resources/ios",
            exclude: ["Tests"],
            sources: ["Sources", "TestSupport"]
        ),
        .testTarget(
            name: "NativePHPChartsViewportCoreTests",
            dependencies: ["NativePHPChartsRendererCompileHarness"],
            path: "resources/ios/Tests"
        ),
    ]
)
