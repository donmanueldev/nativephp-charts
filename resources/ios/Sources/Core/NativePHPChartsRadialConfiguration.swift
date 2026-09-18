import Foundation

enum NativePHPChartsRadialKind: String {
    case pie
    case donut
}

/// Captures the pie/donut EDGE payload before radial-specific normalization.
///
/// Missing fields retain cross-version defaults. PHP is the authoritative validator, while
/// the native configuration still clamps visual ratios so a stale shell cannot create
/// degenerate geometry.
struct NativePHPChartsRadialWireInput: Equatable, Sendable {
    let contractVersion: Int
    let segmentsJSON: String
    let styleJSON: String
    let legendJSON: String
    let locale: String
    let valueFormat: String
    let currencyCode: String
    let minimumFractionDigits: Int
    let maximumFractionDigits: Int
    let animated: Bool
    let emptyLabel: String
    let errorLabel: String
    let accessibilityLabel: String
    let themeMode: String
    let preset: String
    let themeJSON: String
    let onSelect: Int
    let innerRadiusRatio: Double

    init(node: NativeUINode, kind: NativePHPChartsRadialKind) {
        contractVersion = node.props.getInt("contract_version", default: 0)
        segmentsJSON = node.props.getString("segments_json", default: "[]")
        styleJSON = node.props.getString("style_json", default: "{}")
        legendJSON = node.props.getString("legend_json", default: "{}")
        locale = node.props.getString("locale", default: "")
        valueFormat = node.props.getString("value_format", default: "number")
        currencyCode = node.props.getString("currency_code", default: "")
        minimumFractionDigits = node.props.getInt("minimum_fraction_digits", default: -1)
        maximumFractionDigits = node.props.getInt("maximum_fraction_digits", default: -1)
        animated = node.props.getBool("animated", default: true)
        emptyLabel = node.props.getString("empty_label", default: "No data")
        errorLabel = node.props.getString("error_label", default: "Chart unavailable")
        accessibilityLabel = node.props.getString("a11y_label", default: "Chart")
        themeMode = node.props.getString("theme_mode", default: "system")
        preset = node.props.getString("preset", default: "default")
        themeJSON = node.props.getString("theme_json", default: "{}")
        onSelect = node.props.getInt("on_select", default: 0)

        let defaultRatio: Float = kind == .donut ? 0.6 : 0
        innerRadiusRatio = Double(node.props.getFloat("inner_radius_ratio", default: defaultRatio))
    }

    static func testing(contractVersion: Int = 1, segmentsJSON: String) -> Self {
        Self(contractVersion: contractVersion, segmentsJSON: segmentsJSON)
    }

    private init(contractVersion: Int, segmentsJSON: String) {
        self.contractVersion = contractVersion; self.segmentsJSON = segmentsJSON
        styleJSON = "{}"; legendJSON = "{}"; locale = ""; valueFormat = "number"; currencyCode = ""
        minimumFractionDigits = -1; maximumFractionDigits = -1; animated = true
        emptyLabel = "No data"; errorLabel = "Chart unavailable"; accessibilityLabel = "Chart"
        themeMode = "system"; preset = "default"; themeJSON = "{}"
        onSelect = 0; innerRadiusRatio = 0
    }
}

struct NativePHPChartsRadialStyle: Decodable {
    let segment: Segment

    struct Segment: Decodable {
        let gap: CGFloat?
        let cornerRadius: CGFloat?
        let opacity: Double?

        enum CodingKeys: String, CodingKey {
            case gap, opacity
            case cornerRadius = "corner_radius"
        }

        init(gap: CGFloat? = nil, cornerRadius: CGFloat? = nil, opacity: Double? = nil) {
            self.gap = gap
            self.cornerRadius = cornerRadius
            self.opacity = opacity
        }
    }

    enum CodingKeys: String, CodingKey {
        case segment
    }

    init(segment: Segment = Segment()) {
        self.segment = segment
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        segment = try container.decodeIfPresent(Segment.self, forKey: .segment) ?? Segment()
    }

    static func decode(_ json: String) -> NativePHPChartsRadialStyle {
        guard let data = json.data(using: .utf8),
              let style = try? JSONDecoder().decode(NativePHPChartsRadialStyle.self, from: data)
        else {
            return NativePHPChartsRadialStyle()
        }

        return style
    }

    var gap: CGFloat {
        min(max(segment.gap ?? 2, 0), 12)
    }

    var cornerRadius: CGFloat {
        min(max(segment.cornerRadius ?? 0, 0), 20)
    }

    var opacity: Double {
        min(max(segment.opacity ?? 1, 0), 1)
    }
}

/// Resolves shared legend/style options and the chart-kind-specific inner radius invariant.
struct NativePHPChartsRadialConfiguration {
    let legend: NativePHPChartsLegendConfiguration
    let style: NativePHPChartsRadialStyle
    let animated: Bool
    let emptyLabel: String
    let accessibilityLabel: String
    let onSelect: Int
    let innerRadiusRatio: Double
    let theme: NativePHPChartsTheme.Variant?
    let palette: [String]

    init(input: NativePHPChartsRadialWireInput, kind: NativePHPChartsRadialKind, colorSchemeIsDark: Bool = false) {
        legend = NativePHPChartsLegendConfiguration.decode(input.legendJSON)
        style = NativePHPChartsRadialStyle.decode(input.styleJSON)
        animated = input.animated
        emptyLabel = input.emptyLabel
        accessibilityLabel = input.accessibilityLabel
        onSelect = input.onSelect
        theme = NativePHPChartsTheme.decode(input.themeJSON)?.variant(mode: input.themeMode, colorSchemeIsDark: colorSchemeIsDark)
        palette = theme?.palette.isEmpty == false ? (theme?.palette ?? []) : NativePHPChartsRuntime.palette(preset: input.preset)

        switch kind {
        case .pie:
            innerRadiusRatio = 0
        case .donut:
            innerRadiusRatio = min(max(input.innerRadiusRatio, 0.2), 0.85)
        }
    }
}

/// Immutable radial configuration, formatter, and cumulative angular data for one node revision.
struct NativePHPChartsRadialSnapshot {
    let availability: NativePHPChartsAvailability
    let configuration: NativePHPChartsRadialConfiguration
    let formatter: NativePHPChartsRadialFormatter
    let data: NativePHPChartsRadialDataSet

    init(input: NativePHPChartsRadialWireInput, kind: NativePHPChartsRadialKind, colorSchemeIsDark: Bool = false) {
        configuration = NativePHPChartsRadialConfiguration(input: input, kind: kind, colorSchemeIsDark: colorSchemeIsDark)
        formatter = NativePHPChartsRadialFormatter(input: input)
        if input.contractVersion != NativePHPChartsRuntime.supportedContractVersion {
            availability = .unsupportedContract
            data = NativePHPChartsRadialDataSet(segments: [], total: 0)
        } else if NativePHPChartsRadialDataSet.validate(input.segmentsJSON) == false {
            availability = .invalidPayload
            data = NativePHPChartsRadialDataSet(segments: [], total: 0)
        } else {
            availability = .available
            data = NativePHPChartsRadialDataSet.decode(input.segmentsJSON, palette: configuration.palette)
        }
        NativePHPChartsRuntime.diagnose(availability, chartType: kind.rawValue)
    }

    @concurrent static func load(input: NativePHPChartsRadialWireInput, kind: NativePHPChartsRadialKind, colorSchemeIsDark: Bool = false) async -> Self {
        Self(input: input, kind: kind, colorSchemeIsDark: colorSchemeIsDark)
    }
}
