import SwiftUI

struct NativePHPChartsLineChartRenderer: View {
    let node: NativeUINode

    var body: some View {
        NativePHPChartsRenderer(node: node, kind: .line)
    }
}
