## donmanueldev/nativephp-charts

Native line, area, bar, scatter, pie, and donut charts for NativePHP Mobile, rendered with Swift Charts on iOS and Jetpack Compose on Android.

### Installation

```bash
composer require donmanueldev/nativephp-charts
php artisan vendor:publish --tag=nativephp-plugins-provider --no-interaction
php artisan native:plugin:register donmanueldev/nativephp-charts --no-interaction
php artisan native:plugin:validate
```

Rebuild the selected native target after installing or updating the package because Swift and Kotlin renderers compile into the app.

### Blade usage

Use the native element inside a `NativeComponent` view. The chart is a leaf element, so give it an explicit height and an accessible description.

@verbatim
<code-snippet name="Interactive multi-series area chart" lang="blade">
<native:area-chart
    class="w-full h-80"
    :series="[
        [
            'id' => 'online',
            'name' => 'En línea',
            'color' => '#0F766E',
            'points' => [
                ['id' => 'online-jan', 'x' => '2026-01-01', 'label' => 'Ene', 'value' => 42000],
                ['id' => 'online-feb', 'x' => '2026-02-01', 'label' => 'Feb', 'value' => 51800],
            ],
        ],
        [
            'id' => 'store',
            'name' => 'Tienda',
            'color' => '#7C3AED',
            'points' => [
                ['id' => 'store-jan', 'x' => '2026-01-01', 'label' => 'Ene', 'value' => 31000],
                ['id' => 'store-feb', 'x' => '2026-02-01', 'label' => 'Feb', 'value' => 38600],
            ],
        ],
    ]"
    area-mode="stacked"
    locale="es-NI"
    :x-axis="['type' => 'date', 'dateFormat' => 'medium', 'timezone' => 'America/Managua']"
    :y-axis="['valueFormat' => 'currency', 'currencyCode' => 'NIO', 'maximumFractionDigits' => 0]"
    :legend="['visible' => true, 'position' => 'bottom']"
    _select="pointSelected"
    a11y-label="Ventas mensuales en córdobas"
    :style="[
        'line' => ['width' => 3, 'interpolation' => 'smooth'],
        'area' => ['opacity' => 0.28],
        'points' => ['size' => 5],
        'axis' => ['font' => 'accent', 'labelCount' => 5],
    ]"
/>
</code-snippet>
@endverbatim

### Contract

- `<native:line-chart>`, `<native:area-chart>`, `<native:bar-chart>`, and `<native:scatter-chart>` share the versioned Cartesian contract.
- `<native:pie-chart>` and `<native:donut-chart>` use ordered `segments` with unique `id`, `label`, non-negative finite `value`, and `color`. Donut accepts `inner-radius-ratio` from `0.2` through `0.85`.
- `series` accepts multiple ordered series. Each series needs a unique `id`, `name`, `color`, and ordered numeric `points`.
- Give points stable `id` values when using selection. A point may provide `x` as a category string, finite number, `YYYY-MM-DD` date, or ISO-8601 datetime according to `x-axis.type`.
- Area charts support `area-mode="overlay|stacked"`; bar charts use grouped multi-series bars; scatter charts render independent points without connecting lines.
- `legend` supports automatic visibility plus `top`, `bottom`, `leading`, and `trailing` placement.
- Bind `_select="method"` or call `->onSelect('method')`; parse the callback JSON with `Donmanueldev\NativephpCharts\PointSelection::fromJson()`.
- `locale` accepts BCP-47 tags such as `es-NI` and `en-US`; omit it to use the device locale.
- `x-axis.type` accepts `category`, `number`, `date`, or `datetime`. Date axes also accept `dateFormat` and an IANA `timezone`; use the locale-aware `time` preset for compact datetime labels.
- `y-axis.valueFormat` accepts `number`, `currency`, or `percent`. Currency requires a three-letter `currencyCode`.
- `style` is platform-neutral: Cartesian charts use `points`, `grid`, and `axis`; line/area add `line`, area adds `area` with `opacity` and `gradient`, bar adds `bar` with `radius` and optional `width`, and radial charts use `segment` with `gap`, `cornerRadius`, and `opacity`.
- `begin-at-zero` controls line and bar y domains. Area charts retain zero because it is their fill baseline.
- On bar charts, legacy `show-points` controls axis visibility only when neither the structured axis contract nor `style.axis.visible` provides an explicit value.
- Colors accept `#RGB`, `#RRGGBB`, CSS-alpha `#RRGGBBAA`, `black`, `white`, and `transparent`.
- Axis and legend fonts accept a bundled NativePHP font token or configured alias. Unresolved fonts fall back to the system font.
- Legacy scalar formatting and visibility props remain supported for v0.2 consumers, but new code should use `x-axis`, `y-axis`, `legend`, and `style`.

Keep domain labels and `a11y-label` in the application's language. The renderers localize numeric values and their VoiceOver/TalkBack summaries using `locale` and `value-format`.
