---
title: Themes and presets
description: Choose light or dark appearance, use built-in palettes, and define application presets.
sidebar: { order: 1 }
---

Every chart accepts a `theme` and a `preset`:

| Property | Accepted values | Default |
| --- | --- | --- |
| `theme` | `light`, `dark`, `system` | `system` |
| `preset` | `default`, `spectrum`, `contrast`, or a registered application preset | `default` |

`system` follows the host appearance. A preset supplies colors for both appearances, including the chart background, text, grid, and data palette.

In the [installation example](/nativephp-charts/getting-started/installation/#add-the-chart), add these attributes to the existing line chart:

```blade
    theme="system"
    preset="spectrum"
    :style="['line' => ['width' => 3]]"
```

Remove the series' explicit `color` if you want it to use the preset palette.

## Register an application preset

Publish the configuration from your application's root directory:

```bash
php artisan vendor:publish --tag=nativephp-charts-config --no-interaction
```

Add your preset to `presets` in `config/nativephp-charts.php`:

```php
<?php

return [
    'presets' => [
        'product' => [
            'light' => [
                'background' => '#FFFFFF',
                'foreground' => '#111111',
                'palette' => ['#ED3F16', '#2563EB', '#0F766E'],
            ],
            'dark' => [
                'background' => '#080808',
                'foreground' => '#FFFFFF',
                'palette' => ['#FF7A4D', '#60A5FA', '#2DD4BF'],
            ],
        ],
    ],
];
```

Use `preset="product"` on a chart. A new preset inherits unspecified values from `default`; an application preset named `spectrum`, for example, overrides the built-in `spectrum` values instead. You can override either or both appearances.

## Available tokens

| Token | Purpose |
| --- | --- |
| `background` | Chart background |
| `foreground` | Primary text |
| `muted` | Secondary text |
| `grid` | Grid lines |
| `track` | Unfilled progress track |
| `error` | Error-state text |
| `palette` | Ordered list of 1–24 data colors |

Colors accept CSS hex (`#RGB`, `#RRGGBB`, or `#RRGGBBAA`), `black`, `white`, and `transparent`. Tailwind color names and CSS functions such as `rgb()` are not accepted by chart presets.

Explicit series, segment, or metric colors override the palette. Chart-specific `style` options control geometry such as line width and point size; see the [style options reference](/nativephp-charts/reference/api/#style-options) for supported keys and values. Unknown preset names, unsupported appearance tokens, invalid colors, and empty palettes raise an `InvalidArgumentException`.
