---
title: Callbacks and interactions
description: Handle chart selections in a NativeComponent and configure Cartesian chart gestures.
sidebar: { order: 2 }
---

Bind `_select` to a public method on the `NativeComponent` that renders the chart. The method receives the selection as a JSON string. Native selection and tooltip updates do not need to wait for PHP.

## Handle a selection

Start with the `SalesDashboard` screen from [installation](/nativephp-charts/getting-started/installation/). Add this import to `app/NativeComponents/SalesDashboard.php`:

```php
use Donmanueldev\NativephpCharts\PointSelection;
```

Add the property and method inside the `SalesDashboard` class, alongside `render()`:

```php
public ?string $selectedPoint = null;

public function selectPoint(string $payload): void
{
    $selection = PointSelection::fromJson($payload);

    $this->selectedPoint = "{$selection->label}: {$selection->localizedValue}";
}
```

Add `_select="selectPoint"` to the existing `<native:line-chart>` in `resources/views/native/sales-dashboard.blade.php`. Place this text below the chart, inside the same `<native:column>`:

```blade
@if ($selectedPoint !== null)
    <native:text>{{ $selectedPoint }}</native:text>
@endif
```

Tapping a point now updates the text below the chart. For charts created with the fluent PHP interface, use `->onSelect('selectPoint')`.

## Selection payload

`PointSelection::fromJson()` validates the JSON and returns a typed object. Invalid JSON, unsupported versions, missing fields, and invalid field values raise an `InvalidArgumentException`.

| Object property | Meaning |
| --- | --- |
| `version` | Payload version; currently `1` |
| `chartType` | Chart family, such as `line` or `contribution_heatmap` |
| `seriesId`, `seriesName` | Selected series identity and display name |
| `pointId` | Stable selected item ID |
| `pointIndex` | Original zero-based index in the source data, including after sampling |
| `xType`, `x` | X type (`category`, `number`, `date`, `datetime`) and its value |
| `label` | Selected item's display label |
| `value` | Raw numeric value |
| `localizedValue` | Formatted value for display |

Use IDs, `value`, and `x` for application logic. Use `localizedValue` only as presentation text.

Pie and donut use the segment ID for both `seriesId` and `pointId`. Progress uses the metric ID for both. Contribution heatmap uses the day ID for both and a `date` x value.

## Cartesian interaction options

Line, area, bar, scatter, and candlestick charts accept an `interaction` map. Add it to the chart alongside its existing data attributes:

```blade
    :interaction="['mode' => 'tap', 'crosshair' => 'x', 'tooltip' => 'single']"
```

| Option | Values | Default |
| --- | --- | --- |
| `enabled` | Boolean; disables selection interaction when `false` | `true` |
| `mode` | `tap` or `scrub` | `tap` |
| `crosshair` | `none`, `x`, `y`, or `both` | `x` |
| `tooltip` | `single` or `shared` | `single` |

`tap` selects one item. `scrub` previews nearby values while dragging and emits a callback when the gesture completes. `shared` tooltips show values from multiple series at the selected x position. Crosshair and tooltip options apply to Cartesian charts; radial and other chart families use their own selection presentation.

A selection callback is not a per-frame gesture stream. For a chart inside a scrolling screen, start with `tap`. Scrubbing cannot be combined with an enabled panning viewport; see [viewport and gestures](/nativephp-charts/guides/viewport-gestures/).

Use explicit IDs for interactive data. Automatically generated point IDs can change when the data is reordered.

## Build a chart from PHP

Use the fluent API when the series is assembled in a NativeComponent. This keeps the data, formatting, selection callback, and accessible label together.

```php
use Donmanueldev\NativephpCharts\Elements\LineChart;

public function revenueChart(): LineChart
{
    return LineChart::make()
        ->series($this->revenueSeries)
        ->theme('dark')
        ->locale('es-NI')
        ->valueFormat('currency')
        ->currencyCode('NIO')
        ->legend(['visible' => true, 'position' => 'bottom'])
        ->style([
            'line' => ['width' => 3],
            'points' => ['size' => 5],
            'grid' => ['visible' => true],
        ])
        ->onSelect('selectPoint')
        ->a11yLabel('Ingresos mensuales');
}
```

Pass the element to the Blade view and render it in the native screen:

```blade
<native:column class="p-5 gap-4">
    {{ $this->revenueChart() }}

    @if ($selectedPoint)
        <native:text>{{ $selectedPoint }}</native:text>
    @endif
</native:column>
```

## Refresh data from a selection

Selection is a useful input for the next query. Keep the selected ID in component state, rebuild the series with stable IDs, and let the native chart receive the new snapshot.

```php
public string $range = '30d';

public function selectRange(string $range): void
{
    $this->range = $range;
    $this->revenueSeries = [[
            'id' => 'revenue',
            'name' => 'Revenue',
            'points' => Revenue::forRange($range)->map(fn (Revenue $row) => [
                'id' => $row->recorded_at->toDateString(),
                'label' => $row->recorded_at->format('M j'),
                'value' => $row->amount,
                'x' => $row->recorded_at->toAtomString(),
            ])->all(),
        ]];
}
```

Use a selection callback for decisions made from the chart. Do not use it for animation or pointer-position updates.
