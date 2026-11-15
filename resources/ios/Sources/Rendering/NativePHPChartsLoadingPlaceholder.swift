import SwiftUI

/// Neutral stand-in shown until a chart's first snapshot finishes loading.
///
/// It fills the space the chart will occupy, so the first real frame causes no layout shift,
/// and it is hidden from assistive technologies so VoiceOver never announces a transient
/// empty state. Later reloads keep the previous snapshot visible instead of returning here.
struct NativePHPChartsLoadingPlaceholder: View {
    var body: some View {
        Color.clear
            .frame(maxWidth: .infinity, maxHeight: .infinity)
            .accessibilityHidden(true)
    }
}
