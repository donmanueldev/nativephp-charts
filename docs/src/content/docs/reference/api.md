---
title: API reference
description: Blade attributes, data requirements, defaults, and chart-specific options.
---

Use self-closing `<native:… />` elements inside a NativePHP Mobile view. Bind PHP arrays, numbers, and booleans with `:`; pass literal strings without it.

```blade
<native:line-chart
    :series="$series"
    :animated="false"
    :y-axis="['valueFormat' => 'currency', 'currencyCode' => 'USD']"
    a11y-label="Monthly revenue in US dollars"
/>
```

Start with [installation](/nativephp-charts/getting-started/installation/) for a complete first chart. These tables describe public Blade attributes; the package handles native JSON fields.

## Common attributes

These attributes are accepted by all ten chart elements.

| Attribute | Accepted value | Default |
| --- | --- | --- |
| `animated` | Boolean; system reduced-motion settings take precedence | `true` |
| `empty-label` | Non-empty text for an empty dataset | `No data` |
| `error-label` | Non-empty text for a snapshot rejected by the native renderer | `Chart unavailable` |
| `a11y-label` | Non-empty chart description for assistive technology | `Chart` |
| `theme` | `light`, `dark`, `system` | `system` |
| `preset` | Built-in or application-defined preset name | `default` |
| `style` | Array of chart-specific style sections; see below | `[]` |
| `legend` | Array of visibility, placement, and text options | Automatic visibility |
| `locale` | BCP-47 locale tag, such as `en-US` or `es-NI` | System locale |
| `value-format` | `number`, `currency`, `percent` | `number` |
| `currency-code` | Three-letter currency code; required for `currency` | Unset |
| `minimum-fraction-digits` | Integer from `0` to `8` | Native formatter default |
| `maximum-fraction-digits` | Integer from `0` to `8`; must be at least the configured minimum | Native formatter default |
| `_select` | Callback method name | No callback |

Colors accept `#RGB`, `#RRGGBB`, CSS `#RRGGBBAA`, `black`, `white`, or `transparent`. Omit a series, segment, or metric color to use the preset palette. See [themes and presets](/nativephp-charts/guides/themes/) for overrides.

### Legend

For charts with series, segments, or metrics, `visible: 'auto'` shows the legend when there is more than one item. The contribution heatmap has no series legend.

| Key | Accepted value | Default |
| --- | --- | --- |
| `visible` | `true`, `false`, `'auto'` | `'auto'` |
| `position` | `top`, `bottom`, `leading`, `trailing` | `bottom` |
| `alignment` | `start`, `center`, `end` | `center` |
| `style` | `font`, `fontSize`, `labelColor`, `markerSize` | Preset/native defaults |

`fontSize` and `markerSize` must be greater than `0` and at most `32`.

## Cartesian charts

[Line](/nativephp-charts/charts/line/), [area](/nativephp-charts/charts/area/), [bar](/nativephp-charts/charts/bar/), [scatter](/nativephp-charts/charts/scatter/), and [candlestick](/nativephp-charts/charts/candlestick/) accept these shared attributes.

| Attribute | Purpose and default |
| --- | --- |
| `series` | Ordered list of series; defaults to `[]` |
| `x-axis` | Category, numeric, or temporal axis; defaults to `category`, except scatter (`number`) |
| `y-axis` | Numeric scale and formatting; defaults to number formatting |
| `show-grid` | Grid visibility; defaults to `true` |
| `begin-at-zero` | Include zero in the automatic value domain; defaults to `true`, except candlestick (`false`) |
| `interaction` | Selection settings; defaults to enabled tap, x crosshair, single tooltip |
| `viewport` | Initial x-axis range and gestures; disabled by default |
| `on-viewport-change` | Callback after a viewport gesture settles; unset by default |
| `sampling` | Point reduction; defaults to `['mode' => 'none', 'threshold' => 1000]` |
| `annotations` | Ordered list of reference lines or bands, with optional labels; defaults to `[]` |

### Series and points

Each series requires a unique, non-empty `id`, a non-empty `name`, and an ordered `points` list. `color` and `style` are optional. Series styles cannot override chart-level `grid` or `axis` settings.

