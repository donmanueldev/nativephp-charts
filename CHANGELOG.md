# Changelog

All notable changes to NativePHP Charts are documented here. The project follows Semantic Versioning.

## [Unreleased]

### Added

- Native progress and contribution heatmap charts with selection, reduced-motion behavior, empty/error states, and accessible summaries.
- Semantic `light`, `dark`, and `system` themes with built-in and application-defined presets.
- Versioned Android and generated-shell iOS behavior harnesses, plus an Astro/Starlight documentation application.
- A versioned physical-performance evidence schema and regression comparator for latency, memory, payload, slow-frame, and callback measurements.
- A fail-closed Android physical recorder that correlates Logcat, Perfetto FrameTimeline, and `TOTAL RSS` across five cold runs per density.
- A deterministic installed-app Android gallery and capture command for all ten chart families.

### Changed

- Moved native payload reads and decoding off the UI thread while preserving the last valid snapshot during replacement loading.
- Reduced Android solid-linear HWUI geometry at sub-pixel density while preserving per-column extrema and the complete selection snapshot.
- Made explicit series, segment, and metric colors optional so semantic palettes can supply stable defaults.
- Extended the version 1 `PointSelection` contract with `progress` and `contribution_heatmap` chart types.

### Fixed

- Reject unknown contracts and corrupt native snapshots with a bounded diagnostic and visible error state.
- Keep new file-backed payloads for at least ten minutes and prune only expired entries beyond the retained 64.
- Ignore unrevealed Android geometry during animated hit testing.
- Preserve the full chart accessibility frame on iOS so VoiceOver focus and assistive hit testing cover the rendered plot.

## [1.1.0] - 2026-09-01

### Added

- Native candlestick charts with validated OHLC data, semantic candle styling, selection, and accessibility summaries.
- Native radar charts with ordered axes, multiple series, grid and fill controls, selection, and accessibility summaries.
- Platform-neutral viewport, annotation, sampling, and interaction contracts for Cartesian charts.
- Interactive documentation with installation guidance, Blade examples, and native iOS and Android captures.

### Changed

- Expanded Cartesian styling and interaction depth across the Swift and Jetpack Compose renderers.
- Improved native rendering efficiency by precomputing repeated chart geometry.
- Reorganized the README around installation, a complete first-chart workflow, chart examples, and API reference.

### Fixed

- Hardened radar and candlestick geometry, clipping, selection, and label handling across both platforms.
- Applied viewport constraints consistently to horizontal bars and their selection geometry.
- Datetime annotation bands now retain fractional-second ordering, matching the documented datetime axis contract.

## [1.0.0] - 2026-08-28

### Added

- Native area charts with overlay and stacked fill modes.
- Cross-platform native area gradients with an explicit solid-fill option.
- Native scatter charts with numeric, date, and datetime axes.
- Native pie and donut charts with ordered segments, configurable donut cutout, legends, and selection.
- Multiple series and native legends for line, area, and grouped bar charts.
- Validated bar width and corner-radius styling with native point/dp semantics.
- Stable point identities, native selection tooltips, and PHP selection callbacks.
- Category, numeric, date, and datetime x-axis contracts with locale and timezone support.
- Compact, locale-aware `time` labels for dense datetime axes.
- A typed `PointSelection` PHP value object for callback payloads.
- Shared Swift and Kotlin chart cores for formatting, domains, accessibility, interaction, and animation.

### Changed

- Refactored the public PHP elements around a shared Cartesian chart contract.
- Prefixed native symbols to avoid collisions in consuming applications.
- Bounded accessibility summaries for large datasets.
- Raised the package runtime floor to PHP 8.4, matching NativePHP Mobile 4.

## [0.2.0] - 2026-08-27

### Added

- Native bar charts for iOS and Android.
- Localized value tooltips and mixed-sign domains.

### Fixed

- Non-zero single-point line chart domains now use relative padding.

## [0.1.0] - 2026-08-26

### Added

- Initial native line chart component for NativePHP Mobile.

[Unreleased]: https://github.com/donmanueldev/nativephp-charts/compare/v1.1.0...HEAD
[1.1.0]: https://github.com/donmanueldev/nativephp-charts/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/donmanueldev/nativephp-charts/compare/v0.2.0...v1.0.0
[0.2.0]: https://github.com/donmanueldev/nativephp-charts/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/donmanueldev/nativephp-charts/releases/tag/v0.1.0
