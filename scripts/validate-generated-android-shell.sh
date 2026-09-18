#!/usr/bin/env bash
set -euo pipefail

package_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
shell_root="${1:-}"

if [[ -z "$shell_root" || ! -f "$shell_root/settings.gradle.kts" || ! -x "$shell_root/gradlew" ]]; then
    echo "usage: $0 /path/to/generated/nativephp/android" >&2
    exit 2
fi

generated_root="$shell_root/app/src/nativephp/kotlin"
if [[ ! -d "$generated_root" ]]; then
    echo "Generated plugin sources are missing." >&2
    echo "Run 'php artisan native:run android --build=bundle' in the host app; native:install only creates the base shell." >&2
    exit 3
fi

generated_package_root="$generated_root/com/donmanueldev/plugins/nativephp_charts/ui"
registration_file="$generated_root/com/nativephp/mobile/bridge/plugins/PluginRendererRegistration.kt"

if [[ ! -d "$generated_package_root" || ! -f "$registration_file" ]]; then
    echo "NativePHP Charts was not compiled into this shell." >&2
    echo "Run 'php artisan native:run android --build=bundle' in the host app before validating it." >&2
    exit 3
fi

missing=0
while IFS= read -r source; do
    name="$(basename "$source")"
    if [[ ! -f "$generated_package_root/$name" ]]; then
        echo "Missing generated renderer source: $name" >&2
        missing=1
    fi
done < <(find "$package_root/resources/android/src" -type f -name '*.kt' | sort)

while IFS= read -r generated_source; do
    name="$(basename "$generated_source")"
    if ! find "$package_root/resources/android/src" -type f -name "$name" -print -quit | grep -q .; then
        echo "Unexpected stale generated renderer source: $name" >&2
        missing=1
    fi
done < <(find "$generated_package_root" -type f -name 'NativePHPCharts*.kt' | sort)

while IFS= read -r component_type; do
    if ! grep -Fq "NativeRendererRegistry.register(\"$component_type\"" "$registration_file"; then
        echo "Missing generated renderer registration: $component_type" >&2
        missing=1
    fi
done < <(php -r '
    $manifest = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
    foreach ($manifest["components"] ?? [] as $component) {
        echo $component["type"], PHP_EOL;
    }
' "$package_root/nativephp.json")

if [[ "$missing" -ne 0 ]]; then
    echo "Regenerate the plugin with 'php artisan native:run android --build=bundle' before compiling the shell." >&2
    exit 4
fi

"$shell_root/gradlew" -p "$shell_root" :app:compileDebugKotlin --stacktrace
