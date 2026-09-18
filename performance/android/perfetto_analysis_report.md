# Android physical performance analysis

## Question

Which native phase dominates first presentation for an unsampled 10,000-point line chart, and can
that work be reduced without changing the public contract, dropping points, or weakening selection?

## Environment

- Device: physical Samsung SM-S918B (`R5CX10TVKZJ`)
- Android: 16, build `S918BXXSAFZG1`
- App: isolated NativePHP Charts harness, `profile`, non-debuggable, debug-signed
- Locale/theme/motion: `en-US`, light, animation disabled
- Dataset: deterministic line series at 100, 1,000, and 10,000 points
- Iterations: five cold process starts per density for latency and memory

This is renderer-harness evidence. It is not generated-shell, manual TalkBack, or release-signing
evidence.

## Raw traces

The reviewed local artifacts were written outside the repository:

- `/private/tmp/nativephp-charts-10000-breakdown.perfetto-trace`
- `/private/tmp/nativephp-charts-10000-optimized.perfetto-trace`
- `/private/tmp/nativephp-charts-final-matrix.perfetto-trace`
- `/private/tmp/nativephp-charts-physical-run-v4/line-first-render-100.perfetto-trace`
- `/private/tmp/nativephp-charts-physical-run-v4/line-first-render-1000.perfetto-trace`
- `/private/tmp/nativephp-charts-physical-run-v4/line-first-render-10000.perfetto-trace`
- `/private/tmp/nativephp-charts-physical-run-v4/android-candidate.json`
- `/private/tmp/nativephp-charts-physical-run-v5/` (rejected `drawPoints` experiment)
- `/private/tmp/nativephp-charts-physical-run-v7/line-first-render-100.perfetto-trace`
- `/private/tmp/nativephp-charts-physical-run-v7/line-first-render-1000.perfetto-trace`
- `/private/tmp/nativephp-charts-physical-run-v7/line-first-render-10000.perfetto-trace`
- `/private/tmp/nativephp-charts-physical-run-v7/android-candidate.json`

They are temporary evidence and must be copied into durable CI/release storage before a baseline can
be approved.

## Findings

### Combined-trace integrity

The original combined matrix trace is degraded: Perfetto reported 6,508,544 overwritten bytes,
210 overwritten chunks, 10 discarded chunks, and eight FrameTimeline parser errors. Only eight
first-render windows are authoritative: two at 100 points, three at 1,000 points, and three at
10,000 points. Missing windows are evidence gaps, not successful frames.

The worst retained 10,000-point frame lasted 98.321 ms on a 120 Hz device. Within that frame,
Compose recomposition consumed 34.464 ms, chart layout consumed 17.366 ms, path-cache construction
consumed 4.200 ms, and RenderThread spent 54.824 ms flushing commands. No retained LMK, OOM,
SurfaceFlinger miss, Binder error in the victim process, or CPU-frequency transition explains the
stall. Thermal and GPU counters were not captured, so they remain unexcluded gaps.

The repository recorder now creates one trace per density and refuses to assemble a candidate unless
all five correlated `NPC.first-render.*` sections and FrameTimeline rows survive. A validation run
correctly rejected a dozing, keyguard-locked Samsung before installation or artifact creation.

Before optimization, the five 10,000-point runs spent 169.881-177.949 ms in
`NPC.layout.line.10000`. The internal breakdown was stable:

- data geometry: 54.556-55.846 ms;
- numeric label candidates: 28.043-30.501 ms;
- value-domain collection: 22.365-24.530 ms;
- unused category list plus index: 26.640-28.060 ms;
- hit index: 10.782-12.006 ms;
- path cache: 33.147-34.365 ms;
- decode: 111.325-119.480 ms on `DefaultDispatch`, not the main thread.

Numeric line, area, scatter, and candlestick layouts do not need categorical lookup. The renderer now
skips that work, reuses numeric point conversions, preserves already sorted input in both labels and
hit testing, avoids `zipWithNext` allocations during viewport culling, computes value domains without
a temporary value list, and avoids copying every datum when no error range exists.

