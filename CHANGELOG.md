# Changelog

All notable changes to NativePHP Charts are documented here. The project follows Semantic Versioning.

## [1.0.1] - 2026-08-28

### Fixed

- Kept Laravel view tooling development-only so consuming NativePHP applications install no redundant runtime dependencies.

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

[1.0.1]: https://github.com/donmanueldev/nativephp-charts/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/donmanueldev/nativephp-charts/compare/v0.2.0...v1.0.0
[0.2.0]: https://github.com/donmanueldev/nativephp-charts/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/donmanueldev/nativephp-charts/releases/tag/v0.1.0
