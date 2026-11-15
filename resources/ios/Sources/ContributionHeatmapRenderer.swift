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

    static func testing(
        contractVersion: Int = 1,
        valuesJSON: String,
        endDate: String = "2026-12-31",
        days: Int = 365,
        weekStartsOn: Int = 0,
        locale: String = ""
    ) -> Self {
        Self(
            contractVersion: contractVersion,
            valuesJSON: valuesJSON,
            endDate: endDate,
            days: days,
            weekStartsOn: weekStartsOn,
            locale: locale
        )
    }

    private init(contractVersion: Int, valuesJSON: String, endDate: String, days: Int, weekStartsOn: Int, locale: String) {
        self.contractVersion = contractVersion; self.valuesJSON = valuesJSON
        styleJSON = "{}"; self.endDate = endDate; self.days = days; self.weekStartsOn = weekStartsOn; colorsJSON = "[]"; emptyColor = ""
        showMonthLabels = true; showWeekdayLabels = true; themeMode = "system"; preset = "default"; themeJSON = "{}"; self.locale = locale; minimumFractionDigits = -1; maximumFractionDigits = -1
        errorLabel = "Chart unavailable"; emptyLabel = "No data"; accessibilityLabel = "Contribution heatmap"
        animated = true; onSelect = 0
    }
}

/// One immutable heatmap render transaction.
///
/// Everything that does not depend on layout size (the day grid, month and weekday labels in
/// the chart locale, the color-scale maximum, and the number formatter) is resolved here once,
/// so `body` only lays out frames on reveal-animation ticks and selection changes.
/// `@unchecked Sendable` because `NumberFormatter` is a class that is only read after init.
struct NativePHPChartsContributionSnapshot: @unchecked Sendable {
    /// A grid position in the visible date window, in chronological order.
    struct Day {
        let offset: Int
        let column: Int
        let row: Int
        let value: NativePHPChartsContributionValue?
        /// Short month name on the first visible day and on each first of the month.
        let monthLabel: String?
    }

    let availability: NativePHPChartsAvailability
    let values: [NativePHPChartsContributionValue]
    let input: NativePHPChartsContributionWireInput
    let colors: [String]
    let theme: NativePHPChartsTheme?
    let animationID: Int
    let style: NativePHPChartsContributionStyle
    let grid: [Day]
    let columns: Int
    /// Very short weekday symbols indexed by grid row, honoring `week_starts_on` and `locale`.
    let rowWeekdayLabels: [String]
    let maximumValue: Double
    let hasVisibleValues: Bool
    private let numberFormatter: NumberFormatter

