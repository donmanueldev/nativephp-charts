import Foundation

enum NativePHPChartsTypography {
    static let minimumSpatialScale = 0.85
    static let maximumSpatialScale = 1.6

    static func spatialScale(_ preferredScale: Double) -> Double {
        min(max(preferredScale, minimumSpatialScale), maximumSpatialScale)
    }
}
