import SwiftUI

struct NativePHPChartsDonutChartRenderer: View {
    let node: NativeUINode

    var body: some View {
        NativePHPChartsRadialRenderer(node: node, kind: .donut)
    }
}
