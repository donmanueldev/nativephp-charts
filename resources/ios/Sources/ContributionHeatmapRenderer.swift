import SwiftUI

struct NativePHPChartsContributionValue: Decodable, Identifiable, Sendable {
    let id: String
    let date: String
    let value: Double
    let label: String?
    let color: String?
    let sourceIndex: Int

    private enum CodingKeys: String, CodingKey { case id, date, value, label, color }
    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        id = try container.decode(String.self, forKey: .id)
        date = try container.decode(String.self, forKey: .date)
        value = try container.decode(Double.self, forKey: .value)
        label = try container.decodeIfPresent(String.self, forKey: .label)
        color = try container.decodeIfPresent(String.self, forKey: .color)
        sourceIndex = decoder.codingPath.last?.intValue ?? 0
    }

    init(id: String, date: String, value: Double, label: String?, color: String? = nil, sourceIndex: Int) {
        self.id = id; self.date = date; self.value = value; self.label = label; self.color = color; self.sourceIndex = sourceIndex
    }
}

struct NativePHPChartsContributionWireInput: Equatable, Sendable {
    let contractVersion: Int
    let valuesJSON: String
    let styleJSON: String
    let endDate: String
    let days: Int
    let weekStartsOn: Int
    let colorsJSON: String
    let emptyColor: String
    let showMonthLabels: Bool
    let showWeekdayLabels: Bool
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
        valuesJSON = node.props.getString("values_json", default: "[]")
        styleJSON = node.props.getString("style_json", default: "{}")
        endDate = node.props.getString("end_date", default: "")
        days = node.props.getInt("days", default: 365)
        weekStartsOn = node.props.getInt("week_starts_on", default: 0)
        colorsJSON = node.props.getString("colors_json", default: "[]")
        emptyColor = node.props.getString("empty_color", default: "")
        showMonthLabels = node.props.getBool("show_month_labels", default: true)
        showWeekdayLabels = node.props.getBool("show_weekday_labels", default: true)
        themeMode = node.props.getString("theme_mode", default: "system")
        preset = node.props.getString("preset", default: "default")
        themeJSON = node.props.getString("theme_json", default: "{}")
        locale = node.props.getString("locale", default: "")
        minimumFractionDigits = node.props.getInt("minimum_fraction_digits", default: -1)
        maximumFractionDigits = node.props.getInt("maximum_fraction_digits", default: -1)
        errorLabel = node.props.getString("error_label", default: "Chart unavailable")
        emptyLabel = node.props.getString("empty_label", default: "No data")
        accessibilityLabel = node.props.getString("a11y_label", default: "Contribution heatmap")
        animated = node.props.getBool("animated", default: true)
        onSelect = node.props.getInt("on_select", default: 0)
    }

    static func testing(contractVersion: Int = 1, valuesJSON: String) -> Self {
        Self(contractVersion: contractVersion, valuesJSON: valuesJSON)
    }

    private init(contractVersion: Int, valuesJSON: String) {
        self.contractVersion = contractVersion; self.valuesJSON = valuesJSON
        styleJSON = "{}"; endDate = "2026-12-31"; days = 365; weekStartsOn = 0; colorsJSON = "[]"; emptyColor = ""
        showMonthLabels = true; showWeekdayLabels = true; themeMode = "system"; preset = "default"; themeJSON = "{}"; locale = ""; minimumFractionDigits = -1; maximumFractionDigits = -1
        errorLabel = "Chart unavailable"; emptyLabel = "No data"; accessibilityLabel = "Contribution heatmap"
        animated = true; onSelect = 0
    }
}

struct NativePHPChartsContributionSnapshot: @unchecked Sendable {
    let availability: NativePHPChartsAvailability
    let values: [NativePHPChartsContributionValue]
    let valuesByDate: [String: NativePHPChartsContributionValue]
    let input: NativePHPChartsContributionWireInput
    let colors: [String]
    let theme: NativePHPChartsTheme?
    let endDate: Date?
    let animationID: Int
    let style: NativePHPChartsContributionStyle

