#!/usr/bin/env bash
set -euo pipefail

result_bundle="${1:-}"
output_directory="${2:-}"

if [[ -z "$result_bundle" || ! -d "$result_bundle" || -z "$output_directory" ]]; then
    echo "usage: $0 /path/to/result.xcresult /new/output/directory" >&2
    exit 2
fi

if [[ -e "$output_directory" ]]; then
    echo "Refusing to replace existing iOS evidence directory: $output_directory" >&2
    exit 3
fi

xcrun xcresulttool export attachments \
    --path "$result_bundle" \
    --output-path "$output_directory"

ruby -rjson -rfileutils -e '
  output = ARGV.fetch(0)
  manifest_path = File.join(output, "manifest.json")
  manifest = JSON.parse(File.read(manifest_path))
  expected = %w[
    nativephp-charts-ios-area
    nativephp-charts-ios-area-selected
    nativephp-charts-ios-bar
    nativephp-charts-ios-bar-selected
    nativephp-charts-ios-candlestick
    nativephp-charts-ios-candlestick-selected
    nativephp-charts-ios-contribution-heatmap
    nativephp-charts-ios-contribution-heatmap-selected
    nativephp-charts-ios-donut
    nativephp-charts-ios-donut-selected
    nativephp-charts-ios-line
    nativephp-charts-ios-line-selected
    nativephp-charts-ios-pie
    nativephp-charts-ios-pie-selected
    nativephp-charts-ios-progress
    nativephp-charts-ios-progress-selected
    nativephp-charts-ios-radar
    nativephp-charts-ios-radar-selected
    nativephp-charts-ios-scatter
    nativephp-charts-ios-scatter-selected
  ]

  screenshots = File.join(output, "screenshots")
  FileUtils.mkdir_p(screenshots)
  exported = {}

  manifest.each do |test|
    test.fetch("attachments", []).each do |attachment|
      suggested = attachment.fetch("suggestedHumanReadableName")
      clean = suggested.sub(/_0_[0-9A-F-]+(?=\.png\z)/, "")
      next unless expected.include?(clean.delete_suffix(".png"))

      abort "Duplicate iOS gallery attachment: #{clean}" if exported.key?(clean)

      source = File.join(output, attachment.fetch("exportedFileName"))
      abort "Missing exported iOS gallery attachment: #{source}" unless File.file?(source)

      FileUtils.cp(source, File.join(screenshots, clean))
      exported[clean] = test.fetch("testIdentifier")
    end
  end

  missing = expected.reject { |name| exported.key?("#{name}.png") }
  abort "Missing iOS gallery attachments: #{missing.join(", ")}" unless missing.empty?

  File.write(
    File.join(output, "gallery-summary.json"),
    JSON.pretty_generate({ "screenshots" => exported.sort.to_h }) + "\n"
  )
  puts "Validated #{exported.length} iOS gallery screenshots."
' "$output_directory"

xcrun xcresulttool get test-results summary \
    --path "$result_bundle" \
    > "$output_directory/test-summary.json"
