---
title: Performance and validation
description: Size datasets, profile native rendering, and validate interactions in your app.
sidebar: { order: 5 }
---

Chart performance depends on the device, number of points, labels, animation, and enabled gestures. Measure the chart inside your app's actual screen and scrolling layout.

## Start with the visible data

- Send the date range or categories the screen needs, rather than the full history.
- Limit labels and visible series when they make the chart difficult to read.
- Disable animation with `:animated="false"` when frequently replacing a large dataset.
- Use a [viewport](/nativephp-charts/guides/viewport-gestures/) when users need to inspect a continuous range. A viewport controls the visible domain; it does not fetch or paginate data.

## Sampling

LTTB reduces the points drawn in supported line, area, and scatter charts. It can change which observations are visible, so compare the sampled result against the original data before enabling it. Source IDs and indices identify the original selected point.

Bar, candlestick, and stacked area charts reject LTTB. In particular, removing a candle could hide a price extreme. See the [sampling options](/nativephp-charts/reference/api/#sampling) for supported values and limits.

## Profile representative datasets

Measure a small dataset, your typical dataset, and the largest dataset the app allows. Keep the device, build configuration, data, and interaction sequence fixed when comparing changes.

| Measure | What to check |
| --- | --- |
| Payload size | Serialized bytes passed from PHP to the native renderer |
| First render | Time until the chart appears after opening the screen |
| Frame timing | Slow frames during updates, scrolling, selection, pan, and zoom |
| Memory | Peak usage and whether it recovers after leaving the screen |
| Callbacks | One selection callback per completed interaction, without duplicate application updates |

Use Android Studio's profiler or Perfetto on Android and Instruments on iOS. Simulator timings are useful for diagnosing changes, but measure final frame and memory behavior on the devices you support.

## Validate in your app

PHP validation checks data shapes and options. A successful native build checks compilation. Neither verifies how gestures, scrolling, or accessibility behave in your screen.

Before shipping, test empty and populated data, updates and reordered IDs, selection, navigation away and back, and enabled viewport gestures. Check VoiceOver or TalkBack, larger text, light and dark appearance, and reduced motion on each platform you support.

The chart guides show native previews of default and selected states. Those images demonstrate appearance; they are not a performance benchmark or a guarantee for your app's device and dataset.
