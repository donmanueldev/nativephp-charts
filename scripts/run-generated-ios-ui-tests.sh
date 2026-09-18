#!/usr/bin/env bash
set -euo pipefail

package_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
shell_root="${1:-}"
test_selector_list="${NATIVEPHP_CHARTS_XCTEST_ONLY:-NativePHPUITests/NativePHPChartsUITests}"

if [[ -z "$shell_root" || ! -d "$shell_root/NativePHP.xcodeproj" || ! -d "$shell_root/NativePHP.xcworkspace" ]]; then
    echo "usage: $0 /path/to/generated/nativephp/ios" >&2
    exit 2
fi

if [[ ! -d "$shell_root/NativePHPUITests" ]]; then
    echo "Generated NativePHPUITests target is missing." >&2
    exit 3
fi

cp "$package_root/ios-harness/NativePHPChartsUITests.swift" "$shell_root/NativePHPUITests/NativePHPChartsUITests.swift"

if ruby -e 'require "xcodeproj"' >/dev/null 2>&1; then
    ruby "$package_root/scripts/configure-generated-ios-ui-tests.rb" "$shell_root/NativePHP.xcodeproj"
elif command -v brew >/dev/null 2>&1 && [[ -x "$(brew --prefix cocoapods)/libexec/bin/pod" ]]; then
    cocoapods_home="$(brew --prefix cocoapods)/libexec"
    cocoapods_ruby="$(head -n 1 "$cocoapods_home/bin/pod")"
    cocoapods_ruby="${cocoapods_ruby#\#!}"
    GEM_HOME="$cocoapods_home" "$cocoapods_ruby" "$package_root/scripts/configure-generated-ios-ui-tests.rb" "$shell_root/NativePHP.xcodeproj"
else
    echo "The xcodeproj Ruby gem bundled with CocoaPods is required." >&2
    exit 4
fi

if [[ -n "${NATIVEPHP_IOS_SIMULATOR_ID:-}" ]]; then
    simulator_id="$NATIVEPHP_IOS_SIMULATOR_ID"
else
    simulator_id="$(xcrun simctl list devices available -j | ruby "$package_root/scripts/select-ios-simulator.rb")"
fi

bundle_id="$({
    xcodebuild \
        -project "$shell_root/NativePHP.xcodeproj" \
        -target NativePHP-simulator \
        -configuration Debug \
        -showBuildSettings 2>/dev/null || true
} | awk -F ' = ' '/^[[:space:]]*PRODUCT_BUNDLE_IDENTIFIER = / { print $2; exit }')"

if [[ -z "$bundle_id" ]]; then
    echo "Unable to resolve the generated simulator bundle identifier." >&2
    exit 5
fi

# NativePHP extracts the embedded Laravel bundle into the application container. XCTest installs do
# not reliably clear that container, so an incremental run can otherwise execute stale PHP/views.
xcrun simctl uninstall "$simulator_id" "$bundle_id" >/dev/null 2>&1 || true

xcodebuild_args=(
    test
    -workspace "$shell_root/NativePHP.xcworkspace"
    -scheme NativePHPChartsUITests
    -configuration Debug
    -destination "platform=iOS Simulator,id=$simulator_id"
    -parallel-testing-enabled NO
    -test-timeouts-enabled YES
    -default-test-execution-time-allowance 90
    -collect-test-diagnostics never
    -quiet
    CODE_SIGNING_ALLOWED=NO
)

IFS=',' read -r -a test_selectors <<< "$test_selector_list"
for test_selector in "${test_selectors[@]}"; do
    if [[ -z "$test_selector" ]]; then
        echo "NATIVEPHP_CHARTS_XCTEST_ONLY contains an empty test selector." >&2
        exit 6
    fi
    xcodebuild_args+=( "-only-testing:$test_selector" )
done

if [[ -n "${NATIVEPHP_CHARTS_XCRESULT_PATH:-}" ]]; then
    xcodebuild_args+=( -resultBundlePath "$NATIVEPHP_CHARTS_XCRESULT_PATH" )
fi

xcodebuild "${xcodebuild_args[@]}"
