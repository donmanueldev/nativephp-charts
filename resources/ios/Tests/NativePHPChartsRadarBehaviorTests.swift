import XCTest
@testable import NativePHPChartsRendererCompileHarness

final class NativePHPChartsRadarBehaviorTests: XCTestCase {
    private let axesJSON = """
    [
      {"id":"speed","label":"Velocidad","maximum":100},
      {"id":"quality","label":"Calidad","maximum":10},
      {"id":"cost","label":"Costo","maximum":500}
    ]
    """

    private let seriesJSON = """
    [{
      "id":"nativephp","name":"NativePHP","color":"#6366F1",
      "values":[
        {"axis":"speed","value":88},
        {"axis":"quality","value":9},
        {"axis":"cost","value":220}
      ]
    }]
    """

    func testSnapshotConsumesCommonPresentationContract() {
        let snapshot = NativePHPChartsRadarSnapshot(
            input: NativePHPChartsRadarWireInput(
                axesJSON: axesJSON,
                seriesJSON: seriesJSON,
                styleJSON: """
                {"line":{"width":4,"dash":[3,2]},"area":{"opacity":0.4,"gradient":true},"points":{"visible":false,"size":9},"grid":{"visible":false,"width":2},"axis":{"visible":false,"font_size":13}}
                """,
                legendJSON: """
                {"visible":true,"position":"leading","alignment":"end","style":{"font_size":12,"marker_size":8}}
                """,
                animated: false,
                emptyLabel: "Sin datos",
                accessibilityLabel: "Comparación técnica",
                gridLevels: 7,
                fillOpacity: 0.2
            )
        )

        XCTAssertFalse(snapshot.isEmpty)
        XCTAssertFalse(snapshot.animated)
        XCTAssertEqual(snapshot.emptyLabel, "Sin datos")
        XCTAssertEqual(snapshot.accessibilityLabel, "Comparación técnica")
        XCTAssertEqual(snapshot.gridLevels, 7)
        XCTAssertEqual(snapshot.fillOpacity, 0.4)
        XCTAssertEqual(snapshot.style.line.width, 4)
        XCTAssertEqual(snapshot.style.line.dash ?? [], [3, 2])
        XCTAssertEqual(snapshot.style.points.visible, false)
        XCTAssertEqual(snapshot.style.points.size, 9)
        XCTAssertEqual(snapshot.style.grid.visible, false)
        XCTAssertEqual(snapshot.style.axis.visible, false)
        XCTAssertTrue(snapshot.legendVisible)
        XCTAssertEqual(snapshot.legend.position, "leading")
        XCTAssertEqual(snapshot.legend.alignment, "end")
    }

    func testEmptySeriesUsesEmptyStateSemantics() {
        let snapshot = NativePHPChartsRadarSnapshot(
            input: NativePHPChartsRadarWireInput(
                axesJSON: axesJSON,
                seriesJSON: "[]",
                emptyLabel: "Sin métricas",
                accessibilityLabel: "Radar de métricas"
            )
        )

        XCTAssertTrue(snapshot.isEmpty)
        XCTAssertEqual(snapshot.emptyLabel, "Sin métricas")
        XCTAssertEqual(snapshot.accessibilityLabel, "Radar de métricas")
        XCTAssertFalse(snapshot.legendVisible)
    }

    func testFormattingAndPayloadPreserveVersionOneShape() throws {
        let input = NativePHPChartsRadarWireInput(
            axesJSON: axesJSON,
            seriesJSON: seriesJSON,
            locale: "es-NI",
            valueFormat: "currency",
            currencyCode: "NIO",
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        )
        let snapshot = NativePHPChartsRadarSnapshot(input: input)
        let selection = try XCTUnwrap(snapshot.selections.first)
        let json = try XCTUnwrap(
            NativePHPChartsRadarSelectionPayload.json(selection: selection, formatter: snapshot.formatter)
        )
        let payload = try XCTUnwrap(JSONSerialization.jsonObject(with: Data(json.utf8)) as? [String: Any])

        XCTAssertEqual(Set(payload.keys), [
            "version", "chart_type", "series_id", "series_name", "point_id", "point_index",
            "x_type", "x", "label", "value", "localized_value",
        ])
        XCTAssertEqual(payload["version"] as? Int, 1)
        XCTAssertEqual(payload["chart_type"] as? String, "radar")
        XCTAssertEqual(payload["series_id"] as? String, "nativephp")
        XCTAssertEqual(payload["point_id"] as? String, "speed")
        XCTAssertEqual(payload["point_index"] as? Int, 0)
        XCTAssertEqual(payload["x_type"] as? String, "category")
        XCTAssertEqual(payload["x"] as? String, "speed")
        XCTAssertEqual(payload["label"] as? String, "Velocidad")
        XCTAssertEqual(payload["value"] as? Double, 88)
        XCTAssertEqual(payload["localized_value"] as? String, snapshot.formatter.value(88))
        XCTAssertTrue(snapshot.formatter.value(88).contains("88"))
    }

