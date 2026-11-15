# Changelog

All notable changes to NativePHP Charts are documented here. The project follows Semantic Versioning.

## [Unreleased]

## [1.3.0] - 2026-11-14

### Added

- The Blade `a11y-hint` attribute now reaches the native renderers, matching the fluent `a11yHint()` method.
- Contributing guide, code of conduct, feature request template, and a documentation build check on pull requests.
- Social preview image, canonical and Open Graph metadata, structured data, and `llms.txt` for the documentation site.

### Changed

- The Composer dist archive no longer ships the documentation site, tests, or CI configuration, reducing it from about 3.3 MB to about 220 KB.
- An explicit `null` for an optional axis key (`title`, `visible`, `labelCount`, `minimum`, `maximum`, `baseline`, `interval`, `beginAtZero`) now clears that option instead of raising a PHP warning or keeping the earlier value.
- Changing the x-axis `type` drops a previously configured `minimum`, `maximum`, `baseline`, and `interval`.
- Fluent `annotations()` and `viewport()` may be called before `xAxis()`; x-type errors are then reported when the axis is configured or the chart is rendered.
- Progress charts format the selection `localized_value` and Android labels with the chart `locale` and fraction-digit settings on both platforms.
- Progress charts with many rings scale ring width so every ring stays visible and tappable on both platforms, metrics beyond the fourth use the preset palette on iOS, legends longer than six metrics scroll within a fixed height, and the center label is hidden when the innermost ring leaves no room for it.
- Android progress legends show a color marker per metric, and iOS progress legends use the chart locale and fraction digits.
- Contribution heatmap month and weekday labels follow the chart `locale`; Android intensity scales to the largest visible value.
- Contribution heatmap days with a value of `0` use the empty color on iOS, matching Android, and a partial first month drops its label when it would overlap the next month's.
- Declared `nativephp/mobile-ui` as a dependency, since the native renderers use its font resolver, node, and color types.
- Android draws at most 100 ticks for an explicit axis `interval`, thinning denser intervals across the full domain.
- Rewrote the README and Laravel Boost guideline to cover all ten chart types, and converted documentation captures to WebP.

### Fixed

- Android contribution heatmaps without an `end-date` end on the device's local day instead of the UTC day, matching iOS.
- iOS horizontal bar charts render every category in source order, with ticks, annotations, tooltips, selection, and viewport gestures aligned to the bars.
- Charts no longer show or announce the empty state before their first load on iOS, and keep their size while loading on Android.
- Candlestick charts reject more than one series when `series` and `x-axis` are supplied together in Blade.
- Date-axis viewport ranges and `minimumSpan` no longer depend on the PHP default time zone.
- An unwritable payload directory raises the documented exception instead of silently writing to the system temporary directory, and abandoned temporary payload files are pruned.
- Callback expressions accept parentheses inside string arguments and reject non-finite numbers.
- Android tap, scrub, and viewport callbacks report current values after chart props change.
- Android tooltips wider than the plot no longer crash the draw pass, and Cartesian legend markers match per-series line and point colors.
- iOS candlestick tooltips show all four OHLC values, and tooltips near plot edges are no longer clipped.
- iOS contribution heatmaps without an `end-date` end on the device's current day, and keep weekday labels when the grid starts with blank cells.
- Contribution heatmaps no longer outline every blank day as selected while nothing is selected, and the selection outline of edge cells is no longer clipped.
- iOS progress and contribution heatmap charts paint the theme background, and progress center labels and legends use the theme foreground color, matching Android.

## [1.2.1] - 2026-10-01

### Fixed

- Exposed the shared Android JSON decoder extension.

[1.2.1]: https://github.com/donmanueldev/nativephp-charts/compare/v1.2.0...v1.2.1

## [1.2.0] - 2026-09-18

### Added

- Native progress and contribution heatmap charts with selection, reduced-motion behavior, empty/error states, and accessible summaries.
- Semantic `light`, `dark`, and `system` themes with built-in and application-defined presets.
- An Astro/Starlight documentation application.

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

[Unreleased]: https://github.com/donmanueldev/nativephp-charts/compare/v1.3.0...HEAD
[1.3.0]: https://github.com/donmanueldev/nativephp-charts/compare/v1.2.1...v1.3.0
[1.2.0]: https://github.com/donmanueldev/nativephp-charts/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/donmanueldev/nativephp-charts/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/donmanueldev/nativephp-charts/compare/v0.2.0...v1.0.0
[0.2.0]: https://github.com/donmanueldev/nativephp-charts/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/donmanueldev/nativephp-charts/releases/tag/v0.1.0
