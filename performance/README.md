# Physical performance evidence

Performance acceptance uses measurements from installed **release/profile builds on physical
hardware**. PHP timings, native unit tests, compilation, simulators, and emulators are useful gates,
but they are not accepted as physical baselines.

Create one JSON document per device/configuration using `baseline.schema.json`. A comparable run
must keep these fields identical: platform, device model, OS version, build type, locale, theme, and
reduced-motion state. Record at least three cold iterations (five is the project default) for every
scenario with the same deterministic dataset and interaction script at 100, 1,000, and 10,000
points. Every chart/scenario group must contain all three density tiers; an incomplete matrix is
rejected instead of being silently compared.

Required measurements:

- `latency_ms`: median installed-app presentation or completed-interaction latency;
- `memory_mb`: highest peak resident memory observed across the iterations;
- `slow_frames`: highest platform-reported slow/hitch-frame count across the iterations;
- `payload_bytes`: exact UTF-8 bytes delivered to the native renderer;
- `callbacks`: highest callback count observed during the iterations;
- `expected_callbacks`: zero for presentation and preview frames, one for a completed selection or
  viewport gesture.

Only set `approved` to `true` after the run, device, build, and raw profiler artifacts have been
reviewed. Then compare a candidate run:

```bash
php scripts/compare-performance-baselines.php \
  artifacts/performance/android-approved.json \
  artifacts/performance/android-candidate.json
```

The command exits `1` when latency regresses more than 10%, memory regresses more than 15%, or a
scenario emits the wrong callback count. It exits `2` for invalid, unapproved, incomplete, duplicate,
or non-comparable evidence. Slow-frame and payload deltas are always reported for review; no arbitrary
budget is inferred before a physical baseline is approved.

Capture Android frame and memory evidence with Android Studio/Perfetto against the generated release
shell. Capture iOS hitches, memory, and intervals with Instruments against the generated Release
scheme. Retain the raw `.perfetto-trace` or `.trace` artifact beside the JSON result; the JSON alone is
not proof that a physical run occurred.

The generated iOS shell includes `/performance`, a deterministic line-chart screen with controls for
100, 1,000, and 10,000 points. Its displayed payload byte count is calculated from the exact
normalized UTF-8 wire payload; the PHP gate also verifies the transition from inline transport at
100 points to content-addressed `file-v1` transport for the larger tiers. XCTest exercises the full
density matrix with zero presentation callbacks. Use that screen to reproduce an Instruments capture,
but do not promote its simulator XCTest duration to `latency_ms` or its simulator memory to a physical
candidate.

The repository includes `android/physical-profile.pftxt` for repeatable Android captures. The current
Samsung investigation, queries, measured limits, and explicit non-approval decision are recorded in
`android/perfetto_analysis_report.md`. The selected 10,000-point dependency chain is consolidated in
`android/ten-thousand-point-jank-report.md`.

To create a complete unapproved Android candidate, first build the profile harness, unlock the
physical device, and provide the official Perfetto recorder and trace processor:

```bash
gradle --project-dir android-harness testDebugUnitTest assembleProfile

ADB="$ANDROID_HOME/platform-tools/adb" \
PERFETTO_RECORD_ANDROID_TRACE=/path/to/record_android_trace \
PERFETTO_TRACE_PROCESSOR=/path/to/trace_processor \
scripts/record-android-performance.sh \
  DEVICE_SERIAL \
  /durable/artifacts/nativephp-charts-android \
  5
```

The recorder does not clear Logcat. It uses unique run IDs, records one trace per density, requires
all iterations to have completed first-render markers and FrameTimeline rows, captures `TOTAL RSS`,
and writes `android-candidate.json` with `approved: false`. The marker is emitted by an internal draw
observer only after a validated renderer snapshot has completed its first Canvas draw; loading and
unavailable placeholders cannot satisfy it. The recorder refuses to start while the device is dozing
or locked and never overwrites an existing artifact directory.