    func testAccessibleNavigationMovesThroughSeriesAndAxes() throws {
        let snapshot = NativePHPChartsRadarSnapshot(
            input: NativePHPChartsRadarWireInput(axesJSON: axesJSON, seriesJSON: seriesJSON, locale: "es-NI")
        )
        let first = try XCTUnwrap(snapshot.selections.first)
        let initial = NativePHPChartsRadarAccessibility.adjacent(to: nil, in: snapshot.selections)
        let afterFirst = NativePHPChartsRadarAccessibility.adjacent(to: first.id, in: snapshot.selections)

        XCTAssertNil(initial.previous)
        XCTAssertEqual(initial.next?.id, first.id)
        XCTAssertNil(afterFirst.previous)
        XCTAssertEqual(afterFirst.next?.axis.id, "quality")
        XCTAssertTrue(
            NativePHPChartsRadarAccessibility.summary(
                selections: snapshot.selections,
                formatter: snapshot.formatter,
                selected: afterFirst.next
            ).contains("Calidad")
        )
    }

    func testDenseAxisLabelsUseStableMarkersWithoutChangingFullSemanticLabels() {
        XCTAssertEqual(
            NativePHPChartsRadarAxisLabelLayout.displayLabel(label: "Fulfilment reliability", index: 0, axisCount: 3),
            "Fulfilment reliability"
        )
        XCTAssertEqual(
            NativePHPChartsRadarAxisLabelLayout.displayLabel(label: "Fulfilment reliability", index: 0, axisCount: 12),
            "Fulfilmen…"
        )
        XCTAssertEqual(
            NativePHPChartsRadarAxisLabelLayout.displayLabel(label: "Capability dimension 1", index: 0, axisCount: 24),
            "1"
        )
        XCTAssertNil(
            NativePHPChartsRadarAxisLabelLayout.displayLabel(label: "Capability dimension 2", index: 1, axisCount: 24)
        )
    }

    func testStepBeforeUsesTheDestinationXForTheClosingElbow() throws {
        let points = [
            CGPoint(x: 10, y: 10),
            CGPoint(x: 30, y: 20),
            CGPoint(x: 20, y: 40),
        ]

        let vertices = try XCTUnwrap(
            NativePHPChartsRadarPathGeometry.steppedVertices(points, interpolation: "step_before")
        )

        XCTAssertEqual(vertices, [
            CGPoint(x: 10, y: 10),
            CGPoint(x: 30, y: 10),
            CGPoint(x: 30, y: 20),
            CGPoint(x: 20, y: 20),
            CGPoint(x: 20, y: 40),
            CGPoint(x: 10, y: 40),
            CGPoint(x: 10, y: 10),
        ])
    }

    func testStepAfterUsesTheSourceXForTheClosingElbow() throws {
        let points = [
            CGPoint(x: 10, y: 10),
            CGPoint(x: 30, y: 20),
            CGPoint(x: 20, y: 40),
        ]

        let vertices = try XCTUnwrap(
            NativePHPChartsRadarPathGeometry.steppedVertices(points, interpolation: "step_after")
        )

        XCTAssertEqual(vertices, [
            CGPoint(x: 10, y: 10),
            CGPoint(x: 10, y: 20),
            CGPoint(x: 30, y: 20),
            CGPoint(x: 30, y: 40),
            CGPoint(x: 20, y: 40),
            CGPoint(x: 20, y: 10),
            CGPoint(x: 10, y: 10),
        ])
    }
}