Regular points require a non-empty `label` and a finite numeric `value`. A point `id` is optional, but use explicit IDs to keep selections stable when data changes. Point IDs must be unique within their series. Without an ID, the package derives one from the series, label, and point position.

| `x-axis.type` | Point `x` |
| --- | --- |
| `category` | Optional non-empty string; defaults to `label` |
| `number` | Required finite integer or float |
| `date` | Required `YYYY-MM-DD` string or PHP `DateTimeInterface` |
| `datetime` | Required RFC 3339 string with `Z` or an explicit offset, or PHP `DateTimeInterface` |

Numeric strings are not accepted as numbers. Integers must be within the exact cross-platform range of `−9,007,199,254,740,991` to `9,007,199,254,740,991`.

Candlestick points use `open`, `high`, `low`, and `close` instead of `value`. Both open and close must lie between low and high, and `low` must be strictly less than `high`. The chart accepts zero or one series.

### Axes

Both axis arrays accept `visible`, `title`, `labelCount` (`2`–`12`), `minimum`, `maximum`, `baseline`, and a positive `interval`. Category x axes reject `minimum`, `maximum`, `baseline`, and `interval`. Continuous x bounds use the same types as point `x`; y bounds are numbers. When both bounds are supplied, minimum must be less than maximum, and a supplied baseline must be within them.

The x axis also accepts `dateFormat`: `short`, `medium` (default), `long`, `full`, or `time`; and `timeZone`: an IANA name such as `America/Managua`.

The y axis also accepts `valueFormat`, `currencyCode`, `minimumFractionDigits`, `maximumFractionDigits`, and `beginAtZero`. Formatting follows the common attribute rules above; y-axis settings override matching top-level formatting settings when both are supplied.

### Family-specific options

| Chart | Options and restrictions |
| --- | --- |
| Line | `show-points` defaults to `true`; optional series `fill_to` references another series ID |
| Area | `area-mode`: `overlay` (default) or `stacked`; `show-points` defaults to `true`; supports `fill_to` |
| Bar | `mode`: `grouped` (default) or `stacked`; `orientation`: `vertical` (default) or `horizontal` |
| Scatter | Numeric x axis by default; point appearance uses `style.points` |
| Candlestick | At most one OHLC series; automatic value domain excludes zero by default |

### Sampling

LTTB sampling (`mode: 'lttb'`) is available only for line, area, and scatter. Set `threshold` to an integer from `3` to `100000`. LTTB cannot be combined with `fill_to` or stacked area mode. Bar and candlestick reject LTTB because removing observations would change their meaning.

### Annotations

Add reference lines or bands to any Cartesian chart. Each annotation requires a unique, non-empty `id`, a `type` (`line` or `band`), and an `axis` (`x` or `y`). For example, add a revenue target and a target range to the line chart above:

```blade
:annotations="[
    ['id' => 'target', 'type' => 'line', 'axis' => 'y', 'value' => 5000, 'label' => 'Target'],
    ['id' => 'range', 'type' => 'band', 'axis' => 'y', 'from' => 4000, 'to' => 6000, 'opacity' => 0.1],
]"
```

| Type | Required values | Optional appearance |
| --- | --- | --- |
| `line` | `value` | `width`: `0.000001`–`16`, default `1` |
| `band` | `from`, `to`; continuous axes require `from < to` | `opacity`: `0`–`1`, default `0.12` |

Both types accept a non-empty `label` and a `color` (default `#6366F1`). Y-axis values are finite numbers; x-axis values match the declared x type, including category strings. A line does not accept band options, and a band does not accept `value` or `width`.

## Radial and specialized charts

All data below uses ordered lists. Empty data displays `empty-label`; radar still requires its declared axes.

