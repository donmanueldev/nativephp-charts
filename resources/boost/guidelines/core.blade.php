## donmanueldev/nativephp-charts

Native line charts for NativePHP Mobile, rendered with Swift Charts on iOS and Jetpack Compose on Android.

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
<code-snippet name="Localized currency chart" lang="blade">
<native:line-chart
    class="w-full h-80"
    :series="[[
        'id' => 'monthly-sales',
        'name' => 'Ventas',
        'color' => '#0F766E',
        'points' => [
            ['label' => 'Ene', 'value' => 42000],
            ['label' => 'Feb', 'value' => 51800],
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
        'axis' => ['font' => 'accent', 'labelCount' => 4],
    ]"
/>
</code-snippet>
@endverbatim

### Contract

- `series` accepts zero or one ordered series. Each series needs `id`, `name`, `color`, and ordered numeric `points`.
- `locale` accepts BCP-47 tags such as `es-NI` and `en-US`; omit it to use the device locale.
- `value-format` accepts `number`, `currency`, or `percent`. Currency requires a three-letter `currency-code`.
- `style` has `line`, `points`, `grid`, and `axis` sections. It is platform-neutral and takes precedence over `show-grid` and `show-points`.
- Colors accept `#RGB`, `#RRGGBB`, CSS-alpha `#RRGGBBAA`, `black`, `white`, and `transparent`.
- `style.axis.font` accepts a bundled NativePHP font token or configured alias. Unresolved fonts fall back to the system font.

Keep domain labels and `a11y-label` in the application's language. The renderers localize numeric values and their VoiceOver/TalkBack summaries using `locale` and `value-format`.
