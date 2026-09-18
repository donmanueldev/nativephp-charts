# Android renderer contract harness

This project compiles every Compose renderer and runs its deterministic JVM tests.
It copies `NativeUINode.kt` directly from the installed `nativephp/mobile` Composer
dependency, so prop access and color parsing compile against NativePHP Mobile 4.x
instead of a local imitation. The event bridge and font loader are inert harness
adapters because the real implementations require the generated app's JNI runtime
and bundled assets.

Run:

```sh
gradle --project-dir android-harness testDebugUnitTest assembleDebug
```

With an emulator or device connected, run the Compose behavior suite:

```sh
gradle --project-dir android-harness connectedDebugAndroidTest
```

The instrumented suite renders the real Compose Canvas and covers tap, scrub,
pan, pinch, cancellation, vertical-scroll coexistence, accessible selection,
explicit unavailable semantics, callback cardinality, disposal/reopening, and
selection retention/clearing across insert, reorder, update, delete, and empty
mutations.

This APK is a compile harness, not runtime/device evidence. Validate the exact
plugin inside a generated NativePHP shell after NativePHP has compiled the
registered plugins:

```sh
cd /path/to/app
php artisan native:run android --build=bundle --no-tty --no-interaction

cd /path/to/nativephp-charts
scripts/validate-generated-android-shell.sh /path/to/app/nativephp/android
```

`native:install` only creates the base Android shell. It does not copy plugin
sources or generate renderer registrations, so it is not sufficient for this
gate.

CI keeps these as separate gates. The harness job owns deterministic renderer
and geometry tests plus Compose behavior on an emulator. The generated-shell job creates a clean Laravel host,
registers Mobile UI and Charts, builds a real Android App Bundle, verifies all
ten generated renderer registrations, and compiles the generated debug shell.
That separation makes plugin discovery and source-generation failures visible
instead of letting the standalone harness hide them.

Physical performance recording uses the non-debuggable `profile` APK and the repository Perfetto
configuration. See `performance/README.md` for the exact command. The recorder requires an awake,
unlocked device, holds the screen on only while its Activity is visible, and fails rather than
assembling a candidate when any trace window is missing.

`NativePHPChartsGalleryActivity` mounts one real renderer at a time with deterministic fixtures for
all ten chart families. Capture a fresh physical Android documentation matrix after `assembleProfile`:

```sh
ADB="$ANDROID_HOME/platform-tools/adb" \
scripts/capture-android-gallery.sh DEVICE_SERIAL docs/public/evidence/android
```

These images prove installed harness rendering only. Keep generated-shell interaction, manual
TalkBack, iOS, and release evidence labeled separately.
