import Foundation

/// Locale-aware value formatter shared by the pie, donut, and radar renderers.
final class NativePHPChartsRadialFormatter {
    private let numberFormatter: NumberFormatter

    init(
        locale: String,
        valueFormat: String,
        currencyCode: String,
        minimumFractionDigits: Int,
        maximumFractionDigits: Int
    ) {
        numberFormatter = NativePHPChartsFormatter.makeNumberFormatter(
            locale: locale.isEmpty ? .current : Locale(identifier: locale),
            configuration: NativePHPChartsNumberFormat(
                style: valueFormat,
                currencyCode: currencyCode.isEmpty ? nil : currencyCode,
                minimumFractionDigits: minimumFractionDigits >= 0 ? minimumFractionDigits : nil,
                maximumFractionDigits: maximumFractionDigits >= 0 ? maximumFractionDigits : nil
            )
        )
    }

    convenience init(input: NativePHPChartsRadialWireInput) {
        self.init(
            locale: input.locale,
            valueFormat: input.valueFormat,
            currencyCode: input.currencyCode,
            minimumFractionDigits: input.minimumFractionDigits,
            maximumFractionDigits: input.maximumFractionDigits
        )
    }

    convenience init(input: NativePHPChartsRadarWireInput) {
        self.init(
            locale: input.locale,
            valueFormat: input.valueFormat,
            currencyCode: input.currencyCode,
            minimumFractionDigits: input.minimumFractionDigits,
            maximumFractionDigits: input.maximumFractionDigits
        )
    }

    func value(_ value: Double) -> String {
        numberFormatter.string(from: value as NSNumber) ?? String(value)
    }
}
