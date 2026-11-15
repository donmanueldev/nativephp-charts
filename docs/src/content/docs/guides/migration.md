---
title: Migration and versioning
description: "Upgrade NativePHP Charts within 1.x: stable IDs, versioned callback payloads, themes, new chart families, and what the SemVer contract covers."
sidebar: { order: 7, label: "Migration" }
---

## Existing 1.x charts

Existing chart tags remain valid. Migrate interactive data first:

1. Add stable `series.id` and `point.id` values.
2. Decode callbacks with `PointSelection::fromJson()` instead of parsing ad hoc JSON.
3. Add `empty-label`, `error-label`, and a task-oriented `a11y-label`.
4. Choose `theme="system"` and a registered preset; retain existing semantic style overrides.
5. Rebuild and validate both native targets.

Compatibility-generated point IDs remain available in 1.x, but selection retention after reordering requires explicit IDs.

## New chart families

Progress and contribution heatmap use the same version 1 selection envelope as existing charts. Consumers can extend an exhaustive `chart_type` match with `progress` and `contribution_heatmap` without changing callback transport.

Publish native plugin updates through the platform compilation and native runtime validation workflow.

## Stability and versioning

NativePHP Charts follows [Semantic Versioning](https://semver.org/). Minor and patch releases in the 1.x line add features and fixes without breaking code written against an earlier 1.x release. Breaking changes require a new major version.

The 1.x contract covers:

- The ten Blade tags, such as `<native:line-chart>`, and the attributes documented in the [API reference](/nativephp-charts/reference/api/).
- The fluent element API: the classes in `Donmanueldev\NativephpCharts\Elements` and their public methods, such as `LineChart::make()->series(...)->onSelect(...)`.
- Callback payloads with `version: 1`, decoded by `PointSelection::fromJson()` and `ViewportChange::fromJson()`. Both reject any other version.
- The `presets` structure in `config/nativephp-charts.php`.

The JSON that PHP sends to the Swift and Kotlin renderers is an internal detail and is not part of this contract.

After every package update, including patch releases, rebuild the iOS and Android apps. The renderers are Swift and Kotlin sources that compile into the native shell, so `composer update` alone does not change what runs on the device:

```bash
composer update donmanueldev/nativephp-charts
php artisan native:plugin:validate
```

Then rebuild each native target you ship before testing.
