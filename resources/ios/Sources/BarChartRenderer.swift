import SwiftUI

struct NativePHPChartsBarChartRenderer: View {
    let node: NativeUINode

    var body: some View {
        NativePHPChartsRenderer(node: node, kind: .bar)
    }
}
