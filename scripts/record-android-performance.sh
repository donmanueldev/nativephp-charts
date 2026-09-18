#!/usr/bin/env bash
set -euo pipefail

package_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
serial="${1:-}"
artifact_directory="${2:-}"
iterations="${3:-5}"

if [[ -z "$serial" || -z "$artifact_directory" || ! "$iterations" =~ ^[0-9]+$ || "$iterations" -lt 3 ]]; then
    echo "usage: $0 DEVICE_SERIAL ARTIFACT_DIRECTORY [ITERATIONS>=3]" >&2
    exit 2
fi

adb_binary="${ADB:-$(command -v adb || true)}"
recorder="${PERFETTO_RECORD_ANDROID_TRACE:-$(command -v record_android_trace || true)}"
trace_processor="${PERFETTO_TRACE_PROCESSOR:-$(command -v trace_processor || true)}"
profile_apk="${NATIVEPHP_CHARTS_PROFILE_APK:-$package_root/android-harness/app/build/outputs/apk/profile/app-profile.apk}"
trace_config="$package_root/performance/android/physical-profile.pftxt"
application_id="com.donmanueldev.plugins.nativephp_charts.harness"
activity="$application_id/.NativePHPChartsPerformanceActivity"

for dependency in "$adb_binary" "$recorder" "$trace_processor" "$profile_apk" "$trace_config"; do
    if [[ -z "$dependency" || ! -f "$dependency" ]]; then
        echo "Required dependency is missing: ${dependency:-unset}" >&2
        echo "Set ADB, PERFETTO_RECORD_ANDROID_TRACE, PERFETTO_TRACE_PROCESSOR, or NATIVEPHP_CHARTS_PROFILE_APK as needed." >&2
        exit 2
    fi
done

recorder_pid=""
cleanup() {
    "$adb_binary" -s "$serial" shell am force-stop "$application_id" >/dev/null 2>&1 || true
    if [[ -n "$recorder_pid" ]] && kill -0 "$recorder_pid" >/dev/null 2>&1; then
        kill "$recorder_pid" >/dev/null 2>&1 || true
        wait "$recorder_pid" >/dev/null 2>&1 || true
    fi
}
trap cleanup EXIT

if [[ "$("$adb_binary" -s "$serial" get-state 2>/dev/null)" != "device" ]]; then
    echo "Android device is not ready: $serial" >&2
    exit 2
fi

power_state="$("$adb_binary" -s "$serial" shell dumpsys power)"
window_state="$("$adb_binary" -s "$serial" shell dumpsys window)"
if ! grep -Fq 'mWakefulness=Awake' <<< "$power_state" ||
    grep -Eq 'isKeyguardShowing=true|mDreamingLockscreen=true' <<< "$window_state"; then
    echo "Android device must be awake and unlocked before physical profiling: $serial" >&2
    echo "No app was installed and no artifact directory was created." >&2
    exit 2
fi

if [[ -e "$artifact_directory" ]]; then
    echo "Artifact directory already exists; refusing to overwrite: $artifact_directory" >&2
    exit 2
fi
mkdir -p "$artifact_directory"

"$adb_binary" -s "$serial" install -r "$profile_apk"
printf 'points,iteration,total_rss_kb\n' > "$artifact_directory/memory.csv"

recorded_at="$(date -u '+%Y-%m-%dT%H:%M:%SZ')"
recording_token="$(date -u '+%Y%m%dT%H%M%SZ')"
device_model="$("$adb_binary" -s "$serial" shell getprop ro.product.model | tr -d '\r')"
android_release="$("$adb_binary" -s "$serial" shell getprop ro.build.version.release | tr -d '\r')"
android_build="$("$adb_binary" -s "$serial" shell getprop ro.build.version.incremental | tr -d '\r')"
revision="$(git -C "$package_root" rev-parse --short HEAD)"
if [[ -n "$(git -C "$package_root" status --porcelain)" ]]; then
    revision="${revision}+dirty"
fi

