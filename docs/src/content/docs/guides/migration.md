---
title: Migration
description: Adopt stable identities, versioned callbacks, themes, and new chart families safely.
sidebar: { order: 7 }
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