After optimization, the outer `NPC.layout.line.10000` section measured 140.076-148.481 ms across
initial composition work. Category work fell below 0.1 ms and hit-index construction fell to
7.397-7.815 ms. Inside the worst retained FrameTimeline window, layout accounted for 17.366 ms;
Compose recomposition plus HWUI/RenderThread work is the stronger end-to-end jank candidate. Moving
more geometry across threads or changing path submission needs a separate design because canvas size,
text measurement, viewport cancellation, visual fidelity, and stable selection must remain one atomic
snapshot.

The first corrected per-density capture retained all five runs at every density. Marker and async-trace
latencies agreed within the two-millisecond integrity threshold. It produced these medians and peaks:

| Points | Median first render | Peak TOTAL RSS | Max frame | Slow frames | Payload | Callbacks |
| ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| 100 | 64.322 ms | 209.621 MiB | 37.100 ms | 3 | 6,020 B | 0 |
| 1,000 | 76.408 ms | 218.055 MiB | 31.283 ms | 3 | 62,720 B | 0 |
| 10,000 | 143.180 ms | 261.797 MiB | 98.760 ms | 3 | 656,720 B | 0 |

`TOTAL RSS` came from `dumpsys meminfo` after first presentation. Perfetto's `mem.rss` counter was
not substituted because it can omit graphics/EGL memory.

The three per-density traces reported zero overwritten bytes and retained the full marker/frame
matrix. They still reported FrameTimeline parser notices and discarded chunks, so raw traces remain
part of review evidence. The candidate is not approved because all three startup frames were janky at
every density and it covers only line presentation. The 10,000-point causal investigation is in
`ten-thousand-point-jank-report.md`.

### Render-path experiments

Replacing the solid linear `Path` with Compose `drawPoints(PointMode.Polygon)` was rejected after a
complete v5 run: at 10,000 points latency increased to 146.851 ms, peak RSS to 304.793 MiB, and the
maximum frame to 113.628 ms. The implementation was removed; `/private/tmp/nativephp-charts-physical-run-v5/`
is retained only as negative evidence.

The accepted implementation coalesces only solid, linear render geometry within each physical x
column, retaining the first, minimum, maximum, and last datum in source order. The immutable layout,
hit index, stable IDs, callbacks, and accessibility traversal still retain all 10,000 points. Curved,
stepped, dashed, animated, area, and fill-between paths remain unchanged. Combined with the allocation
reductions above, the complete v7 matrix measured:

| Points | Median first render | Peak TOTAL RSS | Max frame | Slow frames | Payload | Callbacks |
| ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| 100 | 67.543 ms | 209.520 MiB | 34.900 ms | 3 | 6,020 B | 0 |
| 1,000 | 76.883 ms | 200.973 MiB | 33.078 ms | 3 | 62,720 B | 0 |
| 10,000 | 143.534 ms | 228.156 MiB | 69.354 ms | 3 | 656,720 B | 0 |

Against v4, the 10,000-point median changed by +0.25%, peak RSS fell 12.85%, and the maximum frame
fell 29.78%. This remains an unapproved candidate: every startup process still contained three janky
frames, the matrix covers only one chart/scenario, and the three traces retain parser notices,
discarded chunks, and one ftrace setup notice despite zero overwrite.

## Reproducible queries

```sql
INCLUDE PERFETTO MODULE slices.with_context;

SELECT pid, tid, thread_name, ROUND(dur / 1e6, 3) AS duration_ms, name
FROM thread_or_process_slice
WHERE name GLOB 'NPC.*'
ORDER BY pid, dur DESC;
```

```sql
SELECT process.pid,
       COUNT(*) AS total_frames,
       SUM(CASE WHEN actual_frame_timeline_slice.jank_type != 'None' THEN 1 ELSE 0 END) AS slow_frames,
       ROUND(AVG(actual_frame_timeline_slice.dur) / 1e6, 3) AS average_ms,
       ROUND(MAX(actual_frame_timeline_slice.dur) / 1e6, 3) AS maximum_ms
FROM actual_frame_timeline_slice
JOIN process USING (upid)
GROUP BY process.pid
ORDER BY process.pid;
```

## Approval status and next measurements

This run is **not approved as a regression baseline**. Approval still requires:

1. eliminate or explicitly accept the all-janky first-render behavior before choosing a reference;
2. equivalent matrices for every chart family and completed gesture scenario;
3. durable storage of raw profiler artifacts beside the JSON evidence;
4. manual TalkBack checks and a generated NativePHP release-shell run;
5. a separately reviewed iOS physical-device matrix.
