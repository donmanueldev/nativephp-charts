# NativePHP Charts

[![Latest Version on Packagist](https://img.shields.io/packagist/v/donmanueldev/nativephp-charts.svg?style=flat-square)](https://packagist.org/packages/donmanueldev/nativephp-charts)
[![Total Downloads](https://img.shields.io/packagist/dt/donmanueldev/nativephp-charts.svg?style=flat-square)](https://packagist.org/packages/donmanueldev/nativephp-charts)
[![PHP Version](https://img.shields.io/packagist/dependency-v/donmanueldev/nativephp-charts/php.svg?style=flat-square)](https://packagist.org/packages/donmanueldev/nativephp-charts)
[![License](https://img.shields.io/packagist/l/donmanueldev/nativephp-charts.svg?style=flat-square)](https://packagist.org/packages/donmanueldev/nativephp-charts)

Native line and bar charts for [NativePHP Mobile](https://nativephp.com), rendered with Swift Charts on iOS and Jetpack Compose on Android. Chart data stays in the app and is rendered by native platform primitives—there is no WebView, JavaScript charting library, or remote rendering service.

> NativePHP Charts is an independent community plugin. It is not an official NativePHP package.

## Features

- Real native `LineChart` and `BarChart` components for SuperNative/EDGE views.
- One ordered data series with stable identity and numeric points.
- Positive, zero, negative, decimal, and single-point domains.
- Device-aware number, currency, and percentage formatting.
- BCP-47 locale support and configurable fraction digits.
- Light and dark appearance using native platform colors.
- Empty states, optional animation, grid and axis controls.
- VoiceOver and TalkBack summaries from the same authoritative chart data.
- Bar selection tooltips without introducing JavaScript or selection callbacks.
- No permissions, secrets, telemetry, network access, or third-party native dependencies.

## What is new in v0.2.0

- Added the public `<native:bar-chart>` EDGE component.
- Added Swift Charts and Jetpack Compose bar renderers.
- Added categorical, mixed-sign currency, and percentage support for bars.
- Added localized tap tooltips for bar values.
- Preserved the existing LineChart Blade and fluent PHP contracts.
- Corrected non-zero single-point LineChart domains to use relative padding on both platforms.
- Ensured iOS empty BarChart states announce their configured empty label.
- Made Android BarChart precision changes invalidate and rebuild the native formatter.
- Expanded manifest, contract, catalogue, renderer, accessibility, and release regression coverage.

## Requirements

| Requirement | Supported |
| --- | --- |
| PHP | `^8.2` |
| NativePHP Mobile | `^4.0` |
| Android | API 26 or newer |
| iOS | 18.2 or newer |
| NativePHP Desktop | Not supported |
| WebView / browser rendering | Not supported |

The package is a NativePHP UI component plugin. It does not expose bridge functions, events, permissions, services, or background tasks.

## Installation

Install the package from [Packagist](https://packagist.org/packages/donmanueldev/nativephp-charts):

```bash
composer require donmanueldev/nativephp-charts:^0.2
```

Publish the application's native plugin provider, register the package explicitly, and verify registration:

```bash
php artisan vendor:publish --tag=nativephp-plugins-provider --no-interaction
php artisan native:plugin:register donmanueldev/nativephp-charts --no-interaction
php artisan native:plugin:list
php artisan native:plugin:validate
```

Registration is required. Composer auto-discovery loads the PHP package, but NativePHP does not compile the Swift and Kotlin renderers into the app until the plugin is registered.

Finally, rebuild the platform you are developing for:

```bash
php artisan native:run ios
```

or:

```bash
php artisan native:run android
```

Native source is compiled at build time. A PHP hot reload cannot install or replace the native renderers.

## Updating

Update the Composer package, confirm it remains registered, validate the manifest, and rebuild the affected native target:

```bash
composer update donmanueldev/nativephp-charts
php artisan native:plugin:list
php artisan native:plugin:validate
```

If a NativePHP shell generated before the update still contains stale plugin files, regenerate it with `php artisan native:install --force`, then rebuild the selected platform.

## Quick start

Use charts inside a `NativeComponent` EDGE view. Each chart is a leaf element and must have an explicit width and height.

### Line chart

```blade
<native:line-chart
    class="w-full h-80"
    :series="[[
        'id' => 'monthly-sales',
        'name' => 'Ventas',
        'color' => '#0F766E',
        'points' => [
            ['label' => 'Ene', 'value' => 42000],
            ['label' => 'Feb', 'value' => 51800],
            ['label' => 'Mar', 'value' => 62400],
        ],
    ]]"
    locale="es-NI"
    value-format="currency"
    currency-code="NIO"
    :maximum-fraction-digits="0"
    a11y-label="Ventas mensuales en córdobas"
    :style="[
        'line' => ['width' => 4, 'interpolation' => 'smooth'],
        'points' => ['size' => 5],
        'grid' => ['color' => '#D1FAE5'],
        'axis' => ['font' => 'accent', 'labelCount' => 4],
    ]"
/>
```

### Bar chart

```blade
<native:bar-chart
    class="w-full h-72"
    :series="[[
        'id' => 'net-change',
        'name' => 'Net change',
        'color' => '#6366F1',
        'points' => [
            ['label' => 'Revenue', 'value' => 4200],
            ['label' => 'Returns', 'value' => -860],
            ['label' => 'Fees', 'value' => -240],
            ['label' => 'Net', 'value' => 3100],
        ],
    ]]"
    :begin-at-zero="true"
    locale="en-US"
    value-format="currency"
    currency-code="USD"
    :maximum-fraction-digits="0"
    a11y-label="Net change in US dollars"
/>
```

Bar charts display a local tooltip when a category is tapped. This is presentation-only and does not emit a PHP callback.

## Data contract

`series` must be an ordered array containing zero or one series. Passing more than one series throws an `InvalidArgumentException` instead of silently dropping data.

```php
[
    [
        'id' => 'stable-series-id',
        'name' => 'Human-readable series name',
        'color' => '#0F766E',
        'points' => [
            ['label' => 'First', 'value' => 12],
            ['label' => 'Second', 'value' => 18.5],
        ],
    ],
]
```

| Field | Type | Rules |
| --- | --- | --- |
| `series[].id` | `string` | Required, non-empty, and stable across updates. |
| `series[].name` | `string` | Required, non-empty, and included in accessibility summaries. |
| `series[].color` | `string` | Required supported color. |
| `series[].points` | ordered array | Required; may be empty. |
| `points[].label` | `string` | Required and non-empty. |
| `points[].value` | `int\|float` | Required and finite; numeric strings are rejected. |

Keep the series `id` stable when points change so native updates and animations retain the same logical identity.

## Properties

| Blade property | Default | Description |
| --- | --- | --- |
| `series` | `[]` | Zero or one ordered series using the contract above. |
| `show-grid` | `true` | Shows or hides grid lines. |
| `show-points` | `true` | Shows LineChart point markers; on BarChart it controls axis ticks and labels. |
| `begin-at-zero` | `true` | Includes zero in the numeric domain. |
| `animated` | `true` | Enables the initial native animation. |
| `empty-label` | `No data` | Visible and accessible text for an empty series. |
| `a11y-label` | `Chart` | Purpose of the chart announced by VoiceOver or TalkBack. |
| `locale` | Device locale | BCP-47 locale such as `es-NI` or `en-US`. |
| `value-format` | `number` | `number`, `currency`, or `percent`. |
| `currency-code` | None | Required three-letter ISO 4217 code for currency values. |
| `minimum-fraction-digits` | Formatter default | Integer from 0 to 8. |
| `maximum-fraction-digits` | Formatter default | Integer from 0 to 8 and not lower than the minimum. |
| `style` | `[]` | Platform-neutral LineChart style configuration described below. |

Blade accepts the documented kebab-case property names. The fluent PHP API uses camelCase methods such as `showGrid()`, `beginAtZero()`, `valueFormat()`, and `maximumFractionDigits()`.

### Percent values

Use fractional values for percentages. For example, `0.92` is formatted as `92%`; do not pass `92` unless `9,200%` is the intended value.

## LineChart styling

The `style` contract currently applies to LineChart. Values in `style.points.visible` and `style.grid.visible` take precedence over the corresponding scalar toggles.

```blade
:style="[
    'line' => [
        'color' => '#7C3AED',
        'width' => 3,
        'interpolation' => 'smooth',
    ],
    'points' => [
        'visible' => true,
        'color' => '#FFFFFF',
        'size' => 5,
    ],
    'grid' => [
        'visible' => true,
        'color' => '#E2E8F0',
        'width' => 1,
    ],
    'axis' => [
        'visible' => true,
        'color' => '#94A3B8',
        'labelColor' => '#475569',
        'font' => 'accent',
        'fontSize' => 11,
        'labelCount' => 4,
    ],
]"
```

| Section | Options |
| --- | --- |
| `line` | `color`, `width` greater than 0 and at most 16, `interpolation` as `linear` or `smooth` |
| `points` | `visible`, `color`, `size` greater than 0 and at most 24 |
| `grid` | `visible`, `color`, `width` greater than 0 and at most 8 |
| `axis` | `visible`, `color`, `labelColor`, `font`, `fontSize` greater than 0 and at most 32, `labelCount` from 2 to 8 |

Unsupported sections or options are rejected explicitly.

BarChart v0.2 uses `series.color` for bars and the scalar `show-grid` / `show-points` controls. Bar-specific `style` overrides are not rendered yet.

### Colors

Supported colors are:

- `#RGB`
- `#RRGGBB`
- CSS alpha `#RRGGBBAA`
- `black`
- `white`
- `transparent`

CSS alpha values are normalized internally to the native ARGB wire format. Tailwind palette names and arbitrary CSS color functions are not accepted as data colors.

### Fonts

`style.axis.font` accepts a bundled NativePHP font filename without its extension or a font alias configured by the host app. Unresolved fonts safely fall back to the platform system font.

## Fluent PHP API

The same contract is available when constructing an element programmatically:

```php
use Donmanueldev\NativephpCharts\Elements\LineChart;

$chart = LineChart::make()
    ->series($series)
    ->showGrid(true)
    ->showPoints(true)
    ->beginAtZero(false)
    ->animated(true)
    ->locale('en-US')
    ->valueFormat('percent')
    ->minimumFractionDigits(1)
    ->maximumFractionDigits(1)
    ->a11yLabel('Service availability by month');
```

Use `Donmanueldev\NativephpCharts\Elements\BarChart` for the bar wire type.

## Accessibility

Every production chart should provide an `a11y-label` that describes its purpose, not its visual appearance.

```blade
a11y-label="Monthly revenue in Nicaraguan córdobas"
```

Both native renderers expose:

- The supplied purpose as the accessibility label.
- The localized series name, point labels, and formatted values as the accessibility value/description.
- The `empty-label` when there are no points.

Keep series names, point labels, `a11y-label`, and `empty-label` in the application's language. Do not include secrets or unnecessary personal data in chart labels because screen readers announce the textual summary.

## Dynamic updates

Charts re-render when their serialized series or configuration changes. Keep `series[].id` stable, preserve point order, and update the values rather than generating a new random identity on each render.

Native renderer changes still require a native rebuild; ordinary PHP data changes can use the normal NativePHP development loop.

## Security and privacy

NativePHP Charts has a deliberately small operational surface:

- No Android permissions or iOS entitlements.
- No secrets or environment variables.
- No analytics, telemetry, logging, or remote requests.
- No bridge functions or background services.
- No Gradle, CocoaPods, or Swift Package dependencies.
- No JavaScript runtime or WebView integration.

Chart values are serialized by the host app and consumed locally by the native renderer. Applications remain responsible for authorizing and minimizing the data they choose to display.

To report a security issue, do not open a public issue containing exploit details or sensitive data. Email `donmanuel@momotombo.dev` with the affected version, platform, impact, and a minimal reproduction.

## Troubleshooting

### The chart renders nothing

- Confirm the chart is inside a `NativeComponent` view.
- Give it an explicit height such as `class="w-full h-72"`.
- Run `php artisan native:plugin:list` and verify `donmanueldev/nativephp-charts` is registered.
- Rebuild the platform after installing or updating the package.

### NativePHP reports that the plugin is installed but not registered

Run:

```bash
php artisan native:plugin:register donmanueldev/nativephp-charts --no-interaction
php artisan native:plugin:list
```

### `native:plugin:validate` warns about `bridge_functions`

`No bridge_functions defined in manifest` is expected. This package exposes native UI components only and does not call native functions from PHP.

### Currency rendering fails during PHP serialization

Set `currency-code` whenever `value-format="currency"` is used. The code must contain exactly three letters, for example `USD` or `NIO`.

### The app still uses an older renderer after updating

Native files are compiled into the application binary. Regenerate the native shell with `php artisan native:install --force` when needed, then rebuild the selected platform.

## Verified release evidence

The v0.2.0 release candidate was verified on 2026-08-28 with:

- 39 focused package and laboratory tests passing with 232 assertions.
- `composer validate --strict` passing.
- Laravel Pint passing for changed PHP files.
- `native:plugin:validate` completing with no errors and the expected UI-only warning.
- Swift source parsing and an iOS simulator Xcode build succeeding.
- The Android debug APK building, installing, and rendering on the available emulator.
- LineChart and BarChart light/dark, signed values, locale formatting, tooltips, and accessibility checks on both native targets.
- The LineChart `99.8%` single-point case remaining centered by relative domain padding on both platforms.

Native acceptance environments:

| Target | Verified environment | Status |
| --- | --- | --- |
| iOS simulator | iPhone 17, iOS 26.5 | Passed |
| Android emulator | Pixel 10 Pro profile, Android API 37 | Passed |
| Physical iOS device | Not exercised | Pending |
| Physical Android device | Not exercised | Pending |

Simulator and emulator evidence proves native compilation and renderer behavior in those environments. It does not claim physical-device acceptance.

## Current limitations

- Zero or one series is supported; multiple series and legends are not rendered.
- BarChart `style` sections are serialized for contract compatibility but are not applied by the v0.2 native bar renderers.
- Bar tooltips do not emit PHP selection callbacks.
- Zooming, scrolling, date-specific axes, stacked bars, area, pie, and donut charts are not included.
- Physical-device acceptance is not yet recorded.
- NativePHP Desktop, browser, WebView, and JavaScript rendering are outside this package's scope.

## Support

Use [GitHub Issues](https://github.com/donmanueldev/nativephp-charts/issues) for reproducible bugs and focused feature requests. Include:

- Package and NativePHP Mobile versions.
- iOS or Android version and simulator, emulator, or physical-device model.
- A minimal `series` payload and Blade snippet.
- Expected and actual behavior.
- A screenshot or accessibility output when relevant.
- Any `native:plugin:validate` or build error without secrets.

## Development and contributing

Clone the repository, install development dependencies, and run the package tests:

```bash
git clone https://github.com/donmanueldev/nativephp-charts.git
cd nativephp-charts
composer install
composer test
```

Contributions should preserve the platform-neutral PHP/Blade contract, include focused regression coverage, and keep the Swift and Kotlin behavior aligned. Native renderer changes are not considered verified until the affected generated project compiles; visual or accessibility changes should also include simulator, emulator, or device evidence.

Open an issue before proposing a breaking public-contract change or a new chart type so its cross-platform behavior and release scope can be agreed first.

## Versioning

Releases use Git tags and are distributed through [Packagist](https://packagist.org/packages/donmanueldev/nativephp-charts). The package is pre-1.0: minor releases may refine the API, while patch releases are reserved for backwards-compatible fixes. Pin a compatible range such as `^0.2` and review release notes before upgrading to a new minor version.

## Release history

### v0.2.0

- Added native BarChart support on iOS and Android.
- Added bar tooltips, signed domains, localized values, and accessibility summaries.
- Corrected LineChart non-zero single-point domain padding.
- Corrected the iOS empty-state accessibility value and Android formatter reactivity.
- Expanded contract and native release validation.

### v0.1.0

- Introduced the single-series native LineChart.
- Added localized number formatting, semantic styles, dark mode, empty states, animation, and accessibility summaries.

## License

NativePHP Charts is open-source software licensed under the MIT license.
