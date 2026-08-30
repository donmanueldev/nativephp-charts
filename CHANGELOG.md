# Changelog

All notable changes to NativePHP Charts are documented here. The project follows Semantic Versioning.

## [Unreleased]

This section targets 1.1.0. Its entries are not part of the stable contract until the complete iOS and Android acceptance matrix passes and the manifest version is updated.

### Added

- Added explicit Cartesian axis titles, minimums, maximums, baselines, and intervals for numeric, date, and datetime domains.
- Added semantic x/y reference lines and bands with optional labels.
- Added per-series styles, stepped and dashed lines, fill-between areas, and point uncertainty ranges.
- Added grouped or stacked bar layouts in vertical and horizontal orientations.
- Added tap or scrub interaction modes, logical crosshair control, and shared Cartesian tooltips.
- Added explicit numeric/date x viewports with native pan, pinch zoom, and versioned PHP viewport callbacks.
- Added opt-in LTTB sampling that preserves endpoint identities and original point indexes.
- Added native candlestick charts with validated OHLC ranges and stable close-value selection payloads.
- Added native radar charts with independently scaled axes, polygon grids, filled series, selection, and accessibility summaries.
- Added a package-owned Swift compile harness with behavioral XCTest coverage for viewport geometry and bounded spatial typography.

### Fixed

- Unified iOS pan and pinch state so enabled recognizers share one viewport transaction and emit a single `pan`, `zoom`, or `pan_zoom` callback only after the domain changes.
- Kept chart axes and legends responsive at accessibility text sizes without allowing spatial labels to consume the complete plot area.
- Replaced invalid iOS adjustable-slider chart semantics with localized datum navigation actions, eliminating the `NaN` accessibility value while preserving selectable data traversal and refreshing action identity when chart data changes.
- Externalized oversized Cartesian series into bounded app-local payload files so dense datasets no longer overflow Native UI's 16-bit prop encoding; native readers cache immutable payloads after the first load.
- Kept Laravel view tooling development-only so consuming NativePHP applications install no redundant runtime dependencies.
- Sized Android axes and selection tooltips from rendered font metrics so localized labels and accessibility font scales retain usable plot space.
- Aligned Cartesian point sizing, hidden-axis baselines, and pie/donut angular gaps across Android and iOS.
- Matched Swift Charts' half-open angular range semantics so adjacent pie and donut segments do not share their upper boundary.
- Collapsed duplicate VoiceOver and TalkBack chart semantics while preserving both selection directions for repeated visible values.

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

[1.0.0]: https://github.com/donmanueldev/nativephp-charts/compare/v0.2.0...v1.0.0
[0.2.0]: https://github.com/donmanueldev/nativephp-charts/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/donmanueldev/nativephp-charts/releases/tag/v0.1.0
