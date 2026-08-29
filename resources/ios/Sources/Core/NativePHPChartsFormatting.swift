import Foundation

final class NativePHPChartsFormatter {
    private let xAxis: NativePHPChartsAxisConfiguration
    private let locale: Locale
    private let timezone: TimeZone
    private let xNumberFormatter: NumberFormatter
    private let yNumberFormatter: NumberFormatter
    private let dateFormatter: DateFormatter
    private let dateOnlyParser: DateFormatter
    private let dateTimeParser: ISO8601DateFormatter
    private let fractionalDateTimeParser: ISO8601DateFormatter

    init(input: NativePHPChartsWireInput, configuration: NativePHPChartsConfiguration) {
        xAxis = configuration.xAxis
        locale = input.locale.isEmpty ? .current : Locale(identifier: input.locale)
        timezone = TimeZone(identifier: configuration.xAxis.timezone) ?? .current
        xNumberFormatter = NativePHPChartsFormatter.makeNumberFormatter(
            locale: locale,
            configuration: configuration.xAxis.format ?? NativePHPChartsNumberFormat()
        )
        yNumberFormatter = NativePHPChartsFormatter.makeNumberFormatter(
            locale: locale,
            configuration: configuration.yAxis.format ?? NativePHPChartsNumberFormat(
                style: input.valueFormat,
                currencyCode: input.currencyCode.isEmpty ? nil : input.currencyCode,
                minimumFractionDigits: input.minimumFractionDigits >= 0 ? input.minimumFractionDigits : nil,
                maximumFractionDigits: input.maximumFractionDigits >= 0 ? input.maximumFractionDigits : nil
            )
        )
        dateFormatter = NativePHPChartsFormatter.makeDateFormatter(
            locale: locale,
            timezone: timezone,
            preset: configuration.xAxis.dateFormat,
            includesTime: configuration.xAxis.type == .datetime
        )
        dateOnlyParser = DateFormatter()
        dateOnlyParser.locale = Locale(identifier: "en_US_POSIX")
        dateOnlyParser.calendar = Calendar(identifier: .gregorian)
        dateOnlyParser.timeZone = timezone
        dateOnlyParser.dateFormat = "yyyy-MM-dd"
        dateOnlyParser.isLenient = false
        dateTimeParser = ISO8601DateFormatter()
        dateTimeParser.timeZone = timezone
        dateTimeParser.formatOptions = [.withInternetDateTime]
        fractionalDateTimeParser = ISO8601DateFormatter()
        fractionalDateTimeParser.timeZone = timezone
        fractionalDateTimeParser.formatOptions = [.withInternetDateTime, .withFractionalSeconds]
    }

    func y(_ value: Double) -> String {
        yNumberFormatter.string(from: value as NSNumber) ?? String(value)
    }

    func x(_ plotX: Double, data: NativePHPChartsDataSet) -> String {
        switch xAxis.type {
        case .category:
            return data.categoryLabels[Int(plotX.rounded())] ?? ""
        case .number:
            return xNumberFormatter.string(from: plotX as NSNumber) ?? String(plotX)
        case .date, .datetime:
            return dateFormatter.string(from: Date(timeIntervalSince1970: plotX))
        }
    }

    func x(point: NativePHPChartsPoint, data: NativePHPChartsDataSet) -> String {
        point.label.isEmpty ? x(point.plotX, data: data) : point.label
    }

    func date(from value: NativePHPChartsWireValue, type: NativePHPChartsXAxisType) -> Date? {
        switch value {
        case let .number(number):
            return Date(timeIntervalSince1970: number)
        case let .string(string):
            if type == .date {
                return dateOnlyParser.date(from: string)
            }

            if let parsed = fractionalDateTimeParser.date(from: string) {
                return parsed
            }

            return dateTimeParser.date(from: string)
        }
    }

    static func makeNumberFormatter(
        locale: Locale,
        configuration: NativePHPChartsNumberFormat
    ) -> NumberFormatter {
        let formatter = NumberFormatter()
        formatter.locale = locale
        formatter.numberStyle = switch configuration.style {
        case "currency": .currency
        case "percent": .percent
        default: .decimal
        }

        if let currencyCode = configuration.currencyCode, !currencyCode.isEmpty {
            formatter.currencyCode = currencyCode
        }
        if let minimum = configuration.minimumFractionDigits, minimum >= 0 {
            formatter.minimumFractionDigits = minimum
        }
        if let maximum = configuration.maximumFractionDigits, maximum >= 0 {
            formatter.maximumFractionDigits = maximum
        }

        return formatter
    }

    private static func makeDateFormatter(
        locale: Locale,
        timezone: TimeZone,
        preset: String,
        includesTime: Bool
    ) -> DateFormatter {
        let formatter = DateFormatter()
        formatter.locale = locale
        formatter.timeZone = timezone

        switch preset {
        case "time":
            formatter.timeStyle = .short
        case "short":
            formatter.dateStyle = .short
        case "long":
            formatter.dateStyle = .long
        case "full":
            formatter.dateStyle = .full
        default:
            formatter.dateStyle = .medium
        }

        if includesTime {
            formatter.timeStyle = .short
        }

        return formatter
    }
}
