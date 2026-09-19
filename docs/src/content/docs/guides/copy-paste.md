---
title: Copy-paste examples
description: Complete Blade and NativeComponent examples for common chart screens.
sidebar: { order: 2 }
---

Each example has the data owned by the `NativeComponent`, the chart in Blade, and a concrete interaction outcome. Replace the query and labels, but preserve the data shape and stable IDs.

## Revenue trend with a selected month

Use a line chart when a user needs to read an ordered trend and inspect a particular point.

```php
use Donmanueldev\NativephpCharts\PointSelection;

public array $revenueSeries = [[
    'id' => 'revenue',
    'name' => 'Revenue',
    'points' => [
        ['id' => '2026-01', 'label' => 'Jan', 'x' => '2026-01-01', 'value' => 18200],
        ['id' => '2026-02', 'label' => 'Feb', 'x' => '2026-02-01', 'value' => 21400],
    ],
]];

public ?string $selectedRevenue = null;

public function selectRevenue(string $payload): void
{
    $selection = PointSelection::fromJson($payload);
    $this->selectedRevenue = "{$selection->label}: {$selection->localizedValue}";
}
```

```blade
<native:line-chart
    class="w-full h-72"
    :series="$revenueSeries"
    :x-axis="['type' => 'date']"
    locale="en-US"
    value-format="currency"
    currency-code="USD"
    _select="selectRevenue"
    a11y-label="Monthly revenue"
/>

@if ($selectedRevenue)
    <native:text>{{ $selectedRevenue }}</native:text>
@endif
```

The selected text updates after a completed tap. Use the selected ID for queries; use `localizedValue` only for display.

## Store comparison in córdobas

Use a bar chart for independent categories. The `locale` changes number punctuation; `currency-code` chooses the monetary symbol and rules.

```php
public array $storeSales = [[
    'id' => 'sales',
    'name' => 'Sales',
    'points' => [
        ['id' => 'managua', 'label' => 'Managua', 'value' => 84500],
        ['id' => 'leon', 'label' => 'León', 'value' => 61200],
        ['id' => 'granada', 'label' => 'Granada', 'value' => 48900],
    ],
]];
```

```blade
<native:bar-chart
    class="w-full h-72"
    :series="$storeSales"
    locale="es-NI"
    value-format="currency"
    currency-code="NIO"
    :legend="['visible' => false]"
    a11y-label="Ventas por tienda"
/>
```

Each point's `label` is the category name. Do not assign numeric or date `x` values unless the comparison needs a continuous axis.

## Daily delivery goals

Progress values are normalized from `0` to `1`; pass `0.92` for 92%.

```php
public array $deliveryGoals = [
    ['id' => 'orders', 'label' => 'Orders delivered', 'value' => 0.92],
    ['id' => 'returns', 'label' => 'Returns resolved', 'value' => 0.76],
];
```

```blade
<native:progress-chart
    class="w-full h-64"
    :metrics="$deliveryGoals"
    center-label="Today"
    value-format="percent"
    :maximum-fraction-digits="0"
    :legend="['visible' => true]"
    :style="['ring' => ['width' => 10, 'gap' => 8, 'cap' => 'round']]"
    a11y-label="Daily delivery goals"
/>
```

Use progress for a short set of goals sharing the same completion scale. It does not represent an indeterminate loading state.

## Ninety days of habit activity

Heatmaps show daily patterns. Bind the visible date window explicitly so the query and chart represent the same period.

```php
public array $habitActivity = [
    ['id' => '2026-03-01', 'date' => '2026-03-01', 'label' => '3 habits completed', 'value' => 3],
    ['id' => '2026-03-02', 'date' => '2026-03-02', 'label' => '1 habit completed', 'value' => 1],
];
```

```blade
<native:contribution-heatmap
    :values="$habitActivity"
    end-date="2026-03-31"
    :days="90"
    :week-starts-on="1"
    :style="['cell' => ['size' => 14, 'gap' => 3, 'cornerRadius' => 3]]"
    a11y-label="Completed habits during the last 90 days"
/>
```

`date` must use `YYYY-MM-DD`; duplicate dates and negative values are rejected. Values outside the configured window are not shown.

See [callbacks](/nativephp-charts/guides/callbacks-interactions/) for the typed selection object and [API reference](/nativephp-charts/reference/api/) for every accepted attribute.