for points in 100 1000 10000; do
    run_prefix="npc-${recording_token}-${points}"
    trace_path="$artifact_directory/line-first-render-${points}.perfetto-trace"
    recorder_log="$artifact_directory/recorder-${points}.log"

    env PATH="$(dirname "$adb_binary"):/usr/bin:/bin:/usr/sbin:/sbin" \
        "$recorder" --serial "$serial" -c "$trace_config" -n -o "$trace_path" \
        > "$recorder_log" 2>&1 &
    recorder_pid="$!"
    sleep 2
    if ! kill -0 "$recorder_pid" >/dev/null 2>&1; then
        wait "$recorder_pid" || true
        echo "Perfetto recorder stopped before the ${points}-point scenario." >&2
        exit 2
    fi

    for ((iteration = 1; iteration <= iterations; iteration++)); do
        run_id="${run_prefix}-${iteration}"
        "$adb_binary" -s "$serial" shell am force-stop "$application_id"
        "$adb_binary" -s "$serial" shell am start -W -n "$activity" \
            --ei point_count "$points" --es run_id "$run_id" >/dev/null
        sleep 1
        total_rss_kb="$(
            "$adb_binary" -s "$serial" shell dumpsys meminfo "$application_id" |
                sed -n 's/.*TOTAL RSS:[[:space:]]*\([0-9]*\).*/\1/p' |
                head -1 |
                tr -d '\r'
        )"
        if [[ -z "$total_rss_kb" ]]; then
            echo "Unable to read TOTAL RSS for $run_id." >&2
            exit 2
        fi
        printf '%s,%s,%s\n' "$points" "$iteration" "$total_rss_kb" >> "$artifact_directory/memory.csv"
    done

    if ! wait "$recorder_pid"; then
        recorder_pid=""
        echo "Perfetto recorder failed for ${points} points; inspect $recorder_log." >&2
        exit 2
    fi
    recorder_pid=""
    if [[ ! -s "$trace_path" ]]; then
        echo "Perfetto did not produce a trace for ${points} points." >&2
        exit 2
    fi

    if ! "$adb_binary" -s "$serial" logcat -d -v raw -s NativePHPChartsPerf:W '*:S' |
        grep -F "\"run_id\":\"${run_prefix}-" > "$artifact_directory/markers-${points}.jsonl"; then
        echo "No completed first-render markers were retained for ${points} points." >&2
        echo "Confirm that the device stayed awake and unlocked; raw trace: $trace_path" >&2
        exit 2
    fi

    query="INCLUDE PERFETTO MODULE slices.with_context;
WITH runs AS (
  SELECT pid, name, ROUND(dur / 1e6, 3) AS trace_latency_ms
  FROM thread_or_process_slice
  WHERE name GLOB 'NPC.first-render.${points}.${run_prefix}-*'
), frames AS (
  SELECT process.pid,
         COUNT(*) AS total_frames,
         SUM(CASE WHEN actual_frame_timeline_slice.jank_type != 'None' THEN 1 ELSE 0 END) AS slow_frames,
         ROUND(MAX(actual_frame_timeline_slice.dur) / 1e6, 3) AS maximum_frame_ms
  FROM actual_frame_timeline_slice
  JOIN process USING (upid)
  GROUP BY process.pid
)
SELECT SUBSTR(runs.name, LENGTH('NPC.first-render.${points}.') + 1) AS run_id,
       runs.pid,
       runs.trace_latency_ms,
       COALESCE(frames.total_frames, 0) AS total_frames,
       COALESCE(frames.slow_frames, 0) AS slow_frames,
       COALESCE(frames.maximum_frame_ms, 0) AS maximum_frame_ms
FROM runs
LEFT JOIN frames USING (pid)
ORDER BY run_id;"
    if ! "$trace_processor" query "$trace_path" "$query" > "$artifact_directory/frames-${points}.csv"; then
        echo "Perfetto SQL failed for ${points} points; raw trace retained at $trace_path." >&2
        exit 2
    fi
done

output_json="$artifact_directory/android-candidate.json"
php "$package_root/scripts/assemble-android-performance-run.php" \
    "$artifact_directory" \
    "$device_model" \
    "Android ${android_release} (${android_build})" \
    "$iterations" \
    "$revision" \
    "$recorded_at" \
    "$output_json"

echo "Physical Android artifacts retained in: $artifact_directory"
echo "Candidate remains approved=false until profiler review."
