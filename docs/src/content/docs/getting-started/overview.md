---
title: Overview
description: Add native charts to NativePHP Mobile screens with Blade and PHP.
sidebar:
  order: 1
---

NativePHP Charts adds ten chart types to NativePHP Mobile. Declare charts in Blade, pass data from PHP, and handle selections in your `NativeComponent`. Rendering uses Swift Charts and SwiftUI on iOS, and Jetpack Compose Canvas on Android.

## Start here

1. [Install the plugin](/nativephp-charts/getting-started/installation/) and create a native screen.
2. Choose a chart below and adapt its data example.
3. Add [selection callbacks](/nativephp-charts/guides/callbacks-interactions/) when a selected item must update component state, and choose a [theme preset](/nativephp-charts/guides/themes/) when the screen needs a defined palette.

## Choose a chart

| User decision | Chart | Use another chart when |
| --- | --- | --- |
| How did a value change in order? | [Line](/nativephp-charts/charts/line/) | Categories have no inherent order: use bar. |
| How did magnitude accumulate over time? | [Area](/nativephp-charts/charts/area/) | Users must compare several series precisely: use line or bar. |
| Which independent category is larger? | [Bar](/nativephp-charts/charts/bar/) | The x values form a continuous trend: use line. |
| How is one total divided? | [Pie](/nativephp-charts/charts/pie/) or [donut](/nativephp-charts/charts/donut/) | Precise comparison between many categories matters: use bar. |
| Which dimensions are strong or weak on the same scale? | [Radar](/nativephp-charts/charts/radar/) | Dimensions use different units or need exact values: use bar. |
| Which days show activity patterns? | [Contribution heatmap](/nativephp-charts/charts/contribution-heatmap/) | The reader must compare exact values between dates: use line or bar. |
| How close are a few bounded goals? | [Progress](/nativephp-charts/charts/progress/) | The value is indeterminate or represents loading: use a native progress indicator. |
| What was the open, high, low, and close range? | [Candlestick](/nativephp-charts/charts/candlestick/) | Only a closing trend matters: use line. |
| Are two numeric values related? | [Scatter](/nativephp-charts/charts/scatter/) | One value is categorical: use bar. |

Keep pie and donut to a small number of meaningful parts. Use a date or datetime x axis for continuous time data and stable item IDs whenever selection must survive data updates.

## Data and updates

Chart data is validated in PHP before rendering. Each chart guide documents its required fields and supported options; invalid values raise an `InvalidArgumentException`. Empty data is supported and displays the chart's `empty-label`.

Use explicit, stable IDs for series and their points, segments, metrics, or days. Callbacks return those IDs so your application can identify an item after values change or data is reordered. Update the component's data and let its next render update the chart.

## Platform scope

The plugin targets NativePHP Mobile 4.x, iOS 18.2+, and Android API 26+. It renders native views and does not supply a browser chart or NativePHP Desktop integration.

Use the [API reference](/nativephp-charts/reference/api/) to look up shared properties, and each chart's guide for chart-specific options and platform limitations.
