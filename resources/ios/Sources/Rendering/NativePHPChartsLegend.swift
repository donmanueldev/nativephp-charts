import SwiftUI

struct NativePHPChartsLegend: View {
    let data: NativePHPChartsDataSet
    let configuration: NativePHPChartsLegendConfiguration
    let kind: NativePHPChartsKind
    let style: NativePHPChartsStyle

    @ScaledMetric(relativeTo: .caption) private var fontScale = 1.0

    var body: some View {
        Group {
            if isVertical {
                VStack(alignment: horizontalAlignment, spacing: 10) {
                    legendItems
                }
            } else {
                ViewThatFits(in: .horizontal) {
                    HStack(spacing: 14) {
                        legendItems
                    }
                    .frame(maxWidth: .infinity, alignment: alignment)
                    .padding(.horizontal, 2)

                    ScrollView(.horizontal, showsIndicators: false) {
                        HStack(spacing: 14) {
                            legendItems
                        }
                        .padding(.horizontal, 2)
                    }
                }
            }
        }
    }

    @ViewBuilder
    private var legendItems: some View {
        ForEach(data.series) { series in
            HStack(spacing: 6) {
                Circle()
                    .fill(markerColor(for: series))
                    .frame(width: markerSize, height: markerSize)
                Text(series.name)
                    .font(legendFont)
                    .foregroundStyle(labelColor)
                    .lineLimit(1)
            }
            .accessibilityElement(children: .combine)
            .accessibilityLabel(series.name)
        }
    }

    private var markerSize: CGFloat {
        configuration.style.markerSize ?? 9
    }

    private var labelColor: Color {
        guard let value = configuration.style.labelColor else {
            return .secondary
        }

        return Color(argb: ColorParser.parse(value, default: 0xFF6B7280))
    }

    private var legendFont: Font {
        let spatialScale = NativePHPChartsTypography.spatialScale(Double(fontScale))
        let scaledSize = (configuration.style.fontSize ?? 11) * CGFloat(spatialScale)

        if let token = configuration.style.font,
           let resolved = NativeUIFontResolver.font(token, size: scaledSize)
        {
            return resolved
        }

        return .system(size: scaledSize, weight: .medium)
    }

    private var alignment: Alignment {
        switch configuration.alignment {
        case "start", "leading": .leading
        case "end", "trailing": .trailing
        default: .center
        }
    }

    private var horizontalAlignment: HorizontalAlignment {
        switch configuration.alignment {
        case "end", "trailing": .trailing
        case "center": .center
        default: .leading
        }
    }

    private func markerColor(for series: NativePHPChartsSeries) -> Color {
        if kind == .scatter, data.series.count == 1 {
            return style.color(style.points.color, fallback: series.color)
        }

        guard kind != .bar, data.series.count == 1 else {
            return series.color
        }

        return style.color(style.line.color, fallback: series.color)
    }

    private var isVertical: Bool {
        configuration.position == "leading" || configuration.position == "trailing"
    }
}
