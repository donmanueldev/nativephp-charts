---
title: Viewport and gestures
description: Configure x-axis pan and zoom, range limits, and settled viewport callbacks.
sidebar: { order: 3 }
---

Line, area, bar, scatter, and candlestick charts support an initial viewport on `number`, `date`, or `datetime` x axes. Category axes do not support viewports.

## Set an initial range

Each point must include a date `x`, such as `'2026-01-15'`. This example opens January through March and limits zoom to a seven-day span:

```blade
<native:line-chart
    class="w-full h-80"
    :series="[[
        'id' => 'revenue',
        'name' => 'Revenue',
        'points' => [
            ['id' => 'jan', 'label' => 'January', 'x' => '2026-01-01', 'value' => 18],
            ['id' => 'feb', 'label' => 'February', 'x' => '2026-02-15', 'value' => 26],
            ['id' => 'mar', 'label' => 'March', 'x' => '2026-03-31', 'value' => 31],
        ],
    ]]"
    :x-axis="['type' => 'date']"
    :viewport="[
        'enabled' => true,
        'minimum' => '2026-01-01',
        'maximum' => '2026-03-31',
        'pan' => true,
        'zoom' => true,
        'minimumSpan' => 7 * 24 * 60 * 60,
    ]"
    on-viewport-change="viewportChanged"
    a11y-label="Daily revenue"
/>
```

| Option | Type and behavior | Default |
| --- | --- | --- |
| `enabled` | Boolean; enables the configured range | `false` |
| `minimum`, `maximum` | Bounds matching the x-axis type; supply both with `minimum < maximum` | Required when enabled |
| `pan` | Boolean; enables one-finger viewport panning | `true` |
| `zoom` | Boolean; enables pinch zoom | `true` |
| `minimumSpan` | Positive number no greater than the initial range | One thousandth of the full domain, with a floor of `0.000001` axis units |

`minimumSpan` uses **axis units for number axes and seconds for date/datetime axes**. Seven days is `604800`, not `7`. A numeric axis with `minimumSpan: 7` uses seven numeric units.

## Handle a range change

The renderer emits one callback after the gesture settles. Import `ViewportChange` before your `NativeComponent` class, and add the property and method inside the class, as in the [selection callback guide](/nativephp-charts/guides/callbacks-interactions/#handle-a-selection):

```php
use Donmanueldev\NativephpCharts\ViewportChange;

public array $visibleRange = [];

public function viewportChanged(string $payload): void
{
    $change = ViewportChange::fromJson($payload);

    $this->visibleRange = [
        'minimum' => $change->minimum,
        'maximum' => $change->maximum,
    ];
}
```

The decoded event exposes `chartType`, `axis`, `reason`, `xType`, `minimum`, and `maximum`. Version 1 reports only the `x` axis, with reason `pan`, `zoom`, or `pan_zoom`. Bounds retain the declared x type. Invalid payloads throw `InvalidArgumentException`.

Use the callback to store the visible range or load additional data. It is not a per-frame gesture stream.

## Combine selection and scrolling

Tap selection is the default and can be combined with viewport pan and zoom. Scrub selection uses the same one-finger gesture as panning, so the package rejects `interaction.mode: 'scrub'` together with an enabled viewport and `pan: true`.

To allow scrub selection and pinch zoom, disable panning:

```blade
:interaction="['mode' => 'scrub']"
:viewport="[
    'enabled' => true,
    'minimum' => '2026-01-01',
    'maximum' => '2026-03-31',
    'pan' => false,
    'zoom' => true,
]"
```

When the chart is inside a scroll container, verify vertical scrolling, horizontal panning, pinch zoom, and interrupted gestures on the app's target devices. The enclosing layout determines how those gestures compete. See [callbacks and interactions](/nativephp-charts/guides/callbacks-interactions/) for selection payloads.