    init(input: NativePHPChartsContributionWireInput) {
        self.input = input
        theme = NativePHPChartsTheme.decode(input.themeJSON)
        style = NativePHPChartsContributionStyle.decode(input.styleJSON)
        colors = input.colorsJSON.data(using: .utf8).flatMap { try? JSONDecoder().decode([String].self, from: $0) } ?? []
        let decoded = input.valuesJSON.data(using: .utf8).flatMap { try? JSONDecoder().decode([NativePHPChartsContributionValue].self, from: $0) }
        let parsedEnd = input.endDate.isEmpty ? Self.today() : Self.parseDate(input.endDate)
        if input.contractVersion != NativePHPChartsRuntime.supportedContractVersion {
            availability = .unsupportedContract; values = []
        } else if let decoded, parsedEnd != nil, (7...371).contains(input.days), (0...6).contains(input.weekStartsOn), Self.validate(decoded) {
            availability = .available; values = decoded
        } else {
            availability = .invalidPayload; values = []
        }
        let valuesByDate = Dictionary(uniqueKeysWithValues: values.map { ($0.date, $0) })
        var hasher = Hasher()
        values.forEach { hasher.combine($0.id); hasher.combine($0.date); hasher.combine($0.value); hasher.combine($0.color) }
        animationID = hasher.finalize()

        let locale = input.locale.isEmpty ? Locale.current : Locale(identifier: input.locale)
        numberFormatter = NativePHPChartsFormatter.makeNumberFormatter(
            locale: locale,
            configuration: NativePHPChartsNumberFormat(
                minimumFractionDigits: input.minimumFractionDigits >= 0 ? input.minimumFractionDigits : nil,
                maximumFractionDigits: input.maximumFractionDigits >= 0 ? input.maximumFractionDigits : nil
            )
        )
        maximumValue = values.map(\.value).max() ?? 0

        var calendar = Self.utcCalendar
        calendar.locale = locale
        let weekdaySymbols = calendar.veryShortWeekdaySymbols
        let monthSymbols = calendar.shortMonthSymbols
        rowWeekdayLabels = (0..<7).map { (row: Int) -> String in
            let weekday: Int = (input.weekStartsOn + row) % 7
            return weekdaySymbols[safe: weekday] ?? ""
        }

        if availability == .available, let end = parsedEnd,
           let start = calendar.date(byAdding: .day, value: -(input.days - 1), to: end)
        {
            let leading = (calendar.component(.weekday, from: start) - 1 - input.weekStartsOn + 7) % 7
            columns = Int(ceil(Double(leading + input.days) / 7.0))
            // A partial first month is labelled only when its label cannot run into the next month's.
            let daysInStartMonth = calendar.range(of: .day, in: .month, for: start)?.count ?? 31
            let daysUntilNextMonth = daysInStartMonth - calendar.component(.day, from: start) + 1
            let labelsLeadingMonth = Self.labelsLeadingMonth(leading: leading, daysUntilNextMonth: daysUntilNextMonth)
            grid = (0..<input.days).compactMap { (offset: Int) -> Day? in
                guard let date = calendar.date(byAdding: .day, value: offset, to: start) else { return nil }
                let position = leading + offset
                let components = calendar.dateComponents([.year, .month, .day], from: date)
                let key = String(format: "%04d-%02d-%02d", components.year ?? 0, components.month ?? 0, components.day ?? 0)
                let isFirstOfMonth = components.day == 1 || (offset == 0 && labelsLeadingMonth)
                return Day(
                    offset: offset,
                    column: position / 7,
                    row: position % 7,
                    value: valuesByDate[key],
                    monthLabel: isFirstOfMonth ? monthSymbols[safe: (components.month ?? 1) - 1] : nil
                )
            }
        } else {
            columns = 0
            grid = []
        }
        hasVisibleValues = grid.contains { $0.value != nil }
        NativePHPChartsRuntime.diagnose(availability, chartType: "contribution_heatmap")
    }

    func formatted(_ value: Double) -> String {
        numberFormatter.string(from: NSNumber(value: value)) ?? value.formatted()
    }

    /// The leading month keeps its label only when the next month starts at least two columns later.
    static func labelsLeadingMonth(leading: Int, daysUntilNextMonth: Int) -> Bool {
        (leading + daysUntilNextMonth) / 7 >= 2
    }

    private static var utcCalendar: Calendar {
        var calendar = Calendar(identifier: .gregorian)
        calendar.timeZone = TimeZone(secondsFromGMT: 0)!
        return calendar
    }

    @concurrent static func load(input: NativePHPChartsContributionWireInput) async -> Self { Self(input: input) }

