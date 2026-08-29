import SwiftUI

struct NativePHPChartsAngularRevealShape: Shape {
    var progress: CGFloat

    var animatableData: CGFloat {
        get { progress }
        set { progress = newValue }
    }

    func path(in rect: CGRect) -> Path {
        let boundedProgress = min(max(progress, 0), 1)
        guard boundedProgress > 0 else { return Path() }

        let center = CGPoint(x: rect.midX, y: rect.midY)
        let radius = hypot(rect.width, rect.height)
        var path = Path()
        path.move(to: center)
        path.addArc(
            center: center,
            radius: radius,
            startAngle: .degrees(-90),
            endAngle: .degrees(-90 + (360 * boundedProgress)),
            clockwise: false
        )
        path.closeSubpath()
        return path
    }
}
