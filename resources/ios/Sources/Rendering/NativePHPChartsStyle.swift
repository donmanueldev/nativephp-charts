import Foundation
import SwiftUI

struct NativePHPChartsStyle: Decodable {
    let line: Line
    let area: Area
    let bar: Bar
    let candlestick: Candlestick
    let points: Points
    let grid: Grid
    let axis: Axis

    enum CodingKeys: String, CodingKey {
        case line, area, bar, candlestick, points, grid, axis
    }

    init(
        line: Line = Line(),
        area: Area = Area(),
        bar: Bar = Bar(),
        candlestick: Candlestick = Candlestick(),
        points: Points = Points(),
        grid: Grid = Grid(),
        axis: Axis = Axis()
    ) {
        self.line = line
        self.area = area
        self.bar = bar
        self.candlestick = candlestick
        self.points = points
        self.grid = grid
        self.axis = axis
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        line = try container.decodeIfPresent(Line.self, forKey: .line) ?? Line()
        area = try container.decodeIfPresent(Area.self, forKey: .area) ?? Area()
        bar = try container.decodeIfPresent(Bar.self, forKey: .bar) ?? Bar()
        candlestick = try container.decodeIfPresent(Candlestick.self, forKey: .candlestick) ?? Candlestick()
        points = try container.decodeIfPresent(Points.self, forKey: .points) ?? Points()
        grid = try container.decodeIfPresent(Grid.self, forKey: .grid) ?? Grid()
        axis = try container.decodeIfPresent(Axis.self, forKey: .axis) ?? Axis()
    }

    static func decode(_ json: String) -> NativePHPChartsStyle {
        guard let data = json.data(using: .utf8),
              let style = try? JSONDecoder().decode(NativePHPChartsStyle.self, from: data)
        else {
            return NativePHPChartsStyle()
        }

        return style
    }

    struct Line: Decodable {
        let color: String?
        let width: CGFloat?
        let interpolation: String?
        let dash: [CGFloat]?

        init(color: String? = nil, width: CGFloat? = nil, interpolation: String? = nil, dash: [CGFloat]? = nil) {
            self.color = color
            self.width = width
            self.interpolation = interpolation
            self.dash = dash
        }
    }

    struct Area: Decodable {
        let opacity: Double?
        let gradient: Bool?

        init(opacity: Double? = nil, gradient: Bool? = nil) {
            self.opacity = opacity
            self.gradient = gradient
        }
    }

    struct Bar: Decodable {
        let radius: CGFloat?
        let width: CGFloat?

        enum CodingKeys: String, CodingKey {
            case radius, width
            case cornerRadius = "corner_radius"
        }

        init(radius: CGFloat? = nil, width: CGFloat? = nil) {
            self.radius = radius
            self.width = width
        }

        init(from decoder: Decoder) throws {
            let container = try decoder.container(keyedBy: CodingKeys.self)
            radius = try container.decodeIfPresent(CGFloat.self, forKey: .radius)
                ?? container.decodeIfPresent(CGFloat.self, forKey: .cornerRadius)
            width = try container.decodeIfPresent(CGFloat.self, forKey: .width)
        }
    }

    struct Candlestick: Decodable, Equatable {
        let risingColor: String?
        let fallingColor: String?
        let neutralColor: String?
        let wickWidth: CGFloat?

        enum CodingKeys: String, CodingKey {
            case risingColor = "rising_color"
            case fallingColor = "falling_color"
            case neutralColor = "neutral_color"
            case wickWidth = "wick_width"
        }

        init(
            risingColor: String? = nil,
            fallingColor: String? = nil,
            neutralColor: String? = nil,
            wickWidth: CGFloat? = nil
        ) {
            self.risingColor = risingColor
            self.fallingColor = fallingColor
            self.neutralColor = neutralColor
            self.wickWidth = wickWidth
        }

        func overriding(_ override: Candlestick?) -> Candlestick {
            Candlestick(
                risingColor: override?.risingColor ?? risingColor,
                fallingColor: override?.fallingColor ?? fallingColor,
                neutralColor: override?.neutralColor ?? neutralColor,
                wickWidth: override?.wickWidth ?? wickWidth
            )
        }

        func colorValue(open: Double, close: Double) -> String {
            if close > open {
                return risingColor ?? "#16A35B"
            }
            if close < open {
                return fallingColor ?? "#DB2E38"
            }

            return neutralColor ?? risingColor ?? "#16A35B"
        }

        var resolvedWickWidth: CGFloat { wickWidth ?? 1.5 }
    }

    struct Points: Decodable {
        let visible: Bool?
        let color: String?
        let size: CGFloat?

        init(visible: Bool? = nil, color: String? = nil, size: CGFloat? = nil) {
            self.visible = visible
            self.color = color
            self.size = size
        }
    }

    struct Grid: Decodable {
        let visible: Bool?
        let color: String?
        let width: CGFloat?

        init(visible: Bool? = nil, color: String? = nil, width: CGFloat? = nil) {
            self.visible = visible
            self.color = color
            self.width = width
        }
    }

    struct Axis: Decodable {
        let visible: Bool?
        let color: String?
        let labelColor: String?
        let font: String?
        let fontSize: CGFloat?
        let labelCount: Int?

        enum CodingKeys: String, CodingKey {
            case visible, color, font
            case labelColor = "label_color"
            case fontSize = "font_size"
            case labelCount = "label_count"
        }

        init(
            visible: Bool? = nil,
            color: String? = nil,
            labelColor: String? = nil,
            font: String? = nil,
            fontSize: CGFloat? = nil,
            labelCount: Int? = nil
        ) {
            self.visible = visible
            self.color = color
            self.labelColor = labelColor
            self.font = font
            self.fontSize = fontSize
            self.labelCount = labelCount
        }
    }

    func color(_ value: String?, fallback: Color) -> Color {
        guard let value else {
            return fallback
        }

        return Color(argb: ColorParser.parse(value, default: 0xFF6366F1))
    }

    func axisFont(scale: CGFloat) -> Font {
        let scaledSize = (axis.fontSize ?? 10) * scale

        if let token = axis.font,
           !token.isEmpty,
           let resolved = NativeUIFontResolver.font(token, size: scaledSize)
        {
            return resolved
        }

        return .system(size: scaledSize)
    }
}

enum NativePHPChartsAnimation {
    static func resolved(enabled: Bool, reduceMotion: Bool) -> Animation? {
        guard enabled, !reduceMotion else {
            return nil
        }

        return .smooth(duration: 0.42)
    }
}
