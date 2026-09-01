import Foundation

struct NativePHPChartsWireInput: Equatable {
    nonisolated(unsafe) private static let seriesFileCache = NSCache<NSString, NSString>()

    let contractVersion: Int
    let seriesJSON: String
    let styleJSON: String
    let xAxisJSON: String
    let yAxisJSON: String
    let legendJSON: String
    let annotationsJSON: String
    let interactionJSON: String
    let viewportJSON: String
    let samplingJSON: String
    let locale: String
    let valueFormat: String
    let currencyCode: String
    let minimumFractionDigits: Int
    let maximumFractionDigits: Int
    let showGrid: Bool
    let showPoints: Bool
    let beginAtZero: Bool
    let animated: Bool
    let emptyLabel: String
    let accessibilityLabel: String
    let onSelect: Int
    let onViewportChange: Int
    let areaMode: String
    let barMode: String
    let barOrientation: String

    init(node: NativeUINode) {
        contractVersion = node.props.getInt("contract_version", default: 0)
        seriesJSON = Self.resolveSeriesJSON(node: node)
        styleJSON = node.props.getString("style_json", default: "{}")
        xAxisJSON = node.props.getString("x_axis_json", default: "{}")
        yAxisJSON = node.props.getString("y_axis_json", default: "{}")
        legendJSON = node.props.getString("legend_json", default: "{}")
        annotationsJSON = node.props.getString("annotations_json", default: "[]")
        interactionJSON = node.props.getString("interaction_json", default: "{}")
        viewportJSON = node.props.getString("viewport_json", default: "{}")
        samplingJSON = node.props.getString("sampling_json", default: "{}")
        locale = node.props.getString("locale", default: "")
        valueFormat = node.props.getString("value_format", default: "number")
        currencyCode = node.props.getString("currency_code", default: "")
        minimumFractionDigits = node.props.getInt("minimum_fraction_digits", default: -1)
        maximumFractionDigits = node.props.getInt("maximum_fraction_digits", default: -1)
        showGrid = node.props.getBool("show_grid", default: true)
        showPoints = node.props.getBool("show_points", default: true)
        beginAtZero = node.props.getBool("begin_at_zero", default: true)
        animated = node.props.getBool("animated", default: true)
        emptyLabel = node.props.getString("empty_label", default: "No data")
        accessibilityLabel = node.props.getString("a11y_label", default: "Chart")
        onSelect = node.props.getInt("on_select", default: 0)
        onViewportChange = node.props.getInt("on_viewport_change", default: 0)
        areaMode = node.props.getString("area_mode", default: "overlay")
        barMode = node.props.getString("bar_mode", default: "grouped")
        barOrientation = node.props.getString("bar_orientation", default: "vertical")
    }

    private static func resolveSeriesJSON(node: NativeUINode) -> String {
        let inline = node.props.getString("series_json", default: "[]")
        let transport = node.props.getString("series_transport", default: "inline-v1")
        let path = node.props.getString("series_json_file", default: "")
        guard transport == "file-v1", !path.isEmpty else { return inline }

        if let cached = seriesFileCache.object(forKey: path as NSString) {
            return cached as String
        }

        guard let data = FileManager.default.contents(atPath: path),
              let value = String(data: data, encoding: .utf8)
        else {
            return "[]"
        }

        seriesFileCache.setObject(value as NSString, forKey: path as NSString)
        return value
    }
}

struct NativePHPChartsAxisConfiguration: Decodable {
    let type: NativePHPChartsXAxisType
    let format: NativePHPChartsNumberFormat?
    let dateFormat: String
    let timezone: String
    let labelCount: Int?
    let visible: Bool?
    let beginAtZero: Bool?
    let title: String?
    let minimum: NativePHPChartsWireValue?
    let maximum: NativePHPChartsWireValue?
    let baseline: NativePHPChartsWireValue?
    let interval: Double?

    enum CodingKeys: String, CodingKey {
        case type, format, timezone, visible, title, minimum, maximum, baseline, interval
        case dateFormat = "date_format"
        case labelCount = "label_count"
        case beginAtZero = "begin_at_zero"
        case valueFormat = "value_format"
        case currencyCode = "currency_code"
        case minimumFractionDigits = "minimum_fraction_digits"
        case maximumFractionDigits = "maximum_fraction_digits"
    }

