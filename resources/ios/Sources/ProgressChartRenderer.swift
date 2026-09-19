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

    static func testing(contractVersion: Int = 1, metricsJSON: String) -> Self {
        Self(contractVersion: contractVersion, metricsJSON: metricsJSON)
    }

    private init(contractVersion: Int, metricsJSON: String) {
        self.contractVersion = contractVersion; self.metricsJSON = metricsJSON
        centerLabel = ""; styleJSON = "{}"; legendJSON = "{}"; themeMode = "system"; preset = "default"; themeJSON = "{}"; locale = ""; minimumFractionDigits = -1; maximumFractionDigits = -1
        errorLabel = "Chart unavailable"; emptyLabel = "No data"; accessibilityLabel = "Progress chart"
        animated = true; onSelect = 0
    }
}

struct NativePHPChartsProgressSnapshot: @unchecked Sendable {
    let availability: NativePHPChartsAvailability
    let metrics: [NativePHPChartsProgressMetric]
    let input: NativePHPChartsProgressWireInput
    let theme: NativePHPChartsTheme?
    let animationID: Int
    let style: NativePHPChartsProgressStyle
    let legend: NativePHPChartsLegendConfiguration

    init(input: NativePHPChartsProgressWireInput) {
        self.input = input
        theme = NativePHPChartsTheme.decode(input.themeJSON)
        style = NativePHPChartsProgressStyle.decode(input.styleJSON)
        legend = NativePHPChartsLegendConfiguration.decode(input.legendJSON)
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
    static func payload(metric: NativePHPChartsProgressMetric) -> NativePHPChartsSelectionPayload {
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
            localizedValue: metric.value.formatted(.percent)
        )
    }
}

struct NativePHPChartsProgressChartRenderer: View {
    let node: NativeUINode
    @State private var snapshot: NativePHPChartsProgressSnapshot
    @State private var selectedID: String?
    @State private var revealProgress = 0.0
    @Environment(\.accessibilityReduceMotion) private var reduceMotion
    @Environment(\.colorScheme) private var colorScheme

    init(node: NativeUINode) {
        self.node = node
        _snapshot = State(initialValue: .init(input: .testing(metricsJSON: "[]")))
    }

    private var wireInput: NativePHPChartsProgressWireInput { .init(node: node) }

    var body: some View {
        Group {
            if snapshot.availability != .available { unavailableState }
            else if snapshot.metrics.isEmpty { emptyState }
            else { content }
        }
        .task(id: wireInput) {
            let updated = await NativePHPChartsProgressSnapshot.load(input: wireInput)
            guard Task.isCancelled == false else { return }
            snapshot = updated
            selectedID = updated.metrics.contains { $0.id == selectedID } ? selectedID : nil
        }
    }

