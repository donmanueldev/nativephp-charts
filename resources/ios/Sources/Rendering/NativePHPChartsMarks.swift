import Charts
import SwiftUI

enum NativePHPChartsCandlestickBodyWidth: Equatable {
    case fixed(CGFloat)
    case ratio(CGFloat)

    var markDimension: MarkDimension {
        switch self {
        case let .fixed(width): .fixed(width)
        case let .ratio(ratio): .ratio(ratio)
        }
    }
}

struct NativePHPChartsCandlestickGeometry: Equatable {
    let x: Double
    let open: Double
    let high: Double
    let low: Double
    let close: Double
    let bodyWidth: NativePHPChartsCandlestickBodyWidth
    let cornerRadius: CGFloat

    var wickBounds: ClosedRange<Double> { low...high }
    var bodyBounds: ClosedRange<Double> { min(open, close)...max(open, close) }
    var anchor: NativePHPChartsPlottedPosition { NativePHPChartsPlottedPosition(x: x, y: close) }

    init?(
        point: NativePHPChartsPoint,
        x: Double,
        style: NativePHPChartsStyle.Bar
    ) {
        guard let open = point.open,
              let high = point.high,
              let low = point.low,
              let close = point.close
        else {
            return nil
        }

        self.x = x
        self.open = open
        self.high = high
        self.low = low
        self.close = close
        bodyWidth = style.width.map(NativePHPChartsCandlestickBodyWidth.fixed) ?? .ratio(0.62)
        cornerRadius = style.radius ?? 5
    }
}

struct NativePHPChartsAnnotations: ChartContent {
    let snapshot: NativePHPChartsSnapshot

    @ChartContentBuilder
    var body: some ChartContent {
        ForEach(snapshot.configuration.annotations) { annotation in
            if annotation.type == "line", let value = plotValue(annotation.value, axis: annotation.axis) {
                if annotation.axis == physicalXAxis {
                    RuleMark(x: .value(annotation.label ?? annotation.id, value))
                        .foregroundStyle(color(annotation))
                        .lineStyle(StrokeStyle(lineWidth: annotation.width ?? 1))
                        .annotation(position: .top, alignment: .leading) {
                            annotationLabel(annotation)
                        }
                } else {
                    RuleMark(y: .value(annotation.label ?? annotation.id, value))
                        .foregroundStyle(color(annotation))
                        .lineStyle(StrokeStyle(lineWidth: annotation.width ?? 1))
                        .annotation(position: .top, alignment: .trailing) {
                            annotationLabel(annotation)
                        }
                }
            } else if annotation.type == "band",
                      let from = plotValue(annotation.from, axis: annotation.axis),
                      let to = plotValue(annotation.to, axis: annotation.axis)
            {
                if annotation.axis == physicalXAxis {
                    RectangleMark(
                        xStart: .value("Band start", from),
                        xEnd: .value("Band end", to)
                    )
                    .foregroundStyle(color(annotation).opacity(annotation.opacity ?? 0.12))
                } else {
                    RectangleMark(
                        yStart: .value("Band start", from),
                        yEnd: .value("Band end", to)
                    )
                    .foregroundStyle(color(annotation).opacity(annotation.opacity ?? 0.12))
                }
            }
        }
    }

    private func plotValue(_ value: NativePHPChartsWireValue?, axis: String) -> Double? {
        guard axis == "x" else { return value?.numberValue }
        if snapshot.configuration.xAxis.type == .category {
            return snapshot.data.categoryPlotX(value)
        }
        return snapshot.configuration.xAxis.plotValue(value, formatter: snapshot.formatter)
    }

    private var physicalXAxis: String {
        snapshot.configuration.barOrientation == .horizontal ? "y" : "x"
    }

    private func color(_ annotation: NativePHPChartsAnnotation) -> Color {
        snapshot.configuration.style.color(annotation.color, fallback: .indigo)
    }

    @ViewBuilder
    private func annotationLabel(_ annotation: NativePHPChartsAnnotation) -> some View {
        if let label = annotation.label {
            Text(label)
                .font(.caption2)
                .foregroundStyle(color(annotation))
        }
    }
}

struct NativePHPChartsMarks: ChartContent {
    let kind: NativePHPChartsKind
    let snapshot: NativePHPChartsSnapshot
    let progress: CGFloat
    let viewport: ClosedRange<Double>?

    @ChartContentBuilder
    var body: some ChartContent {
        ForEach(snapshot.data.series) { series in
            ForEach(points(for: series)) { point in
                switch kind {
                case .line:
                    line(series: series, point: point)
                case .area:
                    area(series: series, point: point)
                case .bar:
                    bar(series: series, point: point)
                case .scatter:
                    scatter(series: series, point: point)
                case .candlestick:
                    candlestick(series: series, point: point)
                }

                if kind != .candlestick, let errorMin = point.errorMin, let errorMax = point.errorMax {
                    errorRange(series: series, point: point, minimum: errorMin, maximum: errorMax)
                }
            }
        }
    }