    init(
        type: NativePHPChartsXAxisType = .category,
        format: NativePHPChartsNumberFormat? = nil,
        dateFormat: String = "medium",
        timezone: String = "",
        labelCount: Int? = nil,
        visible: Bool? = nil,
        beginAtZero: Bool? = nil,
        title: String? = nil,
        minimum: NativePHPChartsWireValue? = nil,
        maximum: NativePHPChartsWireValue? = nil,
        baseline: NativePHPChartsWireValue? = nil,
        interval: Double? = nil
    ) {
        self.type = type
        self.format = format
        self.dateFormat = dateFormat
        self.timezone = timezone
        self.labelCount = labelCount
        self.visible = visible
        self.beginAtZero = beginAtZero
        self.title = title
        self.minimum = minimum
        self.maximum = maximum
        self.baseline = baseline
        self.interval = interval
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        type = try container.decodeIfPresent(NativePHPChartsXAxisType.self, forKey: .type) ?? .category
        dateFormat = try container.decodeIfPresent(String.self, forKey: .dateFormat) ?? "medium"
        timezone = try container.decodeIfPresent(String.self, forKey: .timezone) ?? ""
        labelCount = try container.decodeIfPresent(Int.self, forKey: .labelCount)
        visible = try container.decodeIfPresent(Bool.self, forKey: .visible)
        beginAtZero = try container.decodeIfPresent(Bool.self, forKey: .beginAtZero)
        title = try container.decodeIfPresent(String.self, forKey: .title)
        minimum = try container.decodeIfPresent(NativePHPChartsWireValue.self, forKey: .minimum)
        maximum = try container.decodeIfPresent(NativePHPChartsWireValue.self, forKey: .maximum)
        baseline = try container.decodeIfPresent(NativePHPChartsWireValue.self, forKey: .baseline)
        interval = try container.decodeIfPresent(Double.self, forKey: .interval)

        if let nested = try container.decodeIfPresent(NativePHPChartsNumberFormat.self, forKey: .format) {
            format = nested
        } else if container.contains(.valueFormat) {
            format = NativePHPChartsNumberFormat(
                style: try container.decode(String.self, forKey: .valueFormat),
                currencyCode: try container.decodeIfPresent(String.self, forKey: .currencyCode),
                minimumFractionDigits: try container.decodeIfPresent(Int.self, forKey: .minimumFractionDigits),
                maximumFractionDigits: try container.decodeIfPresent(Int.self, forKey: .maximumFractionDigits)
            )
        } else {
            format = nil
        }
    }

    static func decode(_ json: String, fallback: NativePHPChartsAxisConfiguration) -> NativePHPChartsAxisConfiguration {
        guard json != "{}", let data = json.data(using: .utf8) else {
            return fallback
        }

        return (try? JSONDecoder().decode(NativePHPChartsAxisConfiguration.self, from: data)) ?? fallback
    }

    func plotValue(_ value: NativePHPChartsWireValue?, formatter: NativePHPChartsFormatter) -> Double? {
        guard let value else { return nil }

        switch type {
        case .category:
            return nil
        case .number:
            return value.numberValue
        case .date, .datetime:
            return formatter.date(from: value, type: type)?.timeIntervalSince1970
        }
    }

    var plotInterval: Double? {
        guard let interval else { return nil }

        return type == .date ? interval * 86_400 : interval
    }
}

struct NativePHPChartsNumberFormat: Decodable {
    let style: String
    let currencyCode: String?
    let minimumFractionDigits: Int?
    let maximumFractionDigits: Int?

    enum CodingKeys: String, CodingKey {
        case style
        case currencyCode = "currency_code"
        case minimumFractionDigits = "minimum_fraction_digits"
        case maximumFractionDigits = "maximum_fraction_digits"
    }

    init(
        style: String = "number",
        currencyCode: String? = nil,
        minimumFractionDigits: Int? = nil,
        maximumFractionDigits: Int? = nil
    ) {
        self.style = style
        self.currencyCode = currencyCode
        self.minimumFractionDigits = minimumFractionDigits
        self.maximumFractionDigits = maximumFractionDigits
    }
}

