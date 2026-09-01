import Foundation

enum NativePHPChartsViewportReason: String, Encodable, Equatable {
    case pan
    case zoom
    case panZoom = "pan_zoom"
}

struct NativePHPChartsViewportInteraction {
    struct State: Equatable {
        let initialDomain: ClosedRange<Double>
        var latestDomain: ClosedRange<Double>
        var panTranslation = 0.0
        var magnification = 1.0
        var focalFraction = 0.5
        var didPan = false
        var didZoom = false

        init(domain: ClosedRange<Double>) {
            initialDomain = domain
            latestDomain = domain
        }

        mutating func updatePan(translation: Double) {
            panTranslation = translation
            didPan = didPan || abs(translation) > 0.000_001
        }

        mutating func updateZoom(magnification: Double, focalFraction: Double) {
            self.magnification = max(magnification, 0.000_001)
            self.focalFraction = NativePHPChartsViewportInteraction.clamp(
                focalFraction,
                to: 0...1
            )
            didZoom = didZoom || abs(magnification - 1) > 0.000_001
        }

        var reason: NativePHPChartsViewportReason? {
            if didPan, didZoom {
                return .panZoom
            }
            if didZoom {
                return .zoom
            }
            if didPan {
                return .pan
            }

            return nil
        }
    }

    static func resolve(
        state: State,
        fullDomain: ClosedRange<Double>,
        axisLength: Double,
        configuredMinimumSpan: Double?
    ) -> ClosedRange<Double>? {
        let fullSpan = fullDomain.upperBound - fullDomain.lowerBound
        let initialSpan = state.initialDomain.upperBound - state.initialDomain.lowerBound
        guard fullSpan > 0, initialSpan > 0, axisLength > 0 else {
            return nil
        }

        let fallbackMinimumSpan = max(fullSpan / 1_000, 0.000_001)
        let minimumSpan = min(
            max(configuredMinimumSpan ?? fallbackMinimumSpan, 0.000_001),
            fullSpan
        )
        let span = clamp(initialSpan / state.magnification, to: minimumSpan...fullSpan)
        let anchor = state.initialDomain.lowerBound + (initialSpan * state.focalFraction)
        let shift = -(state.panTranslation / axisLength) * span
        let lower = anchor - (span * state.focalFraction) + shift

        return clamped(lower...(lower + span), to: fullDomain)
    }

    static func logicalTranslation(_ physicalTranslation: Double, reversed: Bool) -> Double {
        reversed ? -physicalTranslation : physicalTranslation
    }

    static func logicalFraction(_ physicalFraction: Double, reversed: Bool) -> Double {
        let fraction = clamp(physicalFraction, to: 0...1)
        return reversed ? 1 - fraction : fraction
    }

    private static func clamped(
        _ range: ClosedRange<Double>,
        to fullDomain: ClosedRange<Double>
    ) -> ClosedRange<Double> {
        let fullSpan = fullDomain.upperBound - fullDomain.lowerBound
        guard fullSpan > 0 else {
            return fullDomain
        }

        let span = min(range.upperBound - range.lowerBound, fullSpan)
        var lower = range.lowerBound
        var upper = range.upperBound

        if lower < fullDomain.lowerBound {
            lower = fullDomain.lowerBound
            upper = lower + span
        }
        if upper > fullDomain.upperBound {
            upper = fullDomain.upperBound
            lower = upper - span
        }

        return lower...upper
    }

    private static func clamp(
        _ value: Double,
        to range: ClosedRange<Double>
    ) -> Double {
        min(max(value, range.lowerBound), range.upperBound)
    }
}
