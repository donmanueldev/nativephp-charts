import SwiftUI

final class NativePHPChartsTestProps: Equatable {
    static func == (lhs: NativePHPChartsTestProps, rhs: NativePHPChartsTestProps) -> Bool {
        lhs === rhs
    }

    func getString(_ key: String, default defaultValue: String = "") -> String {
        defaultValue
    }

    func getInt(_ key: String, default defaultValue: Int = 0) -> Int {
        defaultValue
    }

    func getFloat(_ key: String, default defaultValue: Float = 0) -> Float {
        defaultValue
    }

    func getBool(_ key: String, default defaultValue: Bool = false) -> Bool {
        defaultValue
    }
}

final class NativeUINode: Equatable {
    let id = 1
    let props = NativePHPChartsTestProps()

    static func == (lhs: NativeUINode, rhs: NativeUINode) -> Bool {
        lhs === rhs
    }
}

enum NativeElementBridge {
    static func sendTextChangeEvent(_ callback: Int, nodeId: Int, text: String) {}
}

enum NativeUIFontResolver {
    static func font(_ token: String, size: CGFloat) -> Font? {
        nil
    }
}

enum ColorParser {
    static func parse(_ value: String, default defaultValue: Int) -> Int {
        defaultValue
    }
}

extension Color {
    init(argb: Int) {
        let value = UInt32(bitPattern: Int32(truncatingIfNeeded: argb))
        self.init(
            .sRGB,
            red: Double((value >> 16) & 0xFF) / 255,
            green: Double((value >> 8) & 0xFF) / 255,
            blue: Double(value & 0xFF) / 255,
            opacity: Double((value >> 24) & 0xFF) / 255
        )
    }
}
