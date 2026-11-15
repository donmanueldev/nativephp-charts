import SwiftUI

struct NativePHPChartsProgressMetric: Decodable, Identifiable, Sendable {
    let id: String
    let label: String
    let value: Double
    let color: String?
    let sourceIndex: Int

    private enum CodingKeys: String, CodingKey { case id, label, value, color }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        id = try container.decode(String.self, forKey: .id)
        label = try container.decode(String.self, forKey: .label)
        value = try container.decode(Double.self, forKey: .value)
        color = try container.decodeIfPresent(String.self, forKey: .color)
        sourceIndex = decoder.codingPath.last?.intValue ?? 0
    }

    init(id: String, label: String, value: Double, color: String? = nil, sourceIndex: Int) {
        self.id = id; self.label = label; self.value = value; self.color = color; self.sourceIndex = sourceIndex
    }
}

struct NativePHPChartsProgressWireInput: Equatable, Sendable {
    let contractVersion: Int
    let metricsJSON: String
    let centerLabel: String
    let styleJSON: String
    let legendJSON: String
    let themeMode: String
    let preset: String
    let themeJSON: String
    let locale: String
    let minimumFractionDigits: Int
    let maximumFractionDigits: Int
    let errorLabel: String
    let emptyLabel: String
    let accessibilityLabel: String
    let animated: Bool
    let onSelect: Int

    init(node: NativeUINode) {
        contractVersion = node.props.getInt("contract_version", default: 0)
        metricsJSON = node.props.getString("metrics_json", default: "[]")
        centerLabel = node.props.getString("center_label", default: "")
        styleJSON = node.props.getString("style_json", default: "{}")
        legendJSON = node.props.getString("legend_json", default: "{}")
        themeMode = node.props.getString("theme_mode", default: "system")
        preset = node.props.getString("preset", default: "default")
        themeJSON = node.props.getString("theme_json", default: "{}")
        locale = node.props.getString("locale", default: "")
        minimumFractionDigits = node.props.getInt("minimum_fraction_digits", default: -1)
        maximumFractionDigits = node.props.getInt("maximum_fraction_digits", default: -1)
        errorLabel = node.props.getString("error_label", default: "Chart unavailable")
        emptyLabel = node.props.getString("empty_label", default: "No data")
        accessibilityLabel = node.props.getString("a11y_label", default: "Progress chart")
        animated = node.props.getBool("animated", default: true)
        onSelect = node.props.getInt("on_select", default: 0)
    }

    static func testing(
        contractVersion: Int = 1,
        metricsJSON: String,
        locale: String = "",
        maximumFractionDigits: Int = -1,
        preset: String = "default"
    ) -> Self {
        Self(
            contractVersion: contractVersion,
            metricsJSON: metricsJSON,
            locale: locale,
            maximumFractionDigits: maximumFractionDigits,
            preset: preset
        )
    }

    private init(contractVersion: Int, metricsJSON: String, locale: String, maximumFractionDigits: Int, preset: String) {
        self.contractVersion = contractVersion; self.metricsJSON = metricsJSON
        centerLabel = ""; styleJSON = "{}"; legendJSON = "{}"; themeMode = "system"; self.preset = preset; themeJSON = "{}"; self.locale = locale; minimumFractionDigits = -1; self.maximumFractionDigits = maximumFractionDigits
        errorLabel = "Chart unavailable"; emptyLabel = "No data"; accessibilityLabel = "Progress chart"
        animated = true; onSelect = 0
    }
}

/// `@unchecked Sendable` because `NumberFormatter` is a class; it is configured once here and
/// only read afterwards, which Foundation documents as thread-safe.
struct NativePHPChartsProgressSnapshot: @unchecked Sendable {
    let availability: NativePHPChartsAvailability
    let metrics: [NativePHPChartsProgressMetric]
    let input: NativePHPChartsProgressWireInput
    let theme: NativePHPChartsTheme?
    let animationID: Int
    let style: NativePHPChartsProgressStyle
    let legend: NativePHPChartsLegendConfiguration
    /// The preset fallback palette, shared with every other chart type.
    let palette: [String]
    private let percentFormatter: NumberFormatter

