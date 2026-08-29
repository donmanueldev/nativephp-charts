import Charts
import SwiftUI

struct NativePHPChartsMarks: ChartContent {
    let kind: NativePHPChartsKind
    let snapshot: NativePHPChartsSnapshot
    let progress: CGFloat

    @ChartContentBuilder
    var body: some ChartContent {
        ForEach(snapshot.data.series) { series in
            ForEach(series.points) { point in
                switch kind {
                case .line:
                    line(series: series, point: point)
                case .area:
                    area(series: series, point: point)
                case .bar:
                    bar(series: series, point: point)
                case .scatter:
                    scatter(series: series, point: point)
                }
            }
        }
    }

    @ChartContentBuilder
    private func line(series: NativePHPChartsSeries, point: NativePHPChartsPoint) -> some ChartContent {
        LineMark(
            x: .value("X", snapshot.data.renderX(for: point, kind: kind)),
            y: .value(series.name, animated(point.value)),
            series: .value("Series", series.id)
        )
        .foregroundStyle(resolvedColor(for: series))
        .lineStyle(
            StrokeStyle(
                lineWidth: snapshot.configuration.style.line.width ?? 2.75,
                lineCap: .round,
                lineJoin: .round
            )
        )
        .interpolationMethod(interpolation)

        if pointsVisible || series.points.count == 1 {
            pointMark(series: series, point: point, y: animated(point.value), defaultSize: 5)
        }
    }

    @ChartContentBuilder
    private func area(series: NativePHPChartsSeries, point: NativePHPChartsPoint) -> some ChartContent {
        let bounds = snapshot.domain.areaBounds(for: point, mode: snapshot.configuration.areaMode)
        let outerY = snapshot.domain.areaOuterY(for: point, bounds: bounds)

        AreaMark(
            x: .value("X", snapshot.data.renderX(for: point, kind: kind)),
            yStart: .value("Start", animated(bounds.lowerBound, from: 0)),
            yEnd: .value("End", animated(bounds.upperBound, from: 0)),
            series: .value("Series", series.id)
        )
        .foregroundStyle(areaStyle(for: series))
        .opacity(snapshot.configuration.style.area.opacity ?? 0.28)
        .interpolationMethod(interpolation)

        LineMark(
            x: .value("X", snapshot.data.renderX(for: point, kind: kind)),
            y: .value(series.name, animated(outerY, from: 0)),
            series: .value("Series", series.id)
        )
        .foregroundStyle(resolvedColor(for: series))
        .lineStyle(StrokeStyle(lineWidth: snapshot.configuration.style.line.width ?? 2.25, lineCap: .round))
        .interpolationMethod(interpolation)

        if pointsVisible || series.points.count == 1 {
            pointMark(series: series, point: point, y: animated(outerY, from: 0), defaultSize: 4.5)
        }
    }

    private func bar(series: NativePHPChartsSeries, point: NativePHPChartsPoint) -> some ChartContent {
        BarMark(
            x: .value("X", snapshot.data.renderX(for: point, kind: kind)),
            yStart: .value("Start", snapshot.domain.baseline),
            yEnd: .value(series.name, animated(point.value)),
            width: snapshot.configuration.style.bar.width.map(MarkDimension.fixed) ?? .automatic
        )
        .foregroundStyle(resolvedColor(for: series))
        .cornerRadius(snapshot.configuration.style.bar.radius ?? 5)
    }

    private func scatter(series: NativePHPChartsSeries, point: NativePHPChartsPoint) -> some ChartContent {
        pointMark(series: series, point: point, y: animated(point.value), defaultSize: 7)
    }

    private func pointMark(
        series: NativePHPChartsSeries,
        point: NativePHPChartsPoint,
        y: Double,
        defaultSize: CGFloat
    ) -> some ChartContent {
        PointMark(
            x: .value("X", snapshot.data.renderX(for: point, kind: kind)),
            y: .value(series.name, y)
        )
        .foregroundStyle(pointColor(for: series))
        .symbolSize(pow(snapshot.configuration.style.points.size ?? defaultSize, 2))
    }

    private var interpolation: InterpolationMethod {
        snapshot.configuration.style.line.interpolation == "smooth" ? .catmullRom : .linear
    }

    private var pointsVisible: Bool {
        snapshot.configuration.style.points.visible ?? snapshot.configuration.showPoints
    }

    private func animated(_ value: Double, from baseline: Double? = nil) -> Double {
        let baseline = baseline ?? snapshot.domain.baseline
        return baseline + ((value - baseline) * Double(progress))
    }

    private func pointColor(for series: NativePHPChartsSeries) -> Color {
        let override = snapshot.data.series.count == 1
            ? snapshot.configuration.style.points.color
            : nil

        return snapshot.configuration.style.color(
            override,
            fallback: resolvedColor(for: series)
        )
    }

    private func resolvedColor(for series: NativePHPChartsSeries) -> Color {
        let override: String? = kind == .bar || snapshot.data.series.count > 1
            ? nil
            : snapshot.configuration.style.line.color

        return snapshot.configuration.style.color(override, fallback: series.color)
    }

    private func areaStyle(for series: NativePHPChartsSeries) -> AnyShapeStyle {
        let color = resolvedColor(for: series)

        guard snapshot.configuration.style.area.gradient ?? true else {
            return AnyShapeStyle(color)
        }

        return AnyShapeStyle(
            LinearGradient(
                colors: [color.opacity(0.9), color.opacity(0.16)],
                startPoint: .top,
                endPoint: .bottom
            )
        )
    }
}
