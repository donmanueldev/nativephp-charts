import SwiftUI

/// One declared spoke in the ordered radar domain.
struct NativePHPChartsRadarAxis: Decodable, Hashable, Identifiable {
    let id: String
    let label: String
    let maximum: Double
}

struct NativePHPChartsRadarValue: Decodable, Hashable {
    let axis: String
    let value: Double
}

struct NativePHPChartsRadarSeries: Decodable, Hashable, Identifiable {
    let id: String
    let name: String
    let colorValue: String
    let values: [NativePHPChartsRadarValue]

    enum CodingKeys: String, CodingKey {
        case id, name, values
        case colorValue = "color"
    }

    var color: Color { Color(argb: ColorParser.parse(colorValue, default: 0xFF6366F1)) }
}

struct NativePHPChartsRadarSelection: Hashable, Identifiable {
    let series: NativePHPChartsRadarSeries
    let axis: NativePHPChartsRadarAxis
    let value: NativePHPChartsRadarValue
    let index: Int

    var id: String { "\(series.id.utf8.count):\(series.id)\(axis.id)" }
}

/// Captures the normalized radar EDGE properties before native snapshot construction.
///
/// Axes and values use separate JSON lists, but PHP guarantees that each series repeats every
/// axis exactly once and in declaration order. Defaults keep older generated shells readable.
struct NativePHPChartsRadarWireInput: Equatable {
    let axesJSON: String
    let seriesJSON: String
    let styleJSON: String
    let legendJSON: String
    let locale: String
    let valueFormat: String
    let currencyCode: String
    let minimumFractionDigits: Int
    let maximumFractionDigits: Int
    let animated: Bool
    let emptyLabel: String
    let accessibilityLabel: String
    let onSelect: Int
    let gridLevels: Int
    let fillOpacity: Double

    init(
        axesJSON: String = "[]",
        seriesJSON: String = "[]",
        styleJSON: String = "{}",
        legendJSON: String = "{}",
        locale: String = "",
        valueFormat: String = "number",
        currencyCode: String = "",
        minimumFractionDigits: Int = -1,
        maximumFractionDigits: Int = -1,
        animated: Bool = true,
        emptyLabel: String = "No data",
        accessibilityLabel: String = "Chart",
        onSelect: Int = 0,
        gridLevels: Int = 5,
        fillOpacity: Double = 0.22
    ) {
        self.axesJSON = axesJSON
        self.seriesJSON = seriesJSON
        self.styleJSON = styleJSON
        self.legendJSON = legendJSON
        self.locale = locale
        self.valueFormat = valueFormat
        self.currencyCode = currencyCode
        self.minimumFractionDigits = minimumFractionDigits
        self.maximumFractionDigits = maximumFractionDigits
        self.animated = animated
        self.emptyLabel = emptyLabel
        self.accessibilityLabel = accessibilityLabel
        self.onSelect = onSelect
        self.gridLevels = gridLevels
        self.fillOpacity = fillOpacity
    }

    init(node: NativeUINode) {
        self.init(
            axesJSON: node.props.getString("axes_json", default: "[]"),
            seriesJSON: node.props.getString("series_json", default: "[]"),
            styleJSON: node.props.getString("style_json", default: "{}"),
            legendJSON: node.props.getString("legend_json", default: "{}"),
            locale: node.props.getString("locale", default: ""),
            valueFormat: node.props.getString("value_format", default: "number"),
            currencyCode: node.props.getString("currency_code", default: ""),
            minimumFractionDigits: node.props.getInt("minimum_fraction_digits", default: -1),
            maximumFractionDigits: node.props.getInt("maximum_fraction_digits", default: -1),
            animated: node.props.getBool("animated", default: true),
            emptyLabel: node.props.getString("empty_label", default: "No data"),
            accessibilityLabel: node.props.getString("a11y_label", default: "Chart"),
            onSelect: node.props.getInt("on_select", default: 0),
            gridLevels: node.props.getInt("grid_levels", default: 5),
            fillOpacity: Double(node.props.getFloat("fill_opacity", default: 0.22))
        )
    }
}

