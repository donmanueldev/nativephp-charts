# 10,000-point Android first-render investigation

Evidence tags used below:

- `[SQL]`: directly supported by Perfetto SQL from the retained trace.
- `[INFERRED]`: deduction from SQL evidence plus the renderer implementation.
- `[GAP]`: evidence the current capture does not contain.

## Scope

- [SQL] Victim: `com.donmanueldev.plugins.nativephp_charts.harness`, PID 18702, UPID 396.
- [SQL] Device: Samsung SM-S918B at 120 Hz.
- [SQL] Symptom window: `170324319627261..170324417948042` ns.
- [SQL] Actual frame duration: 98.321 ms against an 8.333 ms deadline.
- [SQL] FrameTimeline classified it as `App Deadline Missed, Buffer Stuffing`, full severity,
  `Late Present`.
- [GAP] The source trace lost other matrix windows, but the selected frame's dependency chain is
  internally coherent.

## Root cause

- [SQL] The main-thread `Choreographer#doFrame` occupied 35.988 ms wall time and 35.658 ms CPU.
- [SQL] The main thread was Running for 35.832 ms and Runnable for only 0.207 ms; scheduler delay and
  I/O blocking do not explain this bucket.
- [SQL] `Recomposer:recompose` consumed 34.464 ms wall time and 34.231 ms CPU.
- [SQL] Chart layout consumed 17.366 ms, including 5.299 ms for data geometry, 3.028 ms for label
  candidates, 2.452 ms for value-domain collection, and 1.330 ms for numeric X conversion.
- [SQL] Path-cache construction consumed another 4.200 ms.
- [SQL] Main woke RenderThread at `170324352845699`; RenderThread then spent 62.317 ms in
  `DrawFrames`.
- [SQL] During `flush commands`, RenderThread slept for 51.463 ms while in-process `hwuiTask1`
  executed for 51.156 ms and was Runnable for only 0.308 ms.
- [SQL] `hwuiTask1` finished with one uninterrupted 35.168 ms run on CPU7 and woke RenderThread at
  `170324408073146`.
- [INFERRED] The full 9,999-segment linear path submitted by `drawNativePHPChartsLines()` is the
  likely source of the CPU-side HWUI tessellation/raster work. The trace has no worker call stacks,
  so the exact internal Skia function cannot be named.
- [SQL] Main `doFrame` plus RenderThread `DrawFrames`, after subtracting their approximately 0.100 ms
  overlap, explain more than 99.8% of the missed frame.

The root cause is therefore **application-owned CPU work on the critical first-render path**:
synchronous Compose layout/path preparation followed by full-resolution CPU-side HWUI rendering.
It is not a retained LMK, OOM, Binder, SurfaceFlinger, I/O, or scheduler-stall event. `[SQL][INFERRED]`

## Dependency chain

```text
Main _charts.harness, UTID 2111
  Choreographer#doFrame                 35.988 ms
    Recomposer:recompose                34.464 ms
      NPC.layout.line.10000             17.366 ms
      NPC.path-cache.line.10000          4.200 ms
      remaining Compose work           ~12.9 ms
        |
        +-- wakes RenderThread at 170324352845699
              RenderThread, UTID 2126
                DrawFrames              62.317 ms
                  flush commands        54.824 ms
                    |
                    +-- waits 51.463 ms on hwuiTask1
                          hwuiTask1, UTID 2131
                            CPU work     51.156 ms
                            final CPU7   35.168 ms
                    |
                    +-- wakes RenderThread at 170324408073146
                          texture upload  2.017 ms
                          swap            6.502 ms wall
```

## Ranked partial suspects

1. Full-resolution path rendering on `hwuiTask1`: 51.156 ms directly on the critical path.
   `[SQL][INFERRED]`
2. Main-thread layout: 17.151 ms CPU, independently more than twice the 120 Hz frame budget. `[SQL]`
3. Unpartitioned Compose recomposition: approximately 11.279 ms CPU after subtracting instrumented
   layout and path-cache work. `[SQL][GAP]`
4. RenderThread work after the worker completes, including 5.417 ms CPU in swap. `[SQL]`
5. Main-thread path-cache construction: 4.183 ms CPU. `[SQL]`

## Platform context and gaps

- [SQL] CPUs 3-6 remained at 2.8032 GHz and CPU7 at 3.36 GHz during the window.
- [SQL] Main, RenderThread, and `hwuiTask1` had only 0.207, 1.417, and 0.329 ms of Runnable time.
- [SQL] No process GC slice coincided with the frame.
- [SQL] Anonymous RSS increased by approximately 7.97 MiB, but no memory-pressure stall coincided
  with the frame.
- [GAP] Thermal counters, GPU tracks, and HWUI worker call-stack samples were not captured.
- [GAP] The analyzed trace contains no healthy 10,000-point first-render baseline.

## Independent reproduction

- [SQL] A later complete five-run trace retained all 15 density windows without buffer overwrite.
- [SQL] Its 10,000-point first-render frames peaked at 98.760 ms and every retained process reported
  three janky startup frames.
- [SQL] Median renderer presentation latency was 143.180 ms, peak `TOTAL RSS` was 261.797 MiB,
  payload size was 656,720 bytes, and callback count was zero.
- [INFERRED] The independent run reproduces the severity of the selected 98.321 ms frame; it does not
  by itself add missing HWUI call-stack evidence.

## Follow-up implementation and measurement

- [SQL] A `drawPoints(PointMode.Polygon)` experiment regressed the 10,000-point case to 146.851 ms
  median latency, 304.793 MiB peak RSS, and a 113.628 ms maximum frame; it was removed.
- [INFERRED] The replacement reduces only sub-pixel solid-linear render geometry. It retains each
  physical column's endpoints and vertical extrema in source order while the full 10,000-point
  immutable snapshot remains authoritative for hit-testing, IDs, callbacks, and accessibility.
- [SQL] The complete v7 run measured 143.534 ms median latency, 228.156 MiB peak RSS, and a 69.354 ms
  maximum frame at 10,000 points.
- [SQL] Relative to the complete pre-change v4 run, that is +0.25% median latency, -12.85% peak RSS,
  and -29.78% maximum-frame duration.
- [SQL] All five v7 processes still reported three janky startup frames; the optimization reduces the
  dominant frame but does not establish a healthy baseline.

## Decision

- [INFERRED] Do not approve the current candidate as the regression baseline: accepting an all-janky
  startup would preserve a known defect as the reference.
- [INFERRED] The harness now uses an internal draw observer and emits its first-render marker only
  after a validated chart snapshot completes a Canvas draw; loading and unavailable placeholders
  cannot satisfy it. A new physical capture is still required because historical traces predate this
  measurement correction.
- [INFERRED] The next renderer investigation should move additional immutable, size-independent
  preparation off the main thread without splitting the atomic snapshot used by rendering,
  hit-testing, callbacks, and accessibility.
- [GAP] Manual TalkBack, generated NativePHP release-shell, other chart families/gestures, and iOS
  physical evidence remain separate acceptance gates.
