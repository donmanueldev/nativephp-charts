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
3. Add [selection callbacks](/nativephp-charts/guides/callbacks-interactions/) and [theme presets](/nativephp-charts/guides/themes/) as needed.

## Choose a chart

| What you want to show | Chart |
| --- | --- |
| Change over time | [Line](/nativephp-charts/charts/line/) or [area](/nativephp-charts/charts/area/) |
| Comparison between categories | [Bar](/nativephp-charts/charts/bar/) |
| Relationship between numeric values | [Scatter](/nativephp-charts/charts/scatter/) |
| Parts of a total | [Pie](/nativephp-charts/charts/pie/) or [donut](/nativephp-charts/charts/donut/) |
| Comparison across several dimensions | [Radar](/nativephp-charts/charts/radar/) |
| Open, high, low, and close prices | [Candlestick](/nativephp-charts/charts/candlestick/) |
| Progress toward goals | [Progress](/nativephp-charts/charts/progress/) |
| Daily activity over a date range | [Contribution heatmap](/nativephp-charts/charts/contribution-heatmap/) |

## Data and updates

Chart data is validated in PHP before rendering. Each chart guide documents its required fields and supported options; invalid values raise an `InvalidArgumentException`. Empty data is supported and displays the chart's `empty-label`.

Use explicit, stable IDs for series and their points, segments, metrics, or days. Callbacks return those IDs so your application can identify an item after values change or data is reordered. Update the component's data and let its next render update the chart.

## Platform scope

The plugin targets NativePHP Mobile 4.x, iOS 18.2+, and Android API 26+. It renders native views and does not supply a browser chart or NativePHP Desktop integration.

Use the [API reference](/nativephp-charts/reference/api/) to look up shared properties, and each chart's guide for chart-specific options and platform limitations.
