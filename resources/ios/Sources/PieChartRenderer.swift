import SwiftUI

struct NativePHPChartsPieChartRenderer: View {
    let node: NativeUINode

    var body: some View {
        NativePHPChartsRadialRenderer(node: node, kind: .pie)
    }
}