    init(input: NativePHPChartsContributionWireInput) {
        self.input = input
        theme = NativePHPChartsTheme.decode(input.themeJSON)
        style = NativePHPChartsContributionStyle.decode(input.styleJSON)
        colors = input.colorsJSON.data(using: .utf8).flatMap { try? JSONDecoder().decode([String].self, from: $0) } ?? []
        let decoded = input.valuesJSON.data(using: .utf8).flatMap { try? JSONDecoder().decode([NativePHPChartsContributionValue].self, from: $0) }
        let parsedEnd = input.endDate.isEmpty ? Self.todayUTC() : Self.parseDate(input.endDate)
        endDate = parsedEnd
        if input.contractVersion != NativePHPChartsRuntime.supportedContractVersion {
            availability = .unsupportedContract; values = []
        } else if let decoded, parsedEnd != nil, (7...371).contains(input.days), (0...6).contains(input.weekStartsOn), Self.validate(decoded) {
            availability = .available; values = decoded
        } else {
            availability = .invalidPayload; values = []
        }
        valuesByDate = Dictionary(uniqueKeysWithValues: values.map { ($0.date, $0) })
        var hasher = Hasher()
        values.forEach { hasher.combine($0.id); hasher.combine($0.date); hasher.combine($0.value); hasher.combine($0.color) }
        animationID = hasher.finalize()
        NativePHPChartsRuntime.diagnose(availability, chartType: "contribution_heatmap")
    }

    @concurrent static func load(input: NativePHPChartsContributionWireInput) async -> Self { Self(input: input) }

    static func parseDate(_ value: String) -> Date? {
        guard value.range(of: #"^\d{4}-\d{2}-\d{2}$"#, options: .regularExpression) != nil else { return nil }
        var calendar = Calendar(identifier: .gregorian); calendar.timeZone = TimeZone(secondsFromGMT: 0)!
        let parts = value.split(separator: "-").compactMap { Int($0) }
        guard parts.count == 3 else { return nil }
        guard let date = calendar.date(from: DateComponents(year: parts[0], month: parts[1], day: parts[2])),
              dateString(date) == value
        else { return nil }
        return date
    }

    static func dateString(_ date: Date) -> String {
        var calendar = Calendar(identifier: .gregorian); calendar.timeZone = TimeZone(secondsFromGMT: 0)!
        let components = calendar.dateComponents([.year, .month, .day], from: date)
        return String(format: "%04d-%02d-%02d", components.year ?? 0, components.month ?? 0, components.day ?? 0)
    }

    private static func todayUTC() -> Date {
        var calendar = Calendar(identifier: .gregorian); calendar.timeZone = TimeZone(secondsFromGMT: 0)!
        return calendar.startOfDay(for: Date())
    }

    private static func validate(_ values: [NativePHPChartsContributionValue]) -> Bool {
        Set(values.map(\.id)).count == values.count && Set(values.map(\.date)).count == values.count && values.allSatisfy {
            $0.id.isEmpty == false && $0.value.isFinite && $0.value >= 0 && parseDate($0.date) != nil
        }
    }
}

struct NativePHPChartsContributionStyle: Decodable {
    struct Cell: Decodable {
        let size: CGFloat?
        let gap: CGFloat?
        let cornerRadius: CGFloat?
        enum CodingKeys: String, CodingKey { case size, gap; case cornerRadius = "corner_radius" }
    }
    let cell: Cell?
    let axis: NativePHPChartsStyle.Axis?

    static func decode(_ json: String) -> Self {
        guard let data = json.data(using: .utf8), let value = try? JSONDecoder().decode(Self.self, from: data) else {
            return Self(cell: nil, axis: nil)
        }
        return value
    }
}

enum NativePHPChartsContributionSelection {
    static func payload(value: NativePHPChartsContributionValue, localizedValue: String? = nil) -> NativePHPChartsSelectionPayload {
        let label = value.label ?? value.date
        return NativePHPChartsSelectionPayload(
            chartType: "contribution_heatmap",
            seriesID: value.id,
            seriesName: label,
            pointID: value.id,
            pointIndex: value.sourceIndex,
            xType: "date",
            x: .string(value.date),
            label: label,
            value: value.value,
            localizedValue: localizedValue ?? value.value.formatted()
        )
    }
}

struct NativePHPChartsContributionHeatmapRenderer: View {
    let node: NativeUINode
    @State private var snapshot: NativePHPChartsContributionSnapshot
    @State private var selectedID: String?
    @State private var revealProgress = 0.0
    @Environment(\.accessibilityReduceMotion) private var reduceMotion
    @Environment(\.colorScheme) private var colorScheme

    init(node: NativeUINode) {
        self.node = node
        _snapshot = State(initialValue: .init(input: .testing(valuesJSON: "[]")))
    }
    private var wireInput: NativePHPChartsContributionWireInput { .init(node: node) }

    var body: some View {
        Group {
            if snapshot.availability != .available { unavailableState }
            else if hasVisibleValues == false { emptyState }
            else { content }
        }
        .task(id: wireInput) {
            let updated = await NativePHPChartsContributionSnapshot.load(input: wireInput)
            guard Task.isCancelled == false else { return }
            snapshot = updated
            selectedID = updated.values.contains { $0.id == selectedID } ? selectedID : nil
        }
    }

