---
title: Troubleshooting
description: Diagnose registration, validation, rendering, payload, and interaction problems.
sidebar: { order: 6 }
---

## The component is missing

Run `php artisan native:plugin:list` and `php artisan native:plugin:validate`, then rebuild the native target. Hot reload cannot install native renderers.

## The chart shows “Chart unavailable”

The renderer rejected an unsupported contract version, corrupt file-backed payload, or invalid snapshot. Check structured application logs without logging the complete payload or user data. Keep `error-label` actionable for users.

## The chart is empty

Confirm the collection is non-empty and follows the exact shape for the chart. Empty data intentionally renders `empty-label`; it is not treated as a failure.

## Selection changes after updating data

Use explicit stable IDs for every series and point. Compatibility-derived point IDs preserve older code but do not guarantee retention after reordering.

## Pan or scrub blocks page scrolling

Do not combine scrub with one-finger viewport pan. Reproduce inside the real scroll container and verify gesture cancellation on both platforms.

## Theme colors do not match

Check precedence: built-in preset, application preset, chart style, then item override. Ensure colors use CSS hex syntax and rebuild after changing native package code.
