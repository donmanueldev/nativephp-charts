---
title: Chart accessibility with VoiceOver and TalkBack
description: "Make NativePHP charts usable with VoiceOver and TalkBack: accessibility labels, error labels, reduced motion, larger text, and meaningful point labels."
sidebar: { order: 4, label: "Accessibility" }
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

Test the labels, focus order, enlarged text, and reduced-motion behavior in the screen where the chart ships. The surrounding native layout determines the final reading and gesture order.
