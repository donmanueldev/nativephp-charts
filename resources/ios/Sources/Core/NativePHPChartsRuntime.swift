import Foundation
import CryptoKit
import OSLog

enum NativePHPChartsAvailability: Equatable, Sendable {
    case available
    case unsupportedContract
    case invalidPayload
    case unavailable
}

enum NativePHPChartsRuntime {
    static let supportedContractVersion = 1
    private static let logger = Logger(subsystem: "dev.donmanuel.nativephp-charts", category: "runtime")

    static func diagnose(_ availability: NativePHPChartsAvailability, chartType: String) {
        guard availability != .available else { return }
        logger.error("chart unavailable type=\(chartType, privacy: .public) reason=\(String(describing: availability), privacy: .public)")
    }

    static func palette(preset: String) -> [String] {
        switch preset {
        case "spectrum": ["#6366F1", "#06B6D4", "#22C55E", "#F59E0B", "#EF4444", "#A855F7"]
        case "contrast": ["#0057B8", "#D1495B", "#00876C", "#7A5195", "#A15C00"]
        default: ["#6366F1", "#06B6D4", "#22C55E", "#F59E0B", "#EF4444"]
        }
    }
}

/// Bounded cache for immutable, content-addressed file payloads.
///
/// Entries are capped both by count and UTF-8 cost. No chart contents, paths, labels, or values
/// are logged. The actor owns all mutation and returns immutable strings across the boundary.
actor NativePHPChartsPayloadCache {
    static let shared = NativePHPChartsPayloadCache()

    private let maximumEntries = 64
    private let maximumCost = 8 * 1_024 * 1_024
    private var values: [String: String] = [:]
    private var costs: [String: Int] = [:]
    private var order: [String] = []
    private var totalCost = 0

    func value(for path: String) -> String? {
        guard let value = values[path] else { return nil }
        order.removeAll { $0 == path }
        order.append(path)
        return value
    }

    func insert(_ value: String, for path: String) {
        let cost = value.utf8.count
        guard cost <= maximumCost else { return }

        if let previous = costs[path] { totalCost -= previous }
        values[path] = value
        costs[path] = cost
        totalCost += cost
        order.removeAll { $0 == path }
        order.append(path)

        while order.count > maximumEntries || totalCost > maximumCost {
            guard let oldest = order.first else { break }
            order.removeFirst()
            values.removeValue(forKey: oldest)
            totalCost -= costs.removeValue(forKey: oldest) ?? 0
        }
    }
}

/// Bounded cache for the expensive JSON-to-wire-model stage of large Cartesian snapshots.
actor NativePHPChartsDecodedSeriesCache {
    static let shared = NativePHPChartsDecodedSeriesCache()
    private let maximumEntries = 32
    private let maximumPoints = 100_000
    private var values: [String: [NativePHPChartsWireSeries]] = [:]
    private var costs: [String: Int] = [:]
    private var order: [String] = []
    private var totalPoints = 0

    func series(for json: String) -> [NativePHPChartsWireSeries]? {
        let data = Data(json.utf8)
        let key = SHA256.hash(data: data).map { String(format: "%02x", $0) }.joined()
        if let cached = values[key] {
            order.removeAll { $0 == key }; order.append(key)
            return cached
        }
        guard let decoded = try? JSONDecoder().decode([NativePHPChartsWireSeries].self, from: data) else { return nil }
        let cost = decoded.reduce(0) { $0 + $1.points.count }
        guard cost <= maximumPoints else { return decoded }
        values[key] = decoded; costs[key] = cost; totalPoints += cost; order.append(key)
        while order.count > maximumEntries || totalPoints > maximumPoints {
            guard let oldest = order.first else { break }
            order.removeFirst(); values.removeValue(forKey: oldest)
            totalPoints -= costs.removeValue(forKey: oldest) ?? 0
        }
        return decoded
    }
}

enum NativePHPChartsPayloadLoader {
    @concurrent
    static func seriesJSON(for input: NativePHPChartsWireInput) async throws -> String {
        guard input.seriesTransport == "file-v1" else { return input.seriesJSON }
        guard let path = input.seriesJSONFile, path.isEmpty == false else {
            throw CocoaError(.fileNoSuchFile)
        }
        if let cached = await NativePHPChartsPayloadCache.shared.value(for: path) {
            return cached
        }

        try Task.checkCancellation()
        let data = try Data(contentsOf: URL(fileURLWithPath: path), options: [.mappedIfSafe])
        try Task.checkCancellation()
        guard let value = String(data: data, encoding: .utf8) else {
            throw CocoaError(.fileReadInapplicableStringEncoding)
        }
        await NativePHPChartsPayloadCache.shared.insert(value, for: path)
        return value
    }
}

struct NativePHPChartsTheme: Decodable, Sendable {
    struct Variant: Decodable, Sendable {
        let background: String?
        let foreground: String?
        let muted: String?
        let grid: String?
        let track: String?
        let error: String?
        let palette: [String]

        init(from decoder: Decoder) throws {
            let container = try decoder.container(keyedBy: CodingKeys.self)
            background = try container.decodeIfPresent(String.self, forKey: .background)
            foreground = try container.decodeIfPresent(String.self, forKey: .foreground)
            muted = try container.decodeIfPresent(String.self, forKey: .muted)
            grid = try container.decodeIfPresent(String.self, forKey: .grid)
            track = try container.decodeIfPresent(String.self, forKey: .track)
            error = try container.decodeIfPresent(String.self, forKey: .error)
            palette = try container.decodeIfPresent([String].self, forKey: .palette) ?? []
        }

        private enum CodingKeys: String, CodingKey { case background, foreground, muted, grid, track, error, palette }
    }

    let light: Variant?
    let dark: Variant?

    static func decode(_ json: String) -> NativePHPChartsTheme? {
        guard let data = json.data(using: .utf8) else { return nil }
        return try? JSONDecoder().decode(Self.self, from: data)
    }

    func variant(mode: String, colorSchemeIsDark: Bool) -> Variant? {
        switch mode {
        case "light": light ?? dark
        case "dark": dark ?? light
        default: colorSchemeIsDark ? (dark ?? light) : (light ?? dark)
        }
    }
}
