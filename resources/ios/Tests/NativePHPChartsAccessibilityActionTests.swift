import XCTest
@testable import NativePHPChartsRendererCompileHarness

final class NativePHPChartsAccessibilityActionTests: XCTestCase {
    func testActionsAreReplacedWhenTheDataSnapshotChanges() {
        let original = NativePHPChartsAccessibilityAction(
            dataID: 10,
            direction: .next,
            targetID: "series:point-0",
            label: "Temperature, 2 PM, 30.8",
            target: "temperature"
        )
        let updated = NativePHPChartsAccessibilityAction(
            dataID: 11,
            direction: .next,
            targetID: "series:point-0",
            label: "Pressure, Muestra 0, 49",
            target: "pressure"
        )

        XCTAssertNotEqual(original.id, updated.id)
        XCTAssertEqual(updated.label, "Pressure, Muestra 0, 49")
        XCTAssertEqual(updated.target, "pressure")
    }

    func testPreviousAndNextActionsHaveDistinctIdentities() {
        let previous = NativePHPChartsAccessibilityAction(
            dataID: 10,
            direction: .previous,
            targetID: "series:point-1",
            label: "Previous",
            target: 1
        )
        let next = NativePHPChartsAccessibilityAction(
            dataID: 10,
            direction: .next,
            targetID: "series:point-1",
            label: "Next",
            target: 1
        )

        XCTAssertNotEqual(previous.id, next.id)
    }

    func testActionIsReplacedWhenSelectionMovesWithinTheSameDataSet() {
        let first = NativePHPChartsAccessibilityAction(
            dataID: 10,
            direction: .next,
            targetID: "series:point-0",
            label: "Temperature, 2 PM, 30.8",
            target: 0
        )
        let second = NativePHPChartsAccessibilityAction(
            dataID: 10,
            direction: .next,
            targetID: "series:point-1",
            label: "Temperature, 3 PM, 29.6",
            target: 1
        )

        XCTAssertNotEqual(first.id, second.id)
        XCTAssertEqual(second.label, "Temperature, 3 PM, 29.6")
        XCTAssertEqual(second.target, 1)
    }

    @MainActor
    func testRepresentationIsReplacedWhenAccessibleContentChanges() {
        let temperature = NativePHPChartsAccessibilityAction(
            dataID: 10,
            direction: .next,
            targetID: "temperature:point-0",
            label: "Temperature, 2 PM, 30.8",
            target: "temperature"
        )
        let latency = NativePHPChartsAccessibilityAction(
            dataID: 11,
            direction: .next,
            targetID: "latency:point-0",
            label: "Latency, 06:00, 151",
            target: "latency"
        )
        let original = NativePHPChartsAccessibilityRepresentation(
            label: "Hourly temperature in Managua",
            value: "Temperature. 2 PM: 30.8",
            actions: [temperature],
            onSelect: { _ in }
        )
        let updated = NativePHPChartsAccessibilityRepresentation(
            label: "Operations latency with a datetime viewport",
            value: "Latency. 06:00: 151",
            actions: [latency],
            onSelect: { _ in }
        )

        XCTAssertNotEqual(original.identity, updated.identity)
        XCTAssertEqual(updated.identity.actions.map(\.label), ["Latency, 06:00, 151"])
    }

    @MainActor
    func testRepresentationIsReplacedWhenFormattingChangesWithoutChangingData() {
        let action = NativePHPChartsAccessibilityAction(
            dataID: 10,
            direction: .next,
            targetID: "pressure:point-0",
            label: "Pressure, Sample 0, 49",
            target: "pressure"
        )
        let english = NativePHPChartsAccessibilityRepresentation(
            label: "Sensor pressure",
            value: "Pressure. Sample 0: 49",
            actions: [action],
            onSelect: { _ in }
        )
        let spanish = NativePHPChartsAccessibilityRepresentation(
            label: "Presión del sensor",
            value: "Pressure. Muestra 0: 49",
            actions: [action],
            onSelect: { _ in }
        )

        XCTAssertNotEqual(english.identity, spanish.identity)
    }
}
