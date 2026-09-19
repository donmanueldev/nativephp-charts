---
title: Application recipes
description: Build product screens with validated chart data, Blade, and selection state.
sidebar: { order: 3 }
---

These recipes show the boundary between application data and the chart contract. Query and authorize data in your component or service, map it to the required shape, then bind the resulting array to Blade.

## Sales dashboard: trend and selected date

Map one ordered revenue series. The record date becomes both a stable identifier and the continuous x value.

```php
public function revenueSeries(): array
{
    return [[
        'id' => 'revenue',
        'name' => 'Revenue',
        'points' => Revenue::forAccount($this->accountId)->daily()->map(fn (Revenue $row) => [
            'id' => $row->recorded_at->toDateString(),
            'label' => $row->recorded_at->format('M j'),
            'x' => $row->recorded_at->toDateString(),
            'value' => $row->amount,
        ])->all(),
    ]];
}
```

```blade
<native:line-chart
    :series="$this->revenueSeries()"
    :x-axis="['type' => 'date']"
    value-format="currency"
    currency-code="USD"
    _select="selectRevenue"
    a11y-label="Daily revenue"
/>
```

In `selectRevenue`, decode `PointSelection` and use `pointId` to open the chosen day's invoices. Do not look up by the formatted label.

## Budget: share of spend and exact comparison

Use donut for the allocation summary and bar for exact category comparison. Both charts can use the same mapped categories.

```php
public function spendingSegments(): array
{
    return BudgetCategory::forMonth($this->month)->map(fn (BudgetCategory $category) => [
        'id' => (string) $category->id,
        'label' => $category->name,
        'value' => $category->spent_amount,
        'color' => $category->color,
    ])->all();
}
```

```blade
<native:donut-chart
    :segments="$this->spendingSegments()"
    :inner-radius-ratio="0.62"
    value-format="currency"
    currency-code="NIO"
    _select="selectCategory"
    a11y-label="Gasto mensual por categoría"
/>
```

If there are many categories or closely sized values, use a bar chart for the main comparison. Pie and donut are for a small set of meaningful parts.

## Habits: a bounded activity window

The query and heatmap use the same inclusive window. This prevents a chart with days that the query did not intend to show.

```php
public function habitValues(): array
{
    $end = now()->startOfDay();

    return HabitCompletion::between($end->copy()->subDays(89), $end)->daily()->map(fn ($day) => [
        'id' => $day->date->toDateString(),
        'date' => $day->date->toDateString(),
        'label' => "{$day->count} habits completed",
        'value' => $day->count,
    ])->all();
}
```

```blade
<native:contribution-heatmap
    :values="$this->habitValues()"
    :end-date="now()->toDateString()"
    :days="90"
    :week-starts-on="1"
    _select="selectDay"
    a11y-label="Habit completions during the last 90 days"
/>
```

Use the selected day ID to load the day's completion list. A heatmap shows patterns; it is not the best view for comparing two distant dates precisely.

## Service health: bounded SLOs

Represent completion ratios with progress. Keep every metric on the same 0–1 scale.

```php
public array $serviceGoals = [
    ['id' => 'availability', 'label' => 'Availability', 'value' => 0.998],
    ['id' => 'latency', 'label' => 'Latency budget', 'value' => 0.84],
    ['id' => 'errors', 'label' => 'Error budget', 'value' => 0.72],
];
```

```blade
<native:progress-chart
    :metrics="$serviceGoals"
    center-label="SLOs"
    value-format="percent"
    :maximum-fraction-digits="1"
    :legend="['visible' => true]"
    a11y-label="Current service-level objectives"
/>
```

Use radar only when the reader needs to compare profiles across dimensions. Use progress when each item is a bounded completion goal.

## Market prices: preserve OHLC ranges

Candlestick data requires one ordered series and a complete OHLC range for each interval.

```php
public function ohlcSeries(): array
{
    return [[
        'id' => 'btc-usd',
        'name' => 'BTC / USD',
        'points' => MarketCandle::forPair('BTC-USD')->daily()->map(fn (MarketCandle $candle) => [
            'id' => $candle->opened_at->toDateString(),
            'label' => $candle->opened_at->format('M j'),
            'x' => $candle->opened_at->toDateString(),
            'open' => $candle->open,
            'high' => $candle->high,
            'low' => $candle->low,
            'close' => $candle->close,
        ])->all(),
    ]];
}
```

```blade
<native:candlestick-chart
    :series="$this->ohlcSeries()"
    :x-axis="['type' => 'date']"
    value-format="currency"
    currency-code="USD"
    _select="selectCandle"
    a11y-label="Daily BTC to USD price range"
/>
```

Do not enable LTTB sampling for candlesticks. Removing observations can hide price extremes and the package rejects that combination.