    private func points(for series: NativePHPChartsSeries) -> [NativePHPChartsPoint] {
        guard kind == .line || kind == .area else {
            return series.points
        }

        return snapshot.data.visiblePoints(for: series, in: viewport)
    }

    @ChartContentBuilder
    private func candlestick(series: NativePHPChartsSeries, point: NativePHPChartsPoint) -> some ChartContent {
        if let geometry = NativePHPChartsCandlestickGeometry(
            point: point,
            x: renderX(for: point),
            style: barStyle(for: series)
        ) {
            let color: Color = geometry.close >= geometry.open
                ? Color(red: 0.09, green: 0.64, blue: 0.36)
                : Color(red: 0.86, green: 0.18, blue: 0.22)
            RuleMark(
                x: .value("X", geometry.x),
                yStart: .value("Low", animated(geometry.wickBounds.lowerBound)),
                yEnd: .value("High", animated(geometry.wickBounds.upperBound))
            )
            .foregroundStyle(color)
            .lineStyle(StrokeStyle(lineWidth: 1.5))

            BarMark(
                x: .value("X", geometry.x),
                yStart: .value("Open", animated(geometry.open)),
                yEnd: .value("Close", animated(geometry.close)),
                width: geometry.bodyWidth.markDimension
            )
            .foregroundStyle(color)
            .cornerRadius(geometry.cornerRadius)
        }
    }

    @ChartContentBuilder
    private func line(series: NativePHPChartsSeries, point: NativePHPChartsPoint) -> some ChartContent {
        if let target = snapshot.data.fillTarget(for: series, point: point) {
            AreaMark(
                x: .value("X", renderX(for: point)),
                yStart: .value("Fill start", animated(target.value)),
                yEnd: .value("Fill end", animated(point.value)),
                series: .value("Fill series", series.id)
            )
            .foregroundStyle(areaStyle(for: series))
            .opacity(areaOpacity(for: series))
            .interpolationMethod(interpolation(for: series))
        }

        LineMark(
            x: .value("X", renderX(for: point)),
            y: .value(series.name, animated(point.value)),
            series: .value("Series", series.id)
        )
        .foregroundStyle(resolvedColor(for: series))
        .lineStyle(
            StrokeStyle(
                lineWidth: lineStyle(for: series).width ?? 2.75,
                lineCap: .round,
                lineJoin: .round,
                dash: lineStyle(for: series).dash ?? []
            )
        )
        .interpolationMethod(interpolation(for: series))

        if pointsVisible(for: series) || series.points.count == 1 {
            pointMark(series: series, point: point, y: animated(point.value), defaultSize: 5)
        }
    }

    @ChartContentBuilder
    private func area(series: NativePHPChartsSeries, point: NativePHPChartsPoint) -> some ChartContent {
        let bounds = snapshot.domain.areaBounds(for: point, mode: snapshot.configuration.areaMode)
        let outerY = snapshot.domain.areaOuterY(for: point, bounds: bounds)

        AreaMark(
            x: .value("X", renderX(for: point)),
            yStart: .value("Start", animated(bounds.lowerBound, from: 0)),
            yEnd: .value("End", animated(bounds.upperBound, from: 0)),
            series: .value("Series", series.id)
        )
        .foregroundStyle(areaStyle(for: series))
        .opacity(areaOpacity(for: series))
        .interpolationMethod(interpolation(for: series))

        LineMark(
            x: .value("X", renderX(for: point)),
            y: .value(series.name, animated(outerY, from: 0)),
            series: .value("Series", series.id)
        )
        .foregroundStyle(resolvedColor(for: series))
        .lineStyle(StrokeStyle(
            lineWidth: lineStyle(for: series).width ?? 2.25,
            lineCap: .round,
            dash: lineStyle(for: series).dash ?? []
        ))
        .interpolationMethod(interpolation(for: series))

        if pointsVisible(for: series) || series.points.count == 1 {
            pointMark(series: series, point: point, y: animated(outerY, from: 0), defaultSize: 4.5)
        }
    }

