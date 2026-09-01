import SwiftUI

struct NativePHPChartsCandlestickChartRenderer: View {
    let node: NativeUINode

    var body: some View {
        NativePHPChartsRenderer(node: node, kind: .candlestick)
    }
}