/// Decodes one radar wire revision into aligned geometry, formatting, and selections.
///
/// The native alignment check repeats the PHP invariant by accepting a value only when its
/// array index exists and its axis id matches the declared spoke. Malformed JSON or misaligned
/// values therefore become empty/partial native state instead of connecting the wrong axes.
struct NativePHPChartsRadarSnapshot {
    let axes: [NativePHPChartsRadarAxis]
    let series: [NativePHPChartsRadarSeries]
    let style: NativePHPChartsStyle
    let legend: NativePHPChartsLegendConfiguration
    let formatter: NativePHPChartsRadarFormatter
    let animated: Bool
    let emptyLabel: String
    let accessibilityLabel: String
    let onSelect: Int
    let gridLevels: Int
    let fillOpacity: Double
    let animationID: Int
    let selections: [NativePHPChartsRadarSelection]

    init(input: NativePHPChartsRadarWireInput) {
        let decodedAxes = Self.decode([NativePHPChartsRadarAxis].self, input.axesJSON)
        let decodedSeries = Self.decode([NativePHPChartsRadarSeries].self, input.seriesJSON)
        let decodedStyle = NativePHPChartsStyle.decode(input.styleJSON)

        axes = decodedAxes
        series = decodedSeries
        style = decodedStyle
        legend = NativePHPChartsLegendConfiguration.decode(input.legendJSON)
        formatter = NativePHPChartsRadarFormatter(input: input)
        animated = input.animated
        emptyLabel = input.emptyLabel
        accessibilityLabel = input.accessibilityLabel
        onSelect = input.onSelect
        gridLevels = min(max(input.gridLevels, 2), 10)
        fillOpacity = min(max(decodedStyle.area.opacity ?? input.fillOpacity, 0), 1)
        selections = decodedSeries.flatMap { item in
            item.values.enumerated().compactMap { index, value in
                guard decodedAxes.indices.contains(index) else { return nil }
                let axis = decodedAxes[index]
                guard axis.id == value.axis else { return nil }
                return NativePHPChartsRadarSelection(series: item, axis: axis, value: value, index: index)
            }
        }

        var hasher = Hasher()
        hasher.combine(decodedAxes)
        hasher.combine(decodedSeries)
        animationID = hasher.finalize()
    }

    var isEmpty: Bool { axes.count < 3 || series.allSatisfy(\.values.isEmpty) }
    var legendVisible: Bool { legend.visible ?? (series.count > 1) }

    func selection(id: String?) -> NativePHPChartsRadarSelection? {
        selections.first { $0.id == id }
    }

    private static func decode<Value: Decodable>(_ type: Value.Type, _ json: String) -> Value where Value: RangeReplaceableCollection {
        guard let data = json.data(using: .utf8), let value = try? JSONDecoder().decode(type, from: data) else {
            return Value()
        }
        return value
    }
}

final class NativePHPChartsRadarFormatter {
    private let numberFormatter: NumberFormatter

    init(input: NativePHPChartsRadarWireInput) {
        numberFormatter = NativePHPChartsFormatter.makeNumberFormatter(
            locale: input.locale.isEmpty ? .current : Locale(identifier: input.locale),
            configuration: NativePHPChartsNumberFormat(
                style: input.valueFormat,
                currencyCode: input.currencyCode.isEmpty ? nil : input.currencyCode,
                minimumFractionDigits: input.minimumFractionDigits >= 0 ? input.minimumFractionDigits : nil,
                maximumFractionDigits: input.maximumFractionDigits >= 0 ? input.maximumFractionDigits : nil
            )
        )
    }

    func value(_ value: Double) -> String {
        numberFormatter.string(from: value as NSNumber) ?? String(value)
    }
}
