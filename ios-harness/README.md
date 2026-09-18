# iOS generated-shell behavior harness

This harness installs one deterministic Laravel `NativeComponent` into a clean host, bundles the
package through NativePHP Mobile, and runs XCTest UI tests against the generated simulator app.
It deliberately uses the package's public EDGE attributes and the generated plugin sources.

The suite checks:

- tap and scrub selection, with one callback only after gesture completion;
- settled pan and pinch viewport callbacks;
- vertical scrolling around a chart without an accidental selection;
- stable `seriesId + pointId` selection through insert, reorder, and update;
- selection clearing after deletion and empty-data semantics;
- disposal and reopening without duplicate callbacks.
- a full-size accessibility hit target, preventing the semantic chart surface from collapsing to
  its summary text;
- deterministic line presentation at 100, 1,000, and 10,000 points with zero presentation
  callbacks and the exact inline or content-addressed file payload size.

Run it after producing a generated shell:

```bash
scripts/run-generated-ios-ui-tests.sh /path/to/host/nativephp/ios
```

Run only the density gate while iterating on performance:

```bash
NATIVEPHP_CHARTS_XCTEST_ONLY='NativePHPUITests/NativePHPChartsUITests/testPerformanceHarnessCoversEveryDensityWithoutPresentationCallbacks' \
scripts/run-generated-ios-ui-tests.sh /path/to/host/nativephp/ios
```

Preserve and validate the XCTest evidence by supplying a new result-bundle path, then export its
twenty canonical default/selected gallery captures:

```bash
NATIVEPHP_CHARTS_XCRESULT_PATH=/path/to/nativephp-charts-ios.xcresult \
scripts/run-generated-ios-ui-tests.sh /path/to/host/nativephp/ios

scripts/export-ios-gallery-evidence.sh \
  /path/to/nativephp-charts-ios.xcresult \
  /path/to/new/evidence-directory
```

The runner rebinds NativePHP Mobile's existing UI-test target to `NativePHP-simulator`, creates a
shared test scheme, selects an available iPhone simulator, removes that generated bundle's previous
application container, and disables code signing. Clearing only that bundle is required because
NativePHP extracts Laravel into its container and an incremental XCTest install can otherwise run
stale PHP or Blade files. Simulator UI tests are not physical-device performance or manual VoiceOver
evidence. The exporter fails unless all ten chart families provide both their default and
selected-state screenshots.
