import SwiftUI

struct NativePHPChartsAreaChartRenderer: View {
    let node: NativeUINode

    var body: some View {
        NativePHPChartsRenderer(node: node, kind: .area)
    }
}
