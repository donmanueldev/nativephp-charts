---
title: Accessibility
description: Build charts that remain understandable with assistive technology and reduced motion.
sidebar: { order: 4 }
---

Charts expose native accessibility elements to VoiceOver and TalkBack. Provide context with `a11y-label` and an actionable failure with `error-label`.

```blade
<native:bar-chart
    :series="$monthlySpend"
    a11y-label="Monthly spending by category"
    empty-label="No spending recorded"
    error-label="Spending chart unavailable"
/>
```

## Checklist

- Describe the measure and domain, not color or geometry.
- Give each series, point, segment, metric, and day a stable meaningful label.
- Do not make color the only distinction; keep legend names explicit.
- Verify focus after data insertion, reordering, deletion, and emptying.
- Verify enlarged text and screen-reader traversal in a scroll container.
- Respect the operating system's reduced-motion preference even when `animated` is enabled.
- Pair dense or comparison-critical visualizations with a readable text summary or table.

Accessibility metadata in source is only a contract. Record separate VoiceOver and TalkBack evidence from installed native builds.
