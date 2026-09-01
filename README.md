<p align="center">
  <img src="docs/assets/brand/nativephp-charts-lockup.svg" width="420" alt="NativePHP Charts">
</p>

# NativePHP Charts

[![Latest Version on Packagist](https://img.shields.io/packagist/v/donmanueldev/nativephp-charts.svg?style=flat-square)](https://packagist.org/packages/donmanueldev/nativephp-charts)
[![Tests](https://github.com/donmanueldev/nativephp-charts/actions/workflows/ci.yml/badge.svg)](https://github.com/donmanueldev/nativephp-charts/actions/workflows/ci.yml)
[![License](https://img.shields.io/packagist/l/donmanueldev/nativephp-charts.svg?style=flat-square)](LICENSE.md)

**[Explore the official documentation and native demos →](https://donmanueldev.github.io/nativephp-charts/)**

Beautiful native line, area, bar, scatter, pie, and donut charts for [NativePHP Mobile](https://nativephp.com). iOS uses Swift Charts and Android uses Jetpack Compose Canvas. Data stays on the device; there is no WebView, JavaScript chart library, network service, telemetry, or third-party native chart dependency.

> NativePHP Charts is an independent community plugin. It is not an official NativePHP package.

> **Release status:** `1.0.x` is the production contract for line, area, bar, scatter, pie, and donut charts. Radar, candlestick, annotations, sampling, and viewport interaction remain source-only until their native acceptance evidence is complete.

## Features

- Production 1.0 `<native:line-chart>`, `<native:area-chart>`, `<native:bar-chart>`, `<native:scatter-chart>`, `<native:pie-chart>`, and `<native:donut-chart>` EDGE elements.
- Multiple ordered series, native legends and tooltips, stable selection, and PHP callbacks.
- Category, number, date, and datetime axes with locale-aware formatting.
- Platform-neutral styles, native animations, dark appearance, VoiceOver, and TalkBack summaries.
- No permissions, secrets, bridge functions, background tasks, or JavaScript API.

## Installation

```bash
composer require donmanueldev/nativephp-charts:^1.0
php artisan vendor:publish --tag=nativephp-plugins-provider --no-interaction
php artisan native:plugin:register donmanueldev/nativephp-charts --no-interaction
php artisan native:plugin:list
php artisan native:plugin:validate
```

The package compiles into the selected native target. After installing or updating it, rebuild that target. Requirements, quick start, chart examples, selection, axes, formatting, styling, accessibility, and native screenshots live in the **[official documentation](https://donmanueldev.github.io/nativephp-charts/)**.

## Public properties

| Blade property | Default | Description |
| --- | --- | --- |
| `series` | `[]` | Ordered, uniquely identified series and points. |
| `show-grid` | `true` | Shows chart grid lines. |
| `show-points` | `true` | Shows line/area symbols. On bars it preserves the v0.2 axis-visibility fallback unless an axis/style override is supplied. |
| `begin-at-zero` | `true` | Includes zero in line/bar y domains. Area charts retain zero as their fill baseline. |
| `animated` | `true` | Enables native reveal and update animation. |
| `empty-label` | `No data` | Visible and accessible empty state. |
| `a11y-label` | `Chart` | Purpose announced by assistive technology. |
| `locale` | Device locale | BCP-47 locale such as `es-NI`. |
| `value-format` | `number` | `number`, `currency`, or `percent`. |
| `currency-code` | None | Required normalized three-letter code for currency formatting. |
| `minimum-fraction-digits` | Formatter default | Integer from 0 to 8. |
| `maximum-fraction-digits` | Formatter default | Integer from 0 to 8. |
| `x-axis` | Category; number for scatter | Structured x-axis configuration. |
| `y-axis` | Scalar formatter props | Structured y formatter configuration. |
| `legend` | Visible for multiple series | Position, alignment, and semantic style. |
| `_select` | None | PHP method receiving selection JSON. |
| `style` | `[]` | Platform-neutral visual configuration. |
| `area-mode` | `overlay` | Area only: `overlay` or `stacked`. |
| `segments` | `[]` | Pie/donut only: ordered, uniquely identified radial segments. |
| `inner-radius-ratio` | `0` pie / `0.6` donut | Donut cutout ratio from `0.2` through `0.85`. |

Blade uses kebab case. The fluent PHP API uses camelCase methods such as `showGrid()`, `xAxis()`, `yAxis()`, `legend()`, `onSelect()`, `areaMode()`, `segments()`, and `innerRadiusRatio()`.

## Updating from v0.2

Existing single-series calls remain valid. Scalar formatting props remain authoritative unless the corresponding structured `y-axis` value is supplied. Add explicit point IDs before enabling selection or frequent insert/reorder updates.

After updating, confirm registration, validate the manifest, regenerate stale native shells if required, and rebuild the selected platform:

```bash
composer update donmanueldev/nativephp-charts
php artisan native:plugin:list
php artisan native:plugin:validate
```

## Testing and evidence

```bash
composer test
```

PHP tests prove normalization, serialization, compatibility, and callback registration. They do not prove Swift/Kotlin compilation or rendering. Native renderer changes require generated iOS and Android builds; simulator/emulator and physical-device evidence must be recorded separately.

## Platform and frontend scope

This is a SuperNative EDGE UI component package. It intentionally has no JavaScript API, bridge functions, permissions, secrets, Livewire WebView integration, or Inertia wrapper. NativePHP Desktop, Electron, browser rendering, and Chart.js are outside scope.

## Support and security

Use [GitHub Issues](https://github.com/donmanueldev/nativephp-charts/issues) for reproducible bugs and feature requests. Follow [SECURITY.md](SECURITY.md) for private vulnerability reports.

## License

NativePHP Charts is open-source software licensed under the [MIT license](LICENSE.md).