    @ChartContentBuilder
    private func bar(series: NativePHPChartsSeries, point: NativePHPChartsPoint) -> some ChartContent {
        let geometry = snapshot.domain.barGeometry(
            for: point,
            data: snapshot.data,
            mode: snapshot.configuration.barMode,
            orientation: snapshot.configuration.barOrientation
        )

        if snapshot.configuration.barOrientation == .horizontal {
            BarMark(
                xStart: .value("Start", animated(geometry.valueBounds.lowerBound)),
                xEnd: .value(series.name, animated(geometry.valueBounds.upperBound)),
                y: .value("Y", geometry.category),
                height: barStyle(for: series).width.map(MarkDimension.fixed) ?? .automatic
            )
            .foregroundStyle(resolvedColor(for: series))
            .cornerRadius(barStyle(for: series).radius ?? 5)
        } else {
            BarMark(
                x: .value("X", geometry.category),
                yStart: .value("Start", animated(geometry.valueBounds.lowerBound)),
                yEnd: .value(series.name, animated(geometry.valueBounds.upperBound)),
                width: barStyle(for: series).width.map(MarkDimension.fixed) ?? .automatic
            )
            .foregroundStyle(resolvedColor(for: series))
            .cornerRadius(barStyle(for: series).radius ?? 5)
        }
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
            x: .value("X", renderX(for: point)),
            y: .value(series.name, y)
        )
        .foregroundStyle(pointColor(for: series))
        .symbolSize(pow(pointsStyle(for: series).size ?? defaultSize, 2))
    }

    @ChartContentBuilder
    private func errorRange(
        series: NativePHPChartsSeries,
        point: NativePHPChartsPoint,
        minimum: Double,
        maximum: Double
    ) -> some ChartContent {
        if kind == .bar, snapshot.configuration.barOrientation == .horizontal {
            RuleMark(
                xStart: .value("Error minimum", animated(minimum)),
                xEnd: .value("Error maximum", animated(maximum)),
                y: .value("Y", renderX(for: point))
            )
            .foregroundStyle(resolvedColor(for: series))
            .lineStyle(StrokeStyle(lineWidth: 1.25, lineCap: .round))
        } else {
            RuleMark(
                x: .value("X", renderX(for: point)),
                yStart: .value("Error minimum", animated(minimum)),
                yEnd: .value("Error maximum", animated(maximum))
            )
            .foregroundStyle(resolvedColor(for: series))
            .lineStyle(StrokeStyle(lineWidth: 1.25, lineCap: .round))
        }
    }

    private func interpolation(for series: NativePHPChartsSeries) -> InterpolationMethod {
        switch lineStyle(for: series).interpolation {
        case "smooth": .catmullRom
        case "step_before": .stepStart
        case "step_after": .stepEnd
        default: .linear
        }
    }

    private func pointsVisible(for series: NativePHPChartsSeries) -> Bool {
        pointsStyle(for: series).visible ?? snapshot.configuration.showPoints
    }

    private func animated(_ value: Double, from baseline: Double? = nil) -> Double {
        let baseline = baseline ?? snapshot.domain.baseline
        return baseline + ((value - baseline) * Double(progress))
    }

    private func renderX(for point: NativePHPChartsPoint) -> Double {
        snapshot.data.renderX(for: point, kind: kind, barMode: snapshot.configuration.barMode)
    }

    private func pointColor(for series: NativePHPChartsSeries) -> Color {
        let override = series.style?.points.color
            ?? (snapshot.data.series.count == 1 ? snapshot.configuration.style.points.color : nil)

        return snapshot.configuration.style.color(
            override,
            fallback: resolvedColor(for: series)
        )
    }

    private func resolvedColor(for series: NativePHPChartsSeries) -> Color {
        let override = series.style?.line.color
            ?? (kind == .bar || snapshot.data.series.count > 1 ? nil : snapshot.configuration.style.line.color)

        return snapshot.configuration.style.color(override, fallback: series.color)
    }

    private func areaStyle(for series: NativePHPChartsSeries) -> AnyShapeStyle {
        let color = resolvedColor(for: series)

        guard areaStyleConfiguration(for: series).gradient ?? true else {
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

    private func lineStyle(for series: NativePHPChartsSeries) -> NativePHPChartsStyle.Line {
        let local = series.style?.line
        let global = snapshot.configuration.style.line
        return NativePHPChartsStyle.Line(
            color: local?.color ?? global.color,
            width: local?.width ?? global.width,
            interpolation: local?.interpolation ?? global.interpolation,
            dash: local?.dash ?? global.dash
        )
    }

    private func pointsStyle(for series: NativePHPChartsSeries) -> NativePHPChartsStyle.Points {
        let local = series.style?.points
        let global = snapshot.configuration.style.points
        return NativePHPChartsStyle.Points(
            visible: local?.visible ?? global.visible,
            color: local?.color ?? global.color,
            size: local?.size ?? global.size
        )
    }

    private func areaStyleConfiguration(for series: NativePHPChartsSeries) -> NativePHPChartsStyle.Area {
        let local = series.style?.area
        let global = snapshot.configuration.style.area
        return NativePHPChartsStyle.Area(
            opacity: local?.opacity ?? global.opacity,
            gradient: local?.gradient ?? global.gradient
        )
    }

    private func areaOpacity(for series: NativePHPChartsSeries) -> Double {
        areaStyleConfiguration(for: series).opacity ?? 0.28
    }

    private func barStyle(for series: NativePHPChartsSeries) -> NativePHPChartsStyle.Bar {
        let local = series.style?.bar
        let global = snapshot.configuration.style.bar
        return NativePHPChartsStyle.Bar(
            radius: local?.radius ?? global.radius,
            width: local?.width ?? global.width
        )
    }
}