    private var content: some View {
        GeometryReader { geometry in
            let layout = cells(in: geometry.size)
            Canvas { context, _ in
                for cell in layout {
                    let fill = color(for: cell.value)
                    let radius = min(snapshot.style.cell?.cornerRadius ?? 3, cell.frame.width / 2)
                    context.fill(Path(roundedRect: cell.frame, cornerRadius: radius), with: .color(fill.opacity(cell.dayOffsetFraction <= revealProgress ? 1 : 0.12)))
                    if cell.value?.id == selectedID { context.stroke(Path(roundedRect: cell.frame.insetBy(dx: -1, dy: -1), cornerRadius: radius), with: .color(.primary), lineWidth: 2) }
                    if snapshot.input.showMonthLabels, cell.isFirstOfMonth {
                        context.draw(Text(cell.monthLabel).font(.caption2).foregroundStyle(axisLabelColor), at: CGPoint(x: cell.frame.minX, y: 6), anchor: .leading)
                    }
                    if snapshot.input.showWeekdayLabels, cell.column == 0, [1, 3, 5].contains(cell.row) {
                        context.draw(Text(cell.weekdayLabel).font(.caption2).foregroundStyle(axisLabelColor), at: CGPoint(x: 0, y: cell.frame.midY), anchor: .leading)
                    }
                }
            }
            .contentShape(Rectangle())
            .gesture(SpatialTapGesture().onEnded { tap in
                select(layout.first { $0.frame.insetBy(dx: -3, dy: -3).contains(tap.location) }?.value)
            })
        }
        .frame(minHeight: 150)
        .animation(NativePHPChartsAnimation.resolved(enabled: snapshot.input.animated, reduceMotion: reduceMotion), value: revealProgress)
        .task(id: snapshot.animationID) {
            revealProgress = reduceMotion || snapshot.input.animated == false ? 1 : 0
            guard revealProgress == 0 else { return }
            await Task.yield()
            withAnimation { revealProgress = 1 }
        }
        .accessibilityRepresentation {
            NativePHPChartsAccessibilityRepresentation(label: snapshot.input.accessibilityLabel, value: accessibilitySummary, actions: accessibilityActions, onSelect: select)
        }
    }

    private var hasVisibleValues: Bool {
        guard let end = snapshot.endDate else { return false }
        var calendar = Calendar(identifier: .gregorian); calendar.timeZone = TimeZone(secondsFromGMT: 0)!
        guard let start = calendar.date(byAdding: .day, value: -(snapshot.input.days - 1), to: end) else { return false }
        return snapshot.values.contains { value in
            NativePHPChartsContributionSnapshot.parseDate(value.date).map { $0 >= start && $0 <= end } ?? false
        }
    }

    private struct Cell {
        let frame: CGRect
        let value: NativePHPChartsContributionValue?
        let dayOffsetFraction: Double
        let column: Int
        let row: Int
        let isFirstOfMonth: Bool
        let monthLabel: String
        let weekdayLabel: String
    }

    private func cells(in size: CGSize) -> [Cell] {
        guard let end = snapshot.endDate else { return [] }
        var calendar = Calendar(identifier: .gregorian); calendar.timeZone = TimeZone(secondsFromGMT: 0)!
        let start = calendar.date(byAdding: .day, value: -(snapshot.input.days - 1), to: end)!
        let weekday = calendar.component(.weekday, from: start) - 1
        let leading = (weekday - snapshot.input.weekStartsOn + 7) % 7
        let columns = Int(ceil(Double(leading + snapshot.input.days) / 7.0))
        let labelWidth: CGFloat = snapshot.input.showWeekdayLabels ? 28 : 0
        let labelHeight: CGFloat = snapshot.input.showMonthLabels ? 18 : 0
        let gap: CGFloat = snapshot.style.cell?.gap ?? 3
        let available = max(4, min((size.width - labelWidth - CGFloat(max(columns - 1, 0)) * gap) / CGFloat(max(columns, 1)), (size.height - labelHeight - 6 * gap) / 7))
        let cell = min(snapshot.style.cell?.size ?? available, available)
        return (0..<snapshot.input.days).compactMap { offset in
            guard let date = calendar.date(byAdding: .day, value: offset, to: start) else { return nil }
            let position = leading + offset
            let column = position / 7; let row = position % 7
            let frame = CGRect(x: labelWidth + CGFloat(column) * (cell + gap), y: labelHeight + CGFloat(row) * (cell + gap), width: cell, height: cell)
            let day = calendar.component(.day, from: date)
            let month = calendar.shortMonthSymbols[max(calendar.component(.month, from: date) - 1, 0)]
            let weekdayLabel = calendar.veryShortWeekdaySymbols[calendar.component(.weekday, from: date) - 1]
            return Cell(frame: frame, value: snapshot.valuesByDate[NativePHPChartsContributionSnapshot.dateString(date)], dayOffsetFraction: Double(offset + 1) / Double(snapshot.input.days), column: column, row: row, isFirstOfMonth: day == 1 || offset == 0, monthLabel: month, weekdayLabel: weekdayLabel)
        }
    }

