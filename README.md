# NativePHP Charts

Native line charts for [NativePHP Mobile](https://nativephp.com), rendered with Swift Charts on iOS and Jetpack Compose on Android.

## Install

```bash
composer require donmanueldev/nativephp-charts
php artisan vendor:publish --tag=nativephp-plugins-provider --no-interaction
php artisan native:plugin:register donmanueldev/nativephp-charts --no-interaction
php artisan native:plugin:validate
```

The Kotlin and Swift renderers are compiled into the app. Rebuild the app after installing or updating this package.

## Blade

```blade
<native:line-chart
    class="w-full h-80"
    :series="[[
        'id' => 'monthly-sales',
        'name' => 'Ventas',
        'color' => '#0F766E',
        'points' => [
            ['label' => 'Ene', 'value' => 42000],
            ['label' => 'Feb', 'value' => 51800],
            ['label' => 'Mar', 'value' => 62400],
        ],
    ]]"
    locale="es-NI"
    value-format="currency"
    currency-code="NIO"
    :maximum-fraction-digits="0"
    a11y-label="Ventas mensuales en córdobas"
    :style="[
        'line' => ['width' => 4, 'interpolation' => 'smooth'],
        'points' => ['size' => 5],
        'grid' => ['color' => '#D1FAE5'],
        'axis' => ['font' => 'accent', 'labelCount' => 4],
    ]"
/>
```

## Fluent API

```php
use Donmanueldev\NativephpCharts\Elements\LineChart;

$chart = LineChart::make()
    ->series($series)
    ->locale('en-US')
    ->valueFormat('percent')
    ->minimumFractionDigits(1)
    ->maximumFractionDigits(1)
    ->style([
        'line' => ['color' => '#7C3AED', 'width' => 3],
        'points' => ['visible' => false],
        'grid' => ['visible' => false],
        'axis' => ['labelColor' => '#475569', 'labelCount' => 4],
    ]);
```

## API

`series` accepts zero or one ordered series. Every series needs a stable `id`, a name, a color, and ordered numeric points.

| Property | Description |
| --- | --- |
| `locale` | Optional BCP-47 locale such as `es-NI` or `en-US`; the device locale is used when omitted. |
| `value-format` | `number`, `currency`, or `percent`. |
| `currency-code` | Required ISO 4217 code when `value-format="currency"`. |
| `minimum-fraction-digits` / `maximum-fraction-digits` | Optional precision from 0 to 8. |
| `style.line` | `color`, `width`, and `interpolation` (`linear` or `smooth`). |
| `style.points` | `visible`, `color`, and `size`. |
| `style.grid` | `visible`, `color`, and `width`. |
| `style.axis` | `visible`, `color`, `labelColor`, `font`, `fontSize`, and `labelCount`. |

Existing `show-grid`, `show-points`, `begin-at-zero`, `animated`, `empty-label`, and `a11y-label` attributes remain supported. A value in `style` takes precedence over `show-grid` or `show-points`.

Colors use CSS hex: `#RGB`, `#RRGGBB`, or `#RRGGBBAA`; `black`, `white`, and `transparent` are also available. An alpha CSS value is normalized to the native ARGB wire value before it reaches either renderer.

Use a NativePHP font filename or a configured font alias in `style.axis.font`. The font must be bundled in `resources/fonts/`; unresolved values fall back to the system font.

## Accessibility

Always provide an `a11y-label` that explains the chart's purpose. Both renderers expose a localized textual value summary to VoiceOver and TalkBack, and the empty state uses `empty-label`.

## Scope

This release renders one line only. Multiple series, legends, selection callbacks, zooming, and tooltips are intentionally not included.

## License

MIT
