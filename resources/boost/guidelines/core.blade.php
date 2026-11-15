## donmanueldev/nativephp-charts

Ten native chart elements for NativePHP Mobile v4: line, area, bar, scatter, candlestick, pie, donut, radar, progress, and contribution heatmap. iOS renders with Swift Charts and SwiftUI Canvas; Android renders with Jetpack Compose Canvas. There is no WebView or JavaScript API.

### Installation

```bash
composer require donmanueldev/nativephp-charts
php artisan vendor:publish --tag=nativephp-plugins-provider --no-interaction
php artisan native:plugin:register donmanueldev/nativephp-charts --no-interaction
php artisan native:plugin:validate
```

The renderers compile into the native app. The user must rebuild the iOS or Android target after installing or updating the package; do not run `native:run` yourself.

### Elements

| Tag | Data attribute | Fluent class (`Donmanueldev\NativephpCharts\Elements\…`) |
| --- | --- | --- |
| `<native:line-chart>` | `series` | `LineChart` |
| `<native:area-chart>` | `series` | `AreaChart` |
| `<native:bar-chart>` | `series` | `BarChart` |
| `<native:scatter-chart>` | `series` | `ScatterChart` |
| `<native:candlestick-chart>` | `series` (zero or one, OHLC points) | `CandlestickChart` |
| `<native:pie-chart>` | `segments` | `PieChart` |
| `<native:donut-chart>` | `segments` | `DonutChart` |
| `<native:radar-chart>` | `axes` and `series` | `RadarChart` |
| `<native:progress-chart>` | `metrics` | `ProgressChart` |
| `<native:contribution-heatmap>` | `values` | `ContributionHeatmap` |

Charts are self-closing leaf elements inside a `NativeComponent` view. Always give them an explicit height class (for example `h-80`) and a task-specific `a11y-label` in the app's language. Every collection must be an ordered PHP list, IDs must be unique, numbers must be finite ints or floats (numeric strings are rejected), and unknown keys throw `InvalidArgumentException` before rendering.

### Cartesian example (line, area, bar, scatter, candlestick)

@verbatim
<code-snippet name="Line chart with a date axis and currency values" lang="blade">
<native:line-chart
    class="w-full h-80"
    :series="[[
        'id' => 'sales',
        'name' => 'Sales',
        'color' => '#0F766E',
        'points' => [
            ['id' => 'sales-jan', 'x' => '2026-01-01', 'label' => 'Jan', 'value' => 42000],
            ['id' => 'sales-feb', 'x' => '2026-02-01', 'label' => 'Feb', 'value' => 51800],
        ],
    ]]"
    :x-axis="['type' => 'date', 'dateFormat' => 'medium', 'timeZone' => 'America/Managua']"
    :y-axis="['valueFormat' => 'currency', 'currencyCode' => 'NIO', 'maximumFractionDigits' => 0]"
    locale="es-NI"
    _select="pointSelected"
    a11y-label="Monthly sales in córdobas"
/>
</code-snippet>
@endverbatim

- A series needs `id`, `name`, and ordered `points`; `color` and `style` are optional. A point needs `label` and `value`; `id` is optional but required in practice for stable selection. Line and area series may set `fill_to` to another series ID.
- `x-axis.type` is `category` (default; scatter defaults to `number`), `number`, `date` (`YYYY-MM-DD`), or `datetime` (RFC 3339 with offset). Continuous axes require `x` on every point. The x axis accepts `title`, `visible`, `labelCount` (2–12), `minimum`, `maximum`, `baseline`, `interval`, `dateFormat` (`short|medium|long|full|time`), and `timeZone` (IANA; `timezone` is accepted as an alias). Category axes reject domain keys.
- `y-axis` accepts `valueFormat`, `currencyCode`, `minimumFractionDigits`, `maximumFractionDigits`, `beginAtZero`, `title`, `visible`, `labelCount`, `minimum`, `maximum`, `baseline`, `interval`.
- Other Cartesian attributes: `show-grid`, `show-points`, `begin-at-zero` (candlestick defaults to `false`), `annotations` (`type` `line` with `value`, or `band` with `from`/`to`; each needs `id` and `axis` `x|y`), `interaction` (`enabled`, `mode` `tap|scrub`, `crosshair` `none|x|y|both`, `tooltip` `single|shared`), `viewport` (`enabled`, `minimum`, `maximum`, `pan`, `zoom`, `minimumSpan`; continuous x axes only, bounds required when enabled), `on-viewport-change`, and `sampling` (`mode` `none|lttb`, `threshold` 3–100000; LTTB only on line, area, and scatter, and not with `fill_to` or stacked areas).
- Area: `area-mode="overlay|stacked"`. Bar: `mode="grouped|stacked"` and `orientation="vertical|horizontal"`. Candlestick points use `open`, `high`, `low`, `close` instead of `value`.
- `scrub` interaction cannot be combined with an enabled, pannable viewport.

