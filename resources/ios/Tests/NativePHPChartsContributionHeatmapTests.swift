import Foundation
import Testing
@testable import NativePHPChartsRendererCompileHarness

struct NativePHPChartsContributionHeatmapTests {
    @Test("Month and weekday labels use the chart locale")
    func localizedLabels() throws {
        let snapshot = NativePHPChartsContributionSnapshot(input: .testing(
            valuesJSON: #"[{"id":"d","date":"2026-03-02","value":1}]"#,
            endDate: "2026-03-31",
            days: 31,
            weekStartsOn: 1,
            locale: "es-ES"
        ))
        let firstDay = try #require(snapshot.grid.first)

        #expect(firstDay.monthLabel?.lowercased().hasPrefix("mar") == true)
        // Monday-first week: rows 1, 3, 5 are Tuesday, Thursday, Saturday in Spanish.
        #expect([1, 3, 5].map { snapshot.rowWeekdayLabels[$0] } == ["M", "J", "S"])
    }

    @Test("Every weekday row has a label even when the first column starts with blanks")
    func weekdayLabelsAnchorToRows() throws {
        // 2026-03-07 is a Saturday, so a Sunday-first grid starts with six blank cells.
        let snapshot = NativePHPChartsContributionSnapshot(input: .testing(
            valuesJSON: #"[{"id":"d","date":"2026-03-07","value":1}]"#,
            endDate: "2026-03-20",
            days: 14,
            weekStartsOn: 0,
            locale: "en-US"
        ))

        #expect(snapshot.grid.first?.row == 6)
        #expect(snapshot.rowWeekdayLabels.count == 7)
        #expect([1, 3, 5].allSatisfy { snapshot.rowWeekdayLabels[$0].isEmpty == false })
    }

    @Test("A partial first month drops its label when the next month starts within two columns")
    func crowdedLeadingMonthLabel() {
        // 2026-05-20 is a Wednesday: a Wednesday-first grid reaches June in its second column.
        let crowded = NativePHPChartsContributionSnapshot(input: .testing(
            valuesJSON: #"[{"id":"d","date":"2026-09-16","value":1}]"#,
            endDate: "2026-09-16", days: 120, weekStartsOn: 3, locale: "en-US"
        ))
        let roomy = NativePHPChartsContributionSnapshot(input: .testing(
            valuesJSON: #"[{"id":"d","date":"2026-09-16","value":1}]"#,
            endDate: "2026-09-16", days: 120, weekStartsOn: 1, locale: "en-US"
        ))

        #expect(crowded.grid.first(where: { $0.monthLabel != nil })?.monthLabel == "Jun")
        #expect(roomy.grid.first?.monthLabel == "May")
        #expect(NativePHPChartsContributionSnapshot.labelsLeadingMonth(leading: 0, daysUntilNextMonth: 12) == false)
        #expect(NativePHPChartsContributionSnapshot.labelsLeadingMonth(leading: 2, daysUntilNextMonth: 12))
    }

    @Test("Zero days use the empty color and only the maximum reaches the last scale color")
    func zeroDaysUseEmptyColor() {
        #expect(NativePHPChartsContributionScale.colorIndex(value: 0, maximum: 0.5, count: 5) == nil)
        #expect(NativePHPChartsContributionScale.colorIndex(value: 0, maximum: 0, count: 5) == nil)
        #expect(NativePHPChartsContributionScale.colorIndex(value: 0.1, maximum: 0.5, count: 5) == 0)
        #expect(NativePHPChartsContributionScale.colorIndex(value: 0.5, maximum: 0.5, count: 5) == 4)
    }

    @Test("Blank days are never drawn as selected")
    func blankDaysAreNotSelected() throws {
        let snapshot = NativePHPChartsContributionSnapshot(input: .testing(
            valuesJSON: #"[{"id":"d","date":"2026-03-07","value":1}]"#,
            endDate: "2026-03-20",
            days: 14,
            weekStartsOn: 0,
            locale: "en-US"
        ))
        let value = try #require(snapshot.values.first)

        #expect(NativePHPChartsContributionSelection.isSelected(nil, selectedID: nil) == false)
        #expect(NativePHPChartsContributionSelection.isSelected(nil, selectedID: "d") == false)
        #expect(NativePHPChartsContributionSelection.isSelected(value, selectedID: nil) == false)
        #expect(NativePHPChartsContributionSelection.isSelected(value, selectedID: "d"))
    }

    @Test("Visibility and the color-scale maximum are resolved once per snapshot")
    func precomputedSummary() {
        let visible = NativePHPChartsContributionSnapshot(input: .testing(
            valuesJSON: #"[{"id":"a","date":"2026-12-30","value":2},{"id":"b","date":"2025-01-01","value":9}]"#
        ))
        let outside = NativePHPChartsContributionSnapshot(input: .testing(
            valuesJSON: #"[{"id":"b","date":"2025-01-01","value":9}]"#
        ))

        #expect(visible.hasVisibleValues)
        #expect(visible.maximumValue == 9)
        #expect(outside.hasVisibleValues == false)
    }

    @Test("The default end date is today in the device calendar, not in UTC")
    func defaultEndDateUsesDeviceCalendar() throws {
        var losAngeles = Calendar(identifier: .gregorian)
        losAngeles.timeZone = try #require(TimeZone(identifier: "America/Los_Angeles"))
        // 2026-03-10 20:00 in Los Angeles is already 2026-03-11 in UTC.
        let evening = try #require(losAngeles.date(from: DateComponents(year: 2026, month: 3, day: 10, hour: 20)))

        let today = NativePHPChartsContributionSnapshot.today(now: evening, calendar: losAngeles)

        #expect(NativePHPChartsContributionSnapshot.dateString(today) == "2026-03-10")
    }
}
