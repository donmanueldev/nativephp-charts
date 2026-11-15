import CoreGraphics
import Testing
@testable import NativePHPChartsRendererCompileHarness

struct NativePHPChartsProgressBehaviorTests {
    @Test("Selection payload and accessibility share the chart locale and fraction digits")
    func localizedValueUsesChartFormatter() throws {
        let snapshot = NativePHPChartsProgressSnapshot(input: .testing(
            metricsJSON: #"[{"id":"delivery","label":"Delivery","value":0.755}]"#,
            locale: "de-DE",
            maximumFractionDigits: 1
        ))
        let metric = try #require(snapshot.metrics.first)
        let localized = snapshot.formatted(metric.value)
        let payload = NativePHPChartsProgressSelection.payload(metric: metric, localizedValue: localized)

        #expect(localized.contains("75,5"))
        #expect(payload.localizedValue == localized)
    }

    @Test("Progress colors fall back to the shared preset palette")
    func sharedPalette() {
        let spectrum = NativePHPChartsProgressSnapshot(input: .testing(metricsJSON: "[]", preset: "spectrum"))
        let contrast = NativePHPChartsProgressSnapshot(input: .testing(metricsJSON: "[]", preset: "contrast"))

        #expect(spectrum.palette == NativePHPChartsRuntime.palette(preset: "spectrum"))
        #expect(contrast.palette.prefix(4) == ["#0057B8", "#D1495B", "#00876C", "#7A5195"])
    }

    @Test("Up to four default rings keep their configured width and gap")
    func fewRingsUnchanged() {
        let layout = NativePHPChartsProgressRingLayout(count: 4, width: 12, gap: 8, size: CGSize(width: 240, height: 240))

        #expect(layout.ringWidth == 12)
        #expect(layout.ringStep == 20)
        #expect(layout.outerRadius == 114)
    }

    @Test("Twenty-four rings shrink to stay visible inside the chart")
    func manyRingsFit() {
        let layout = NativePHPChartsProgressRingLayout(count: 24, width: 12, gap: 8, size: CGSize(width: 240, height: 240))
        let innermostInnerEdge = layout.radius(ringAt: 23) - layout.ringWidth / 2

        #expect(layout.ringWidth < 12)
        #expect(layout.ringStep > layout.ringWidth)
        #expect(innermostInnerEdge >= 0)
        #expect(layout.innerRadius(count: 24) == innermostInnerEdge)
    }

    @Test("A few rings leave room for the center label while many rings do not")
    func centerLabelRoom() {
        let size = CGSize(width: 240, height: 240)
        let few = NativePHPChartsProgressRingLayout(count: 4, width: 12, gap: 8, size: size)
        let many = NativePHPChartsProgressRingLayout(count: 24, width: 12, gap: 8, size: size)

        #expect(few.innerRadius(count: 4) >= 40)
        #expect(many.innerRadius(count: 24) < 40)
    }
}