    static func parseDate(_ value: String) -> Date? {
        guard value.range(of: #"^\d{4}-\d{2}-\d{2}$"#, options: .regularExpression) != nil else { return nil }
        let parts = value.split(separator: "-").compactMap { Int($0) }
        guard parts.count == 3 else { return nil }
        guard let date = utcCalendar.date(from: DateComponents(year: parts[0], month: parts[1], day: parts[2])),
              dateString(date) == value
        else { return nil }
        return date
    }

    static func dateString(_ date: Date) -> String {
        let components = utcCalendar.dateComponents([.year, .month, .day], from: date)
        return String(format: "%04d-%02d-%02d", components.year ?? 0, components.month ?? 0, components.day ?? 0)
    }

    /// Today's calendar date on the device, expressed as the UTC midnight the grid math uses.
    ///
    /// Using the device calendar keeps users west of UTC from seeing tomorrow's column in the
    /// evening when PHP leaves `end_date` empty.
    static func today(now: Date = Date(), calendar deviceCalendar: Calendar = .current) -> Date {
        let components = deviceCalendar.dateComponents([.year, .month, .day], from: now)
        return utcCalendar.date(from: DateComponents(year: components.year, month: components.month, day: components.day))
            ?? utcCalendar.startOfDay(for: now)
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

enum NativePHPChartsContributionScale {
    /// Index into the color scale, or `nil` for a zero day, which shares the empty color with blank days on both platforms.
    static func colorIndex(value: Double, maximum: Double, count: Int) -> Int? {
        guard value > 0, maximum > 0, count > 0 else { return nil }
        return min(Int((value / maximum) * Double(count - 1)), count - 1)
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

    /// A blank day never counts as selected, even while nothing is selected.
    static func isSelected(_ value: NativePHPChartsContributionValue?, selectedID: String?) -> Bool {
        guard let id = value?.id else { return false }
        return id == selectedID
    }
}

/// Loads heatmap snapshots off the main actor; a neutral placeholder covers only the first load.
struct NativePHPChartsContributionHeatmapRenderer: View {
    let node: NativeUINode
    @State private var snapshot: NativePHPChartsContributionSnapshot?
    @State private var selectedID: String?

    private var wireInput: NativePHPChartsContributionWireInput { .init(node: node) }

    var body: some View {
        Group {
            if let snapshot {
                NativePHPChartsContributionHeatmapView(nodeID: node.id, snapshot: snapshot, selectedID: $selectedID)
            } else {
                NativePHPChartsLoadingPlaceholder()
                    .frame(minHeight: 150)
            }
        }
        .task(id: wireInput) {
            let updated = await NativePHPChartsContributionSnapshot.load(input: wireInput)
            guard Task.isCancelled == false else { return }
            snapshot = updated
            selectedID = updated.values.contains { $0.id == selectedID } ? selectedID : nil
        }
    }
}

private struct NativePHPChartsContributionHeatmapView: View {
    let nodeID: Int
    let snapshot: NativePHPChartsContributionSnapshot
    @Binding var selectedID: String?
    @State private var revealProgress = 0.0
    @Environment(\.accessibilityReduceMotion) private var reduceMotion
    @Environment(\.colorScheme) private var colorScheme

    var body: some View {
        if snapshot.availability != .available { unavailableState }
        else if snapshot.hasVisibleValues == false { emptyState }
        else { content }
    }

    private var content: some View {
        GeometryReader { geometry in
            let metrics = gridMetrics(in: geometry.size)
            let layout = cells(metrics: metrics)
            Canvas { context, _ in
                let emptyFill = emptyColor
                let scale = colorScale
                let labelColor = axisLabelColor
                for cell in layout {
                    let fill = color(for: cell.value, empty: emptyFill, scale: scale)
                    let radius = min(snapshot.style.cell?.cornerRadius ?? 3, cell.frame.width / 2)
                    context.fill(Path(roundedRect: cell.frame, cornerRadius: radius), with: .color(fill.opacity(cell.dayOffsetFraction <= revealProgress ? 1 : 0.12)))
                    if NativePHPChartsContributionSelection.isSelected(cell.value, selectedID: selectedID) { context.stroke(Path(roundedRect: cell.frame.insetBy(dx: 1, dy: 1), cornerRadius: radius), with: .color(.primary), lineWidth: 2) }
                    if snapshot.input.showMonthLabels, let monthLabel = cell.monthLabel {
                        context.draw(Text(monthLabel).font(.caption2).foregroundStyle(labelColor), at: CGPoint(x: cell.frame.minX, y: 6), anchor: .leading)
                    }
                }
                if snapshot.input.showWeekdayLabels {
                    // Anchored to the row, not to a cell, so leading blanks never hide a label.
                    for row in [1, 3, 5] {
                        context.draw(
                            Text(snapshot.rowWeekdayLabels[row]).font(.caption2).foregroundStyle(labelColor),
                            at: CGPoint(x: 0, y: metrics.rowMidY(row)),
                            anchor: .leading
                        )
                    }
                }
            }
            .contentShape(Rectangle())
            .gesture(SpatialTapGesture().onEnded { tap in
                select(layout.first { $0.frame.insetBy(dx: -3, dy: -3).contains(tap.location) }?.value)
            })
        }
        .frame(minHeight: 150)
        .background(parsed(variant?.background, fallback: .clear))
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

    private struct GridMetrics {
        let labelWidth: CGFloat
        let labelHeight: CGFloat
        let cell: CGFloat
        let gap: CGFloat

        func frame(column: Int, row: Int) -> CGRect {
            CGRect(x: labelWidth + CGFloat(column) * (cell + gap), y: labelHeight + CGFloat(row) * (cell + gap), width: cell, height: cell)
        }

        func rowMidY(_ row: Int) -> CGFloat {
            labelHeight + CGFloat(row) * (cell + gap) + cell / 2
        }
    }

    private struct Cell {
        let frame: CGRect
        let value: NativePHPChartsContributionValue?
        let dayOffsetFraction: Double
        let monthLabel: String?
    }

    private func gridMetrics(in size: CGSize) -> GridMetrics {
        let columns = snapshot.columns
        let labelWidth: CGFloat = snapshot.input.showWeekdayLabels ? 28 : 0
        let labelHeight: CGFloat = snapshot.input.showMonthLabels ? 18 : 0
        let gap: CGFloat = snapshot.style.cell?.gap ?? 3
        let available = max(4, min((size.width - labelWidth - CGFloat(max(columns - 1, 0)) * gap) / CGFloat(max(columns, 1)), (size.height - labelHeight - 6 * gap) / 7))
        let cell = min(snapshot.style.cell?.size ?? available, available)
        return GridMetrics(labelWidth: labelWidth, labelHeight: labelHeight, cell: cell, gap: gap)
    }

    private func cells(metrics: GridMetrics) -> [Cell] {
        let days = Double(snapshot.input.days)
        return snapshot.grid.map { day in
            Cell(
                frame: metrics.frame(column: day.column, row: day.row),
                value: day.value,
                dayOffsetFraction: Double(day.offset + 1) / days,
                monthLabel: day.monthLabel
            )
        }
    }

    private var variant: NativePHPChartsTheme.Variant? { snapshot.theme?.variant(mode: snapshot.input.themeMode, colorSchemeIsDark: colorScheme == .dark) }
    private var axisLabelColor: Color { parsed(snapshot.style.axis?.labelColor ?? variant?.muted, fallback: .secondary) }
    private var emptyColor: Color {
        parsed(snapshot.input.emptyColor.isEmpty ? variant?.track : snapshot.input.emptyColor, fallback: .secondary.opacity(0.15))
    }
    private var colorScale: [String] {
        let palette = snapshot.colors.isEmpty ? (variant?.palette ?? []) : snapshot.colors
        return palette.isEmpty ? ["#DCFCE7", "#86EFAC", "#22C55E", "#15803D"] : palette
    }
    private func color(for value: NativePHPChartsContributionValue?, empty: Color, scale: [String]) -> Color {
        guard let value else { return empty }
        if let explicit = value.color { return parsed(explicit, fallback: .green) }
        return NativePHPChartsContributionScale.colorIndex(value: value.value, maximum: snapshot.maximumValue, count: scale.count)
            .map { parsed(scale[$0], fallback: .green) } ?? empty
    }
    private func parsed(_ value: String?, fallback: Color) -> Color { value.map { Color(argb: ColorParser.parse($0, default: 0xFF22C55E)) } ?? fallback }

    private func select(_ value: NativePHPChartsContributionValue?) {
        guard selectedID != value?.id else { return }
        selectedID = value?.id
        guard let value, snapshot.input.onSelect > 0,
              let json = NativePHPChartsContributionSelection.payload(value: value, localizedValue: formatted(value.value)).json()
        else { return }
        NativeElementBridge.sendTextChangeEvent(snapshot.input.onSelect, nodeId: nodeID, text: json)
    }

    private func formatted(_ value: Double) -> String { snapshot.formatted(value) }
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
