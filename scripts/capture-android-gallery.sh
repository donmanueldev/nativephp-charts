#!/usr/bin/env bash
set -euo pipefail

package_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
serial="${1:-}"
output_directory="${2:-$package_root/docs/public/evidence/android}"
apk="${NATIVEPHP_CHARTS_PROFILE_APK:-$package_root/android-harness/app/build/outputs/apk/profile/app-profile.apk}"
adb_binary="${ADB:-$(command -v adb || true)}"
application_id="com.donmanueldev.plugins.nativephp_charts.harness"
activity="$application_id/.NativePHPChartsGalleryActivity"

if [[ -z "$serial" || -z "$adb_binary" || ! -f "$adb_binary" || ! -f "$apk" ]]; then
    echo "usage: $0 DEVICE_SERIAL [OUTPUT_DIRECTORY]" >&2
    echo "Build assembleProfile first and set ADB or NATIVEPHP_CHARTS_PROFILE_APK when needed." >&2
    exit 2
fi

if [[ "$("$adb_binary" -s "$serial" get-state 2>/dev/null)" != "device" ]]; then
    echo "Android device is not ready: $serial" >&2
    exit 2
fi

mkdir -p "$output_directory"
"$adb_binary" -s "$serial" install -r "$apk" >/dev/null

screen_size="$("$adb_binary" -s "$serial" shell wm size | sed -n 's/.*Physical size: \([0-9]*\)x\([0-9]*\).*/\1 \2/p' | tr -d '\r')"
read -r screen_width screen_height <<< "$screen_size"
if [[ -z "${screen_width:-}" || -z "${screen_height:-}" ]]; then
    echo "Unable to resolve the physical screen size for proportional selection taps." >&2
    exit 2
fi

charts=(line area bar scatter pie donut radar candlestick progress contribution_heatmap)
for chart in "${charts[@]}"; do
    remote="/sdcard/nativephp-charts-${chart}.png"
    local_name="${chart//_/-}.png"
    "$adb_binary" -s "$serial" shell am force-stop "$application_id"
    "$adb_binary" -s "$serial" shell am start -W -n "$activity" --es chart "$chart" >/dev/null
    sleep 1
    "$adb_binary" -s "$serial" shell screencap -p "$remote"
    "$adb_binary" -s "$serial" pull "$remote" "$output_directory/$local_name" >/dev/null
    "$adb_binary" -s "$serial" shell rm "$remote"

    case "$chart" in
        line|area|scatter) tap_x_percent=92; tap_y_percent=18 ;;
        bar) tap_x_percent=86; tap_y_percent=25 ;;
        pie|donut) tap_x_percent=76; tap_y_percent=49 ;;
        radar) tap_x_percent=50; tap_y_percent=40 ;;
        candlestick) tap_x_percent=82; tap_y_percent=31 ;;
        progress) tap_x_percent=83; tap_y_percent=55 ;;
        contribution_heatmap) tap_x_percent=45; tap_y_percent=15 ;;
    esac
    tap_x=$((screen_width * tap_x_percent / 100))
    tap_y=$((screen_height * tap_y_percent / 100))
    "$adb_binary" -s "$serial" shell input tap "$tap_x" "$tap_y"
    sleep 1
    selected_remote="/sdcard/nativephp-charts-${chart}-selected.png"
    selected_local="${local_name%.png}-selected.png"
    "$adb_binary" -s "$serial" shell screencap -p "$selected_remote"
    "$adb_binary" -s "$serial" pull "$selected_remote" "$output_directory/$selected_local" >/dev/null
    "$adb_binary" -s "$serial" shell rm "$selected_remote"
done

"$adb_binary" -s "$serial" shell am force-stop "$application_id"
echo "Captured ten installed-app Android charts and their tapped states in: $output_directory"