    init(input: NativePHPChartsProgressWireInput) {
        self.input = input
        theme = NativePHPChartsTheme.decode(input.themeJSON)
        style = NativePHPChartsProgressStyle.decode(input.styleJSON)
        legend = NativePHPChartsLegendConfiguration.decode(input.legendJSON)
        palette = NativePHPChartsRuntime.palette(preset: input.preset)
        let formatter = NumberFormatter()
        formatter.numberStyle = .percent
        formatter.locale = input.locale.isEmpty ? .current : Locale(identifier: input.locale)
        if input.minimumFractionDigits >= 0 { formatter.minimumFractionDigits = input.minimumFractionDigits }
        if input.maximumFractionDigits >= 0 { formatter.maximumFractionDigits = input.maximumFractionDigits }
        percentFormatter = formatter
        let decoded = input.metricsJSON.data(using: .utf8).flatMap {
            try? JSONDecoder().decode([NativePHPChartsProgressMetric].self, from: $0)
        }
        if input.contractVersion != NativePHPChartsRuntime.supportedContractVersion {
            availability = .unsupportedContract; metrics = []
        } else if let decoded, Self.validate(decoded) {
            availability = .available; metrics = decoded
        } else {
            availability = .invalidPayload; metrics = []
        }
        var hasher = Hasher()
        metrics.forEach { hasher.combine($0.id); hasher.combine($0.value); hasher.combine($0.color) }
        animationID = hasher.finalize()
        NativePHPChartsRuntime.diagnose(availability, chartType: "progress")
    }

    @concurrent static func load(input: NativePHPChartsProgressWireInput) async -> Self { Self(input: input) }

    /// Formats a 0...1 progress value with the chart's `locale` and fraction-digit props.
    func formatted(_ value: Double) -> String {
        percentFormatter.string(from: NSNumber(value: value)) ?? value.formatted(.percent)
    }

    private static func validate(_ metrics: [NativePHPChartsProgressMetric]) -> Bool {
        Set(metrics.map(\.id)).count == metrics.count && metrics.allSatisfy {
            $0.id.isEmpty == false && $0.label.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty == false
                && $0.value.isFinite && (0...1).contains($0.value)
        }
    }
}

struct NativePHPChartsProgressStyle: Decodable, Sendable {
    struct Ring: Decodable, Sendable {
        let trackColor: String?
        let width: CGFloat?
        let gap: CGFloat?
        let cap: String?
        enum CodingKeys: String, CodingKey { case width, gap, cap; case trackColor = "track_color" }
    }
    let ring: Ring?

    static func decode(_ json: String) -> Self {
        guard let data = json.data(using: .utf8), let value = try? JSONDecoder().decode(Self.self, from: data) else {
            return Self(ring: nil)
        }
        return value
    }
}

enum NativePHPChartsProgressSelection {
    static func payload(metric: NativePHPChartsProgressMetric, localizedValue: String) -> NativePHPChartsSelectionPayload {
        NativePHPChartsSelectionPayload(
            chartType: "progress",
            seriesID: metric.id,
            seriesName: metric.label,
            pointID: metric.id,
            pointIndex: metric.sourceIndex,
            xType: "category",
            x: .string(metric.label),
            label: metric.label,
            value: metric.value,
            localizedValue: localizedValue
        )
    }
}

/// Ring geometry for the progress chart.
///
/// Rings keep their configured width and gap while they fit inside the available radius, so
/// charts with a few rings look unchanged. When many rings would overflow the center, width
/// and spacing shrink proportionally so every ring stays visible and tappable.
struct NativePHPChartsProgressRingLayout: Equatable {
    let ringWidth: CGFloat
    let ringStep: CGFloat
    let outerRadius: CGFloat

    init(count: Int, width: CGFloat, gap: CGFloat, size: CGSize) {
        let radius = max(min(size.width, size.height) / 2, 0)
        let step = width + gap
        // Distance from the outer edge of the first ring to the inner edge of the last one.
        let extent = CGFloat(max(count - 1, 0)) * step + width
        let available = radius * 0.9
        let scale = extent > available && extent > 0 ? available / extent : 1
        ringWidth = width * scale
        ringStep = step * scale
        outerRadius = radius - ringWidth / 2
    }

    func radius(ringAt index: Int) -> CGFloat {
        outerRadius - CGFloat(index) * ringStep
    }