struct NativePHPChartsLegendConfiguration: Decodable {
    let visible: Bool?
    let position: String
    let alignment: String
    let style: Style

    enum CodingKeys: String, CodingKey {
        case visible, position, alignment, style
    }

    struct Style: Decodable {
        let font: String?
        let fontSize: CGFloat?
        let labelColor: String?
        let markerSize: CGFloat?

        enum CodingKeys: String, CodingKey {
            case font
            case fontSize = "font_size"
            case labelColor = "label_color"
            case markerSize = "marker_size"
        }

        init(font: String? = nil, fontSize: CGFloat? = nil, labelColor: String? = nil, markerSize: CGFloat? = nil) {
            self.font = font
            self.fontSize = fontSize
            self.labelColor = labelColor
            self.markerSize = markerSize
        }
    }

    init(
        visible: Bool? = nil,
        position: String = "bottom",
        alignment: String = "center",
        style: Style = Style()
    ) {
        self.visible = visible
        self.position = position
        self.alignment = alignment
        self.style = style
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        visible = try container.decodeIfPresent(Bool.self, forKey: .visible)
        position = try container.decodeIfPresent(String.self, forKey: .position) ?? "bottom"
        alignment = try container.decodeIfPresent(String.self, forKey: .alignment) ?? "center"
        style = try container.decodeIfPresent(Style.self, forKey: .style) ?? Style()
    }

    static func decode(_ json: String) -> NativePHPChartsLegendConfiguration {
        guard let data = json.data(using: .utf8),
              let value = try? JSONDecoder().decode(NativePHPChartsLegendConfiguration.self, from: data)
        else {
            return NativePHPChartsLegendConfiguration()
        }

        return value
    }
}

struct NativePHPChartsSelectionConfiguration: Decodable {
    let enabled: Bool
    let mode: String
    let crosshair: String
    let tooltip: String

    init(enabled: Bool = true, mode: String = "tap", crosshair: String = "x", tooltip: String = "single") {
        self.enabled = enabled
        self.mode = mode
        self.crosshair = crosshair
        self.tooltip = tooltip
    }

    static func decode(_ json: String) -> NativePHPChartsSelectionConfiguration {
        guard let data = json.data(using: .utf8),
              let value = try? JSONDecoder().decode(NativePHPChartsSelectionConfiguration.self, from: data)
        else {
            return NativePHPChartsSelectionConfiguration()
        }

        return value
    }
}

struct NativePHPChartsViewportConfiguration: Decodable {
    let enabled: Bool
    let pan: Bool
    let zoom: Bool
    let minimum: NativePHPChartsWireValue?
    let maximum: NativePHPChartsWireValue?
    let minimumSpan: Double?

    enum CodingKeys: String, CodingKey {
        case enabled, pan, zoom, minimum, maximum
        case minimumSpan = "minimum_span"
    }

    init(enabled: Bool = false, pan: Bool = true, zoom: Bool = true, minimum: NativePHPChartsWireValue? = nil, maximum: NativePHPChartsWireValue? = nil, minimumSpan: Double? = nil) {
        self.enabled = enabled
        self.pan = pan
        self.zoom = zoom
        self.minimum = minimum
        self.maximum = maximum
        self.minimumSpan = minimumSpan
    }

    static func decode(_ json: String) -> NativePHPChartsViewportConfiguration {
        guard let data = json.data(using: .utf8),
              let value = try? JSONDecoder().decode(NativePHPChartsViewportConfiguration.self, from: data)
        else {
            return NativePHPChartsViewportConfiguration()
        }

        return value
    }
}

struct NativePHPChartsAnnotation: Decodable, Identifiable {
    let id: String
    let type: String
    let axis: String
    let color: String
    let label: String?
    let value: NativePHPChartsWireValue?
    let from: NativePHPChartsWireValue?
    let to: NativePHPChartsWireValue?
    let width: CGFloat?
    let opacity: Double?

    static func decode(_ json: String) -> [NativePHPChartsAnnotation] {
        guard let data = json.data(using: .utf8) else { return [] }
        return (try? JSONDecoder().decode([NativePHPChartsAnnotation].self, from: data)) ?? []
    }
}