    private var variant: NativePHPChartsTheme.Variant? { snapshot.theme?.variant(mode: snapshot.input.themeMode, colorSchemeIsDark: colorScheme == .dark) }
    private var axisLabelColor: Color { parsed(snapshot.style.axis?.labelColor ?? variant?.muted, fallback: .secondary) }
    private func color(for value: NativePHPChartsContributionValue?) -> Color {
        guard let value else { return parsed(snapshot.input.emptyColor.isEmpty ? variant?.track : snapshot.input.emptyColor, fallback: .secondary.opacity(0.15)) }
        if let explicit = value.color { return parsed(explicit, fallback: .green) }
        let palette = snapshot.colors.isEmpty ? (variant?.palette ?? []) : snapshot.colors
        let resolved = palette.isEmpty ? ["#DCFCE7", "#86EFAC", "#22C55E", "#15803D"] : palette
        let maxValue = snapshot.values.map(\.value).max() ?? 0
        let index = maxValue > 0 ? min(Int((value.value / maxValue) * Double(resolved.count - 1)), resolved.count - 1) : 0
        return parsed(resolved[index], fallback: .green)
    }
    private func parsed(_ value: String?, fallback: Color) -> Color { value.map { Color(argb: ColorParser.parse($0, default: 0xFF22C55E)) } ?? fallback }

    private func select(_ value: NativePHPChartsContributionValue?) {
        guard selectedID != value?.id else { return }
        selectedID = value?.id
        guard let value, snapshot.input.onSelect > 0,
              let json = NativePHPChartsContributionSelection.payload(value: value, localizedValue: formatted(value.value)).json()
        else { return }
        NativeElementBridge.sendTextChangeEvent(snapshot.input.onSelect, nodeId: node.id, text: json)
    }

    private var formatter: NumberFormatter {
        let formatter = NumberFormatter(); formatter.numberStyle = .decimal
        formatter.locale = snapshot.input.locale.isEmpty ? .current : Locale(identifier: snapshot.input.locale)
        if snapshot.input.minimumFractionDigits >= 0 { formatter.minimumFractionDigits = snapshot.input.minimumFractionDigits }
        if snapshot.input.maximumFractionDigits >= 0 { formatter.maximumFractionDigits = snapshot.input.maximumFractionDigits }
        return formatter
    }
    private func formatted(_ value: Double) -> String { formatter.string(from: NSNumber(value: value)) ?? value.formatted() }
    private var accessibilitySummary: String { snapshot.values.prefix(31).map { "\($0.label ?? $0.date): \(formatted($0.value))" }.joined(separator: ". ") }
    private var accessibilityActions: [NativePHPChartsAccessibilityAction<NativePHPChartsContributionValue>] {
        let index = snapshot.values.firstIndex { $0.id == selectedID }
        let previous = index.flatMap { $0 > 0 ? snapshot.values[$0 - 1] : nil }
        let next = index.map { $0 + 1 }.flatMap { $0 < snapshot.values.count ? snapshot.values[$0] : nil } ?? (index == nil ? snapshot.values.first : nil)
        return [(previous, NativePHPChartsAccessibilityAction<NativePHPChartsContributionValue>.Direction.previous), (next, .next)].compactMap { value, direction in
            value.map { .init(dataID: snapshot.animationID, direction: direction, targetID: $0.id, label: "\($0.label ?? $0.date), \(formatted($0.value))", target: $0) }
        }
    }

    private var emptyState: some View { ContentUnavailableView(snapshot.input.emptyLabel, systemImage: "square.grid.3x3") }
    private var unavailableState: some View { ContentUnavailableView(snapshot.input.errorLabel, systemImage: "exclamationmark.triangle") }
}

// Keep compatibility with either renderer naming convention while the manifest remains source compatible.
typealias NativePHPChartsContributionHeatmapChartRenderer = NativePHPChartsContributionHeatmapRenderer