    /// Radius of the empty hole inside the innermost ring.
    func innerRadius(count: Int) -> CGFloat {
        radius(ringAt: max(count - 1, 0)) - ringWidth / 2
    }

    /// Inset that places a full-size circle's stroke centerline on ring `index`.
    func inset(ringAt index: Int) -> CGFloat {
        CGFloat(index) * ringStep
    }
}

/// Loads progress snapshots off the main actor; a neutral placeholder covers only the first load.
struct NativePHPChartsProgressChartRenderer: View {
    let node: NativeUINode
    @State private var snapshot: NativePHPChartsProgressSnapshot?
    @State private var selectedID: String?

    private var wireInput: NativePHPChartsProgressWireInput { .init(node: node) }

    var body: some View {
        Group {
            if let snapshot {
                NativePHPChartsProgressChartView(nodeID: node.id, snapshot: snapshot, selectedID: $selectedID)
            } else {
                NativePHPChartsLoadingPlaceholder()
            }
        }
        .task(id: wireInput) {
            let updated = await NativePHPChartsProgressSnapshot.load(input: wireInput)
            guard Task.isCancelled == false else { return }
            snapshot = updated
            selectedID = updated.metrics.contains { $0.id == selectedID } ? selectedID : nil
        }
    }
}

private struct NativePHPChartsProgressChartView: View {
    let nodeID: Int
    let snapshot: NativePHPChartsProgressSnapshot
    @Binding var selectedID: String?
    @State private var revealProgress = 0.0
    @Environment(\.accessibilityReduceMotion) private var reduceMotion
    @Environment(\.colorScheme) private var colorScheme

    var body: some View {
        if snapshot.availability != .available { unavailableState }
        else if snapshot.metrics.isEmpty { emptyState }
        else { content }
    }

    private var content: some View {
        VStack(spacing: 12) {
            GeometryReader { geometry in
                let layout = ringLayout(in: geometry.size)
                ZStack {
                    ForEach(Array(snapshot.metrics.enumerated()), id: \.element.id) { index, metric in
                        let inset = layout.inset(ringAt: index)
                        Circle().stroke(trackColor, lineWidth: layout.ringWidth).padding(inset)
                        Circle()
                            .trim(from: 0, to: metric.value * revealProgress)
                            .stroke(color(for: metric, index: index), style: StrokeStyle(lineWidth: layout.ringWidth, lineCap: lineCap))
                            .rotationEffect(.degrees(-90)).padding(inset)
                            .opacity(selectedID == nil || selectedID == metric.id ? 1 : 0.48)
                    }
                    // The label is hidden once the innermost ring leaves too little room to read it.
                    if snapshot.input.centerLabel.isEmpty == false, layout.innerRadius(count: snapshot.metrics.count) >= 40 {
                        Text(snapshot.input.centerLabel).font(.headline).multilineTextAlignment(.center).lineLimit(2)
                    }
                }
                .contentShape(Rectangle())
                .gesture(SpatialTapGesture().onEnded { select(metric(at: $0.location, size: geometry.size, layout: layout)) })
            }
            .aspectRatio(1, contentMode: .fit)
            if snapshot.legend.visible ?? (snapshot.metrics.count > 1) { legend }
        }
        .foregroundStyle(foregroundColor)
        .background(color(variant?.background, fallback: .clear))
        .animation(animation, value: revealProgress)
        .task(id: snapshot.animationID) {
            revealProgress = reduceMotion || snapshot.input.animated == false ? 1 : 0
            guard revealProgress == 0 else { return }
            await Task.yield()
            withAnimation(animation) { revealProgress = 1 }
        }
        .accessibilityRepresentation {
            NativePHPChartsAccessibilityRepresentation(
                label: snapshot.input.accessibilityLabel,
                value: accessibilitySummary,
                actions: accessibilityActions,
                onSelect: select
            )
        }
    }

    /// Long legends scroll inside a fixed height so the rings keep most of the chart.
    @ViewBuilder
    private var legend: some View {
        if snapshot.metrics.count > 6 {
            ScrollView { legendRows }.frame(height: 132)
        } else {
            legendRows
        }
    }