| Element | Data requirements | Additional attributes |
| --- | --- | --- |
| [Pie](/nativephp-charts/charts/pie/) | `segments`: unique `id`, `label`, non-negative `value`, optional `color`; a non-empty list needs a positive value | None |
| [Donut](/nativephp-charts/charts/donut/) | Same `segments` contract as pie | `inner-radius-ratio`: `0.2`–`0.85`, default `0.6` |
| [Radar](/nativephp-charts/charts/radar/) | `axes`: `3`–`24` unique axes with `id`, `label`, positive `maximum`; `series`: `id`, `name`, optional `color`, and one `{axis, value}` entry per axis, in declared order; values range from zero to the axis maximum | `grid-levels`: `2`–`10`, default `5`; `fill-opacity`: `0`–`1`, default `0.22` |
| [Progress](/nativephp-charts/charts/progress/) | `metrics`: up to `24` entries with unique `id`, `label`, `value` from `0` to `1`, optional `color` | `center-label`: optional text |
| [Contribution heatmap](/nativephp-charts/charts/contribution-heatmap/) | `values`: up to `10000` entries with unique `id` and `date` (`YYYY-MM-DD`), non-negative `value`, optional `label` | See the heatmap page for the date window, color scale, and label settings |

## Style sections

Use only the sections listed for the chart family. Unsupported sections and keys are rejected.

| Chart | Accepted `style` sections |
| --- | --- |
| Line | `line`, `points`, `grid`, `axis` |
| Area, radar | `line`, `area`, `points`, `grid`, `axis` |
| Bar | `bar`, `grid`, `axis` |
| Scatter | `points`, `grid`, `axis` |
| Candlestick | `bar`, `candlestick`, `grid`, `axis` |
| Pie, donut | `segment` |
| Progress | `ring` |
| Contribution heatmap | `cell`, `axis` |

### Style options

Each section is an array. All options are optional; omitted values use the preset or renderer defaults. Colors follow the formats listed under [common attributes](#common-attributes). Numeric values must be finite PHP integers or floats. Ranges below include both endpoints unless shown as `> 0`.

| Section | Supported keys and values |
| --- | --- |
| `line` | `color`; `width`: `> 0` to `16`; `interpolation`: `linear`, `smooth`, `step_before`, `step_after`; `dash`: an ordered list of `2`, `4`, `6`, or `8` numbers, each `> 0` to `128` |
| `area` | `opacity`: `0`–`1`; `gradient`: boolean |
| `bar` | `radius`: `0`–`32`; `width`: `> 0` to `128` |
| `candlestick` | `risingColor`, `fallingColor`, `neutralColor`; `wickWidth`: `> 0` to `8` |
| `segment` | `gap`: `0`–`12`; `cornerRadius`: `0`–`20`; `opacity`: `0`–`1` |
| `ring` | `trackColor`; `width`: `> 0` to `64`; `gap`: `0`–`32`; `cap`: `round` or `butt` |
| `cell` | `size`: `> 0` to `64`; `gap`: `0`–`16`; `cornerRadius`: `0`–`16` |
| `points` | `visible`: boolean; `color`; `size`: `> 0` to `24` |
| `grid` | `visible`: boolean; `color`; `width`: `> 0` to `8` |
| `axis` | `visible`: boolean; `color`, `labelColor`; `font`: non-empty font name; `fontSize`: `> 0` to `32`; `labelCount`: integer `2`–`12` |

Radar rejects `axis.labelCount`. The camelCase keys in this table also accept snake_case aliases, such as `wick_width` and `label_color`. `bar.radius` also accepts `cornerRadius` or `corner_radius`.

For an area chart, configure the outline and fill together:

```blade
:style="[
    'line' => ['width' => 2, 'interpolation' => 'smooth'],
    'area' => ['opacity' => 0.25, 'gradient' => true],
    'points' => ['visible' => false],
]"
```

Use the family table above before combining sections: for example, `area` is valid for area and radar charts but rejected by line charts.

## Callbacks and validation

Use `PointSelection::fromJson($payload)` for `_select` callbacks and `ViewportChange::fromJson($payload)` for settled x-axis changes. Both validate version 1 payloads and throw `InvalidArgumentException` for invalid input. See [callbacks and interactions](/nativephp-charts/guides/callbacks-interactions/) and [viewport and gestures](/nativephp-charts/guides/viewport-gestures/) for handlers and configuration.

Invalid PHP data or options throw `InvalidArgumentException` before rendering. `error-label` applies to a snapshot rejected by the native renderer; it does not catch PHP exceptions. Empty lists are valid except where a chart requires structural data, such as radar axes.
