import SwiftUI

struct NativePHPChartsScatterChartRenderer: View {
    let node: NativeUINode

    var body: some View {
        NativePHPChartsRenderer(node: node, kind: .scatter)
    }
}