### Radial example (pie, donut)

@verbatim
<code-snippet name="Donut chart" lang="blade">
<native:donut-chart
    class="w-full h-72"
    :segments="[
        ['id' => 'web', 'label' => 'Web', 'value' => 68, 'color' => '#2563EB'],
        ['id' => 'store', 'label' => 'Store', 'value' => 32],
    ]"
    :inner-radius-ratio="0.62"
    value-format="percent"
    a11y-label="Orders by channel"
/>
</code-snippet>
@endverbatim

- Segments need `id`, `label`, and a non-negative `value`; `color` is optional. A non-empty list needs at least one positive value. Donut `inner-radius-ratio` is 0.2–0.85 (default 0.6).

### Specialized example (contribution heatmap)

@verbatim
<code-snippet name="Contribution heatmap" lang="blade">
<native:contribution-heatmap
    class="w-full h-64"
    :values="[
        ['id' => '2026-09-15', 'date' => '2026-09-15', 'value' => 3, 'label' => '3 workouts'],
        ['id' => '2026-09-16', 'date' => '2026-09-16', 'value' => 7, 'label' => '7 workouts'],
    ]"
    end-date="2026-09-30"
    :days="90"
    :week-starts-on="1"
    a11y-label="Workouts in the last 90 days"
/>
</code-snippet>
@endverbatim

- Heatmap `values`: up to 10,000 entries with unique `id` and unique `date`, non-negative `value`, optional `label`. Attributes: `end-date` (defaults to today, UTC), `days` (7–371, default 365), `week-starts-on` (0 = Sunday … 6 = Saturday, default 1), `colors` (list of 2–9), `empty-color`, `show-month-labels`, `show-weekday-labels`.
- Radar: `axes` is 3–24 entries with `id`, `label`, positive `maximum`; each series has `id`, `name`, optional `color`, and `values` with one `['axis' => …, 'value' => …]` per axis in declared order, between 0 and that axis maximum. Also `grid-levels` (2–10) and `fill-opacity` (0–1).
- Progress: `metrics` is up to 24 entries with `id`, `label`, `value` from 0 to 1, optional `color`; plus `center-label`.

### Attributes shared by all ten charts

- `a11y-label`, `empty-label`, `error-label` (shown when the native renderer rejects a snapshot), `animated` (system reduced-motion settings win).
- `theme="light|dark|system"` (default `system`) and `preset` (`default`, `spectrum`, `contrast`, or an application preset registered under `presets` in `config/nativephp-charts.php`; publish it with `php artisan vendor:publish --tag=nativephp-charts-config`).
- `locale` (BCP-47, defaults to the device), `value-format` (`number|currency|percent`; percent values are fractions), `currency-code` (required for currency), `minimum-fraction-digits` and `maximum-fraction-digits` (0–8).
- `legend` (`visible` `true|false|'auto'`, `position` `top|bottom|leading|trailing`, `alignment` `start|center|end`, `style`) and `style` (platform-neutral sections such as `line`, `area`, `bar`, `candlestick`, `points`, `grid`, `axis`, `segment`, `ring`, `cell`; each chart accepts only its own sections).
- Colors accept `#RGB`, `#RRGGBB`, `#RRGGBBAA`, `black`, `white`, `transparent`. Fonts in `style.axis.font` and `legend.style.font` accept NativePHP font tokens or aliases.

### Callbacks

Bind selection with `_select="method"` (or `on-select`, or `->onSelect('method')`). Use the underscore form: an `@select` attribute is compiled as a child-component event, not a chart callback. The method receives a JSON string; decode it with `Donmanueldev\NativephpCharts\PointSelection::fromJson($payload)` and use `pointId`, `seriesId`, `x`, and `value` for logic (`localizedValue` is display text). Decode `on-viewport-change` payloads with `Donmanueldev\NativephpCharts\ViewportChange::fromJson($payload)`. Both payloads carry `version: 1`.

Blade attributes are kebab-case; the fluent API uses camelCase methods with the same names (`->series()`, `->xAxis()`, `->areaMode()`, `->mode()`, `->orientation()`, `->innerRadiusRatio()`, `->endDate()`, `->onViewportChange()`).
