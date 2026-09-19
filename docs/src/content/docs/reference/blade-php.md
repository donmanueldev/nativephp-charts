---
title: Blade and PHP equivalence
description: Map public Blade attributes to the fluent PHP API.
sidebar: { order: 2 }
---

Every attribute below has the same contract in Blade and PHP.

| Blade | PHP | Type | Accepted values | Default |
| --- | --- | --- | --- | --- |
| `theme="dark"` | `->theme('dark')` | string | `light`, `dark`, `system` | `system` |
| `:animated="false"` | `->animated(false)` | bool | `true`, `false` | `true` |
| `locale="es-NI"` | `->locale('es-NI')` | BCP-47 string | valid locale | device locale |
| `value-format="currency"` | `->valueFormat('currency')` | string | `number`, `currency`, `percent` | `number` |
| `currency-code="NIO"` | `->currencyCode('NIO')` | string | ISO 4217 code | unset |
| `:legend="['visible' => true]"` | `->legend(['visible' => true])` | array | `visible`, `position`, `alignment`, `style` | `'auto'`: visible when the chart has more than one legend item |
| `:style="[...]"` | `->style([...])` | array | sections allowed by chart | `[]` |
| `_select="selectPoint"` | `->onSelect('selectPoint')` | callback | public component method | none |
| `a11y-label="Monthly revenue"` | `->a11yLabel('Monthly revenue')` | string | non-empty text | `Chart` |

Use `:` for Blade values that are PHP arrays, booleans, numbers, expressions, or component properties. Omit `:` for literal strings.

```blade
<native:line-chart :series="$series" theme="dark" :animated="false" />
```

The [API reference](/nativephp-charts/reference/api/) lists chart-specific data requirements and style ranges.
