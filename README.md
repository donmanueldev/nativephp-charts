# NativePHP Charts

[![Latest Version on Packagist](https://img.shields.io/packagist/v/donmanueldev/nativephp-charts.svg?style=flat-square)](https://packagist.org/packages/donmanueldev/nativephp-charts)
[![Tests](https://github.com/donmanueldev/nativephp-charts/actions/workflows/ci.yml/badge.svg)](https://github.com/donmanueldev/nativephp-charts/actions/workflows/ci.yml)
[![License](https://img.shields.io/packagist/l/donmanueldev/nativephp-charts.svg?style=flat-square)](LICENSE.md)

Beautiful native line, area, bar, scatter, pie, and donut charts for [NativePHP Mobile](https://nativephp.com). iOS uses Swift Charts and Android uses Jetpack Compose Canvas. Data stays on the device; there is no WebView, JavaScript chart library, network service, telemetry, or third-party native chart dependency.

> NativePHP Charts is an independent community plugin. It is not an official NativePHP package.

## Features

- Native `<native:line-chart>`, `<native:area-chart>`, `<native:bar-chart>`, `<native:scatter-chart>`, `<native:pie-chart>`, and `<native:donut-chart>` EDGE elements.
- Multiple ordered series with stable series and point identities.
- Native legends, tooltips, point selection, and PHP callbacks.
- Category, number, date, and datetime x-axes.
- Locale-aware number, currency, percentage, and date formatting.
- Positive, negative, mixed-sign, decimal, constant, and single-point domains.
- Overlay/stacked area fills, grouped multi-series bars, independent scatter observations, and radial composition.
- Native reveal/update animations with platform motion settings.
- Dark appearance, semantic styling, font aliases, VoiceOver, and TalkBack summaries.
- No permissions, secrets, bridge functions, background tasks, or JavaScript API.

## Requirements

| Requirement | Supported |
| --- | --- |
| PHP | `^8.4` |
| NativePHP Mobile | `^4.0` |
| Android | API 26 or newer |
| iOS | 18.2 or newer |
| NativePHP Desktop / WebView | Not supported |

## Installation

Install the stable release with:

```bash
composer require donmanueldev/nativephp-charts:^1.0
php artisan vendor:publish --tag=nativephp-plugins-provider --no-interaction
php artisan native:plugin:register donmanueldev/nativephp-charts --no-interaction
php artisan native:plugin:list
php artisan native:plugin:validate
```

The package is a compiled native UI plugin. After installing or updating it, rebuild the platform you are developing for. Choose the platform explicitly in your terminal:

```bash
php artisan native:run ios
```

or:

```bash
php artisan native:run android
```

## Quick start

Charts are leaf elements. Render them inside a `NativeComponent` EDGE view with an explicit height and accessible purpose.

```blade
<native:line-chart
    class="w-full h-80"
    :series="[[
        'id' => 'monthly-sales',
        'name' => 'Ventas',
        'color' => '#0F766E',
        'points' => [
            ['id' => 'jan', 'label' => 'Ene', 'value' => 42000],
            ['id' => 'feb', 'label' => 'Feb', 'value' => 51800],
            ['id' => 'mar', 'label' => 'Mar', 'value' => 62400],
        ],
    ]]"
    locale="es-NI"
    value-format="currency"
    currency-code="NIO"
    :maximum-fraction-digits="0"
    a11y-label="Ventas mensuales en córdobas"
/>
```

## Multiple series and legend

Series IDs must be unique within the chart. Point IDs must be unique within their series. Points without an explicit ID remain compatible with v0.2 and receive a deterministic `compat-*` identity, but explicit stable IDs are recommended for interactive and frequently updated charts.

```blade
<native:area-chart
    class="w-full h-80"
    :series="[
        [
            'id' => 'actual',
            'name' => 'Actual',
            'color' => '#2563EB',
            'points' => [
                ['id' => 'actual-q1', 'label' => 'Q1', 'value' => 82],
                ['id' => 'actual-q2', 'label' => 'Q2', 'value' => 96],
            ],
        ],
        [
            'id' => 'forecast',
            'name' => 'Forecast',
            'color' => '#F59E0B',
            'points' => [
                ['id' => 'forecast-q1', 'label' => 'Q1', 'value' => 78],
                ['id' => 'forecast-q2', 'label' => 'Q2', 'value' => 101],
            ],
        ],
    ]"
    area-mode="overlay"
    :legend="[
        'visible' => true,
        'position' => 'bottom',
        'alignment' => 'center',
        'style' => ['markerSize' => 9],
    ]"
    a11y-label="Actual and forecast quarterly totals"
/>
```

`area-mode` accepts `overlay` or `stacked`. Bar charts group multiple series by category. Series should use the same ordered x values when comparison or stacking is intended.

Scatter charts use the same Cartesian series contract but render independent observations without connecting lines. Numeric, date, and datetime x values retain their actual spacing.

```blade
<native:scatter-chart
    class="w-full h-80"
    :series="$experimentSeries"
    :x-axis="['type' => 'number', 'labelCount' => 5]"
    :y-axis="['valueFormat' => 'percent', 'maximumFractionDigits' => 0]"
    :legend="['visible' => true, 'position' => 'top']"
    _select="pointSelected"
    a11y-label="Conversion observations by cohort"
/>
```

## Pie and donut charts

Radial charts use ordered `segments`. Segment IDs must be unique, values must be finite and non-negative, and every non-empty chart must contain at least one positive value.

```blade
<native:donut-chart
    class="w-full h-80"
    :segments="[
        ['id' => 'subscriptions', 'label' => 'Subscriptions', 'value' => 48200, 'color' => '#2563EB'],
        ['id' => 'services', 'label' => 'Services', 'value' => 31750, 'color' => '#7C3AED'],
        ['id' => 'partners', 'label' => 'Partners', 'value' => 9650, 'color' => '#F59E0B'],
    ]"
    :inner-radius-ratio="0.62"
    :legend="['visible' => true, 'position' => 'bottom']"
    :style="['segment' => ['gap' => 2, 'cornerRadius' => 7, 'opacity' => 0.96]]"
    _select="pointSelected"
    locale="en-US"
    value-format="currency"
    currency-code="USD"
    :maximum-fraction-digits="0"
    a11y-label="Quarterly revenue by channel"
/>
```

Pie fixes the inner radius at `0`. Donut defaults to `0.6` and accepts `0.2` through `0.85`. `style.segment.gap` is an angular gap from 0–12 degrees, `cornerRadius` accepts 0–20 points, and `opacity` accepts 0–1. Radial selections use the same `PointSelection` version 1 payload: the segment ID is exposed as both `series_id` and `point_id`, while its label is the series name, x value, and visible label.

## Selection and PHP callbacks

Use the `_select` callback attribute from NativePHP's UI component contract. Selection and the tooltip update immediately on the native side; the PHP callback is a separate effect.

```blade
<native:bar-chart
    class="w-full h-72"
    :series="$series"
    _select="pointSelected"
    a11y-label="Revenue by channel"
/>
```

```php
use Donmanueldev\NativephpCharts\PointSelection;

public ?string $selectedPointId = null;

public function pointSelected(string $payload): void
{
    $selection = PointSelection::fromJson($payload);
    $this->selectedPointId = $selection->pointId;
}
```

The fluent element API uses `->onSelect('pointSelected')`. Callback payload version 1 contains:

```json
{
  "version": 1,
  "chart_type": "bar",
  "series_id": "online",
  "series_name": "Online",
  "point_id": "online-jan",
  "point_index": 0,
  "x_type": "category",
  "x": "January",
  "label": "January",
  "value": 42000,
  "localized_value": "$42,000"
}
```

Treat `localized_value` as presentation text. Use `value`, IDs, and `x` for application logic.

## Axis and formatting

The existing v0.2 scalar formatter props remain supported. The structured axis API adds typed x values while `y-axis` can override the scalar y formatter. Fluent configuration uses last-call-wins semantics for conflicting scalar and structured y options; partial non-conflicting calls are merged.

```blade
<native:line-chart
    class="w-full h-80"
    :series="[[
        'id' => 'balance',
        'name' => 'Balance',
        'color' => '#7C3AED',
        'points' => [
            ['id' => 'balance-aug-01', 'x' => '2026-08-01', 'label' => '1 Aug', 'value' => 1050.25],
            ['id' => 'balance-aug-15', 'x' => '2026-08-15', 'label' => '15 Aug', 'value' => 1288.40],
        ],
    ]]"
    :x-axis="[
        'type' => 'date',
        'dateFormat' => 'medium',
        'timezone' => 'America/Managua',
    ]"
    :y-axis="[
        'valueFormat' => 'currency',
        'currencyCode' => 'USD',
        'minimumFractionDigits' => 2,
        'maximumFractionDigits' => 2,
    ]"
    locale="en-US"
    a11y-label="Account balance over time"
/>
```

X-axis types:

| Type | Point `x` |
| --- | --- |
| `category` | Optional; defaults to `label` |
| `number` | Finite integer or float; integers must fit `±9,007,199,254,740,991` |
| `date` | Strict `YYYY-MM-DD` |
| `datetime` | RFC 3339 with `Z` or an explicit offset |

Line, area, and bar charts default to a category x-axis. Scatter defaults to a number x-axis. The `x-axis` map accepts `type`, `dateFormat`, `timezone`, `visible`, and `labelCount`; the `y-axis` map accepts `valueFormat`, `currencyCode`, `minimumFractionDigits`, `maximumFractionDigits`, `visible`, `labelCount`, and `beginAtZero`. Snake-case aliases are also accepted. Axis `labelCount` must be between 2 and 12. Axis visibility controls the corresponding axis, while `style.axis.visible` remains the shared visual fallback.

Integer chart values use the same exact cross-platform range as numeric x values, so PHP, JSON, Swift, and Kotlin preserve them without precision loss. Date formats are `short`, `medium`, `long`, and `full`; datetime axes also support `time` for compact, locale-aware time-only labels. Valid datetime fractional seconds are preserved. Timezones use IANA identifiers such as `America/Managua`. Currency accepts normalized three-letter codes such as `USD` and `NIO`; platform formatters determine display support. Percentage values are fractions: `0.92` renders as `92%`.

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

## Style

The neutral style map supports these sections:

- `line`: `color`, `width`, `interpolation` (`linear|smooth`).
- `area`: `opacity`, `gradient` (native vertical gradient by default; set `false` for a solid fill).
- `bar`: `radius`, `width` (points on iOS, density-independent pixels on Android; automatic width remains the default).
- `points`: `visible`, `color`, `size`.
- `grid`: `visible`, `color`, `width`.
- `axis`: `visible`, `color`, `labelColor`, `font`, `fontSize`, `labelCount`.
- `segment` (pie/donut): `gap`, `cornerRadius`, `opacity`.

Colors accept `#RGB`, `#RRGGBB`, CSS-alpha `#RRGGBBAA`, `black`, `white`, and `transparent`. Axis fonts accept NativePHP font aliases and fall back to the system font when unresolved.

## Accessibility and performance

- Always provide a localized `a11y-label` describing the chart's purpose.
- Stable point IDs keep selection and updates deterministic even when labels repeat.
- Large accessibility descriptions are bounded instead of concatenating an unbounded dataset.
- Native renderers decode configuration once per update and reuse domains and formatters.
- The package does not silently remove or sample points. For dense analytical datasets, aggregate deliberately for the device viewport and retain original IDs when mapping a selection back to domain data.
- Native rendering is not a promise of unlimited data. Validate 10, 100, 1,000, and 10,000-point cases against your target devices and interaction needs.

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