    private var content: some View {
        VStack(spacing: 12) {
            GeometryReader { geometry in
                ZStack {
                    ForEach(Array(snapshot.metrics.enumerated()), id: \.element.id) { index, metric in
                        let inset = CGFloat(index) * ringStep
                        Circle().stroke(trackColor, lineWidth: ringWidth).padding(inset)
                        Circle()
                            .trim(from: 0, to: metric.value * revealProgress)
                            .stroke(color(for: metric, index: index), style: StrokeStyle(lineWidth: ringWidth, lineCap: lineCap))
                            .rotationEffect(.degrees(-90)).padding(inset)
                            .opacity(selectedID == nil || selectedID == metric.id ? 1 : 0.48)
                    }
                    if snapshot.input.centerLabel.isEmpty == false {
                        Text(snapshot.input.centerLabel).font(.headline).multilineTextAlignment(.center)
                    }
                }
                .contentShape(Rectangle())
                .gesture(SpatialTapGesture().onEnded { select(metric(at: $0.location, size: geometry.size)) })
            }
            .aspectRatio(1, contentMode: .fit)
            if snapshot.legend.visible ?? (snapshot.metrics.count > 1) { legend }
        }
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

    private var legend: some View {
        VStack(alignment: .leading, spacing: 6) {
            ForEach(Array(snapshot.metrics.enumerated()), id: \.element.id) { index, metric in
                HStack { Circle().fill(color(for: metric, index: index)).frame(width: 8, height: 8); Text(metric.label); Spacer(); Text(metric.value, format: .percent.precision(.fractionLength(0))) }
                    .font(.caption)
            }
        }
    }

    private var variant: NativePHPChartsTheme.Variant? {
        snapshot.theme?.variant(mode: snapshot.input.themeMode, colorSchemeIsDark: colorScheme == .dark)
    }
    private var trackColor: Color { color(snapshot.style.ring?.trackColor ?? variant?.track, fallback: .secondary.opacity(0.18)) }
    private var ringWidth: CGFloat { snapshot.style.ring?.width ?? 12 }
    private var ringStep: CGFloat { ringWidth + (snapshot.style.ring?.gap ?? 8) }
    private var lineCap: CGLineCap {
        switch snapshot.style.ring?.cap { case "butt": .butt; case "square": .square; default: .round }
    }
    private var animation: Animation? { NativePHPChartsAnimation.resolved(enabled: snapshot.input.animated, reduceMotion: reduceMotion) }

    private func color(for metric: NativePHPChartsProgressMetric, index: Int) -> Color {
        let palette = variant?.palette ?? []
        let preset = snapshot.input.preset == "contrast" ? ["#0057B8", "#D1495B", "#00876C", "#7A5195"] : ["#6366F1", "#06B6D4", "#22C55E", "#F59E0B"]
        return color(metric.color ?? palette[safe: index] ?? preset[index % preset.count], fallback: .indigo)
    }

    private func color(_ value: String?, fallback: Color) -> Color {
        value.map { Color(argb: ColorParser.parse($0, default: 0xFF6366F1)) } ?? fallback
    }

    private func metric(at point: CGPoint, size: CGSize) -> NativePHPChartsProgressMetric? {
        let center = CGPoint(x: size.width / 2, y: size.height / 2)
        let distance = hypot(point.x - center.x, point.y - center.y)
        let outerRadius = min(size.width, size.height) / 2 - ringWidth / 2
        return snapshot.metrics.enumerated().min { lhs, rhs in
            abs((outerRadius - CGFloat(lhs.offset) * ringStep) - distance) < abs((outerRadius - CGFloat(rhs.offset) * ringStep) - distance)
        }.flatMap { abs((outerRadius - CGFloat($0.offset) * ringStep) - distance) <= 22 ? $0.element : nil }
    }

    private func select(_ metric: NativePHPChartsProgressMetric?) {
        guard selectedID != metric?.id else { return }
        selectedID = metric?.id
        guard let metric, snapshot.input.onSelect > 0,
              let json = NativePHPChartsProgressSelection.payload(metric: metric).json()
        else { return }
        NativeElementBridge.sendTextChangeEvent(snapshot.input.onSelect, nodeId: node.id, text: json)
    }

    private var formatter: NumberFormatter {
        let formatter = NumberFormatter(); formatter.numberStyle = .percent
        formatter.locale = snapshot.input.locale.isEmpty ? .current : Locale(identifier: snapshot.input.locale)
        if snapshot.input.minimumFractionDigits >= 0 { formatter.minimumFractionDigits = snapshot.input.minimumFractionDigits }
        if snapshot.input.maximumFractionDigits >= 0 { formatter.maximumFractionDigits = snapshot.input.maximumFractionDigits }
        return formatter
    }
    private func formatted(_ value: Double) -> String { formatter.string(from: NSNumber(value: value)) ?? value.formatted(.percent) }
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

private extension Array {
    subscript(safe index: Int) -> Element? { indices.contains(index) ? self[index] : nil }
}
