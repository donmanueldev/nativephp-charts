import Foundation

final class NativePHPChartsRadialFormatter {
    private let numberFormatter: NumberFormatter

    init(input: NativePHPChartsRadialWireInput) {
        let locale = input.locale.isEmpty ? Locale.current : Locale(identifier: input.locale)
        let configuration = NativePHPChartsNumberFormat(
            style: input.valueFormat,
            currencyCode: input.currencyCode.isEmpty ? nil : input.currencyCode,
            minimumFractionDigits: input.minimumFractionDigits >= 0 ? input.minimumFractionDigits : nil,
            maximumFractionDigits: input.maximumFractionDigits >= 0 ? input.maximumFractionDigits : nil
        )
        numberFormatter = NativePHPChartsFormatter.makeNumberFormatter(
            locale: locale,
            configuration: configuration
        )
    }

    func value(_ value: Double) -> String {
        numberFormatter.string(from: value as NSNumber) ?? String(value)
    }
}