    private var legendRows: some View {
        VStack(alignment: .leading, spacing: 6) {
            ForEach(Array(snapshot.metrics.enumerated()), id: \.element.id) { index, metric in
                HStack {
                    Circle().fill(color(for: metric, index: index)).frame(width: 8, height: 8)
                    Text(metric.label).lineLimit(1)
                    Spacer()
                    Text(formatted(metric.value))
                }
                .font(.caption)
            }
        }
    }

    private var variant: NativePHPChartsTheme.Variant? {
        snapshot.theme?.variant(mode: snapshot.input.themeMode, colorSchemeIsDark: colorScheme == .dark)
    }
    private var foregroundColor: Color { color(variant?.foreground, fallback: .primary) }
    private var trackColor: Color { color(snapshot.style.ring?.trackColor ?? variant?.track, fallback: .secondary.opacity(0.18)) }
    private func ringLayout(in size: CGSize) -> NativePHPChartsProgressRingLayout {
        NativePHPChartsProgressRingLayout(
            count: snapshot.metrics.count,
            width: snapshot.style.ring?.width ?? 12,
            gap: snapshot.style.ring?.gap ?? 8,
            size: size
        )
    }
    private var lineCap: CGLineCap {
        switch snapshot.style.ring?.cap { case "butt": .butt; case "square": .square; default: .round }
    }
    private var animation: Animation? { NativePHPChartsAnimation.resolved(enabled: snapshot.input.animated, reduceMotion: reduceMotion) }

    private func color(for metric: NativePHPChartsProgressMetric, index: Int) -> Color {
        let palette = variant?.palette ?? []
        let preset = snapshot.palette
        return color(metric.color ?? palette[safe: index] ?? preset[safe: index % max(preset.count, 1)], fallback: .indigo)
    }

    private func color(_ value: String?, fallback: Color) -> Color {
        value.map { Color(argb: ColorParser.parse($0, default: 0xFF6366F1)) } ?? fallback
    }

    private func metric(
        at point: CGPoint,
        size: CGSize,
        layout: NativePHPChartsProgressRingLayout
    ) -> NativePHPChartsProgressMetric? {
        let center = CGPoint(x: size.width / 2, y: size.height / 2)
        let distance = hypot(point.x - center.x, point.y - center.y)
        return snapshot.metrics.enumerated().min { lhs, rhs in
            abs(layout.radius(ringAt: lhs.offset) - distance) < abs(layout.radius(ringAt: rhs.offset) - distance)
        }.flatMap { abs(layout.radius(ringAt: $0.offset) - distance) <= 22 ? $0.element : nil }
    }

    private func select(_ metric: NativePHPChartsProgressMetric?) {
        guard selectedID != metric?.id else { return }
        selectedID = metric?.id
        guard let metric, snapshot.input.onSelect > 0,
              let json = NativePHPChartsProgressSelection.payload(
                  metric: metric,
                  localizedValue: formatted(metric.value)
              ).json()
        else { return }
        NativeElementBridge.sendTextChangeEvent(snapshot.input.onSelect, nodeId: nodeID, text: json)
    }

    private func formatted(_ value: Double) -> String { snapshot.formatted(value) }
    private var accessibilitySummary: String { snapshot.metrics.map { "\($0.label): \(formatted($0.value))" }.joined(separator: ". ") }
    private var accessibilityActions: [NativePHPChartsAccessibilityAction<NativePHPChartsProgressMetric>] {
        let index = snapshot.metrics.firstIndex { $0.id == selectedID }
        let previous = index.flatMap { $0 > 0 ? snapshot.metrics[$0 - 1] : nil }
        let next = index.map { $0 + 1 }.flatMap { $0 < snapshot.metrics.count ? snapshot.metrics[$0] : nil }
            ?? (index == nil ? snapshot.metrics.first : nil)
        return [(previous, NativePHPChartsAccessibilityAction<NativePHPChartsProgressMetric>.Direction.previous), (next, .next)].compactMap { metric, direction in
            metric.map { .init(dataID: snapshot.animationID, direction: direction, targetID: $0.id, label: "\($0.label), \(formatted($0.value))", target: $0) }
        }
    }

    private var emptyState: some View { ContentUnavailableView(snapshot.input.emptyLabel, systemImage: "chart.pie") }
    private var unavailableState: some View { ContentUnavailableView(snapshot.input.errorLabel, systemImage: "exclamationmark.triangle") }
}
