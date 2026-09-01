import SwiftUI

struct NativePHPChartsAccessibilityAction<Target>: Identifiable {
    struct ID: Hashable {
        let dataID: Int
        let direction: Direction
        let targetID: String
    }

    enum Direction: String, Hashable {
        case previous
        case next
    }

    let dataID: Int
    let direction: Direction
    let targetID: String
    let label: String
    let target: Target

    var id: ID {
        ID(dataID: dataID, direction: direction, targetID: targetID)
    }
}

struct NativePHPChartsAccessibilityRepresentation<Target>: View {
    struct Identity: Hashable {
        struct Action: Hashable {
            let id: NativePHPChartsAccessibilityAction<Target>.ID
            let label: String
        }

        let label: String
        let value: String
        let actions: [Action]
    }

    let label: String
    let value: String
    let actions: [NativePHPChartsAccessibilityAction<Target>]
    let onSelect: (Target) -> Void

    var identity: Identity {
        Identity(
            label: label,
            value: value,
            actions: actions.map { Identity.Action(id: $0.id, label: $0.label) }
        )
    }

    var body: some View {
        Text(value)
            .accessibilityElement(children: .ignore)
            .accessibilityLabel(label)
            .accessibilityValue(value)
            .accessibilityActions {
                ForEach(actions) { action in
                    Button(action.label) {
                        onSelect(action.target)
                    }
                }
            }
            .id(identity)
    }
}