enum NativePHPChartsAreaMode: String {
    case overlay
    case stacked
}

enum NativePHPChartsBarMode: String {
    case grouped
    case stacked
}

enum NativePHPChartsBarOrientation: String {
    case vertical
    case horizontal
}

struct NativePHPChartsConfiguration {
    let xAxis: NativePHPChartsAxisConfiguration
    let yAxis: NativePHPChartsAxisConfiguration
    let legend: NativePHPChartsLegendConfiguration
    let selection: NativePHPChartsSelectionConfiguration
    let viewport: NativePHPChartsViewportConfiguration
    let annotations: [NativePHPChartsAnnotation]
    let style: NativePHPChartsStyle
    let showGrid: Bool
    let showPoints: Bool
    let beginAtZero: Bool
    let animated: Bool
    let emptyLabel: String
    let accessibilityLabel: String
    let onSelect: Int
    let onViewportChange: Int
    let areaMode: NativePHPChartsAreaMode
    let barMode: NativePHPChartsBarMode
    let barOrientation: NativePHPChartsBarOrientation

    static func decode(_ input: NativePHPChartsWireInput, kind: NativePHPChartsKind) -> NativePHPChartsConfiguration {
        let legacyYFormat = NativePHPChartsNumberFormat(
            style: input.valueFormat,
            currencyCode: input.currencyCode.isEmpty ? nil : input.currencyCode,
            minimumFractionDigits: input.minimumFractionDigits >= 0 ? input.minimumFractionDigits : nil,
            maximumFractionDigits: input.maximumFractionDigits >= 0 ? input.maximumFractionDigits : nil
        )
        let xAxis = NativePHPChartsAxisConfiguration.decode(
            input.xAxisJSON,
            fallback: NativePHPChartsAxisConfiguration(type: kind == .scatter ? .number : .category)
        )
        let yAxis = NativePHPChartsAxisConfiguration.decode(
            input.yAxisJSON,
            fallback: NativePHPChartsAxisConfiguration(format: legacyYFormat, beginAtZero: input.beginAtZero)
        )

        return NativePHPChartsConfiguration(
            xAxis: xAxis,
            yAxis: yAxis,
            legend: NativePHPChartsLegendConfiguration.decode(input.legendJSON),
            selection: NativePHPChartsSelectionConfiguration.decode(input.interactionJSON),
            viewport: NativePHPChartsViewportConfiguration.decode(input.viewportJSON),
            annotations: NativePHPChartsAnnotation.decode(input.annotationsJSON),
            style: NativePHPChartsStyle.decode(input.styleJSON),
            showGrid: input.showGrid,
            showPoints: input.showPoints,
            beginAtZero: yAxis.beginAtZero ?? input.beginAtZero,
            animated: input.animated,
            emptyLabel: input.emptyLabel,
            accessibilityLabel: input.accessibilityLabel,
            onSelect: input.onSelect,
            onViewportChange: input.onViewportChange,
            areaMode: NativePHPChartsAreaMode(rawValue: input.areaMode) ?? .overlay,
            barMode: NativePHPChartsBarMode(rawValue: input.barMode) ?? .grouped,
            barOrientation: NativePHPChartsBarOrientation(rawValue: input.barOrientation) ?? .vertical
        )
    }
}

struct NativePHPChartsSnapshot {
    let configuration: NativePHPChartsConfiguration
    let formatter: NativePHPChartsFormatter
    let data: NativePHPChartsDataSet
    let domain: NativePHPChartsDomain

    init(input: NativePHPChartsWireInput, kind: NativePHPChartsKind) {
        let configuration = NativePHPChartsConfiguration.decode(input, kind: kind)
        let formatter = NativePHPChartsFormatter(input: input, configuration: configuration)
        let data = NativePHPChartsDataSet.decode(
            seriesJSON: input.seriesJSON,
            xAxis: configuration.xAxis,
            formatter: formatter
        )

        self.configuration = configuration
        self.formatter = formatter
        self.data = data
        domain = NativePHPChartsDomain(
            data: data,
            configuration: configuration,
            formatter: formatter,
            kind: kind
        )
    }
}
