# Contributing to NativePHP Charts

Thanks for helping improve NativePHP Charts. This guide covers local setup, tests, the documentation site, and how changes are proposed.

By participating, you agree to follow the [Code of Conduct](CODE_OF_CONDUCT.md).

## Requirements

- PHP 8.4 or newer and Composer 2
- macOS with Xcode (Swift 6 toolchain) to run the Swift renderer tests
- [Bun](https://bun.sh/) 1.3 or newer to build the documentation site (the version CI uses is pinned in `.github/workflows/pages.yml`)
- A NativePHP Mobile 4 application with Android Studio and/or Xcode to try renderer changes on a device, emulator, or simulator

## Setup

```bash
git clone https://github.com/donmanueldev/nativephp-charts.git
cd nativephp-charts
composer install
```

## Running tests

```bash
composer test   # Pest: normalization, serialization, callbacks, and documentation examples
swift test      # Compiles the iOS renderers and runs the Swift tests in resources/ios/Tests
```

CI also runs `composer validate --strict` and `vendor/bin/testbench native:plugin:validate . --no-interaction`. Run them before opening a pull request when you change `composer.json` or `nativephp.json`.

The Kotlin renderers have no standalone test suite. Compile and check Android changes in a NativePHP Mobile application that has the package installed from your local checkout (for example through a Composer `path` repository), then rebuild the Android target.

## Documentation site

The documentation is an Astro and Starlight site in `docs/`. CI installs it with Bun, so `docs/bun.lock` is the lockfile that counts.

```bash
cd docs
bun install --frozen-lockfile
bun run dev      # local preview
bun run check    # Astro type and content checks
bun run build    # production build into docs/dist
```

The Blade examples in `docs/examples/` are rendered on the site and also exercised by `tests/DocumentationExamplesTest.php`, so keep them valid when you change a public attribute.

## How the source is organized

| Path | Contents |
| --- | --- |
| `src/Elements/` | Fluent element classes (`LineChart::make()`), attribute parsing, and the props sent to the renderers |
| `src/Components/` | Blade component classes behind the `<native:*>` tags |
| `src/Support/` | Validation and normalization of series, axes, styles, themes, and callbacks |
| `src/PointSelection.php`, `src/ViewportChange.php` | Decoders for the versioned callback payloads |
| `config/nativephp-charts.php` | Application preset registry |
| `nativephp.json` | Plugin manifest that maps each element to its iOS and Android renderer |
| `resources/ios/Sources/` | SwiftUI and Swift Charts renderers: one file per chart, plus `Core/`, `Interaction/`, `Rendering/`, and `Radar/` |
| `resources/android/src/` | Jetpack Compose renderers: `entrypoints/` (one per chart), `core/`, `interaction/`, and `rendering/` |
| `resources/boost/guidelines/` | Laravel Boost guideline that coding agents read in consumer apps |
| `tests/` | Pest tests; `tests/Fixtures/chart_contract_fixtures.json` is shared with the Swift tests |

A change to the public contract usually touches PHP validation, both native renderers, the shared fixtures, and the documentation. Keep iOS and Android behavior in parity.

## Branches and commits

- Branch from `main` with a prefix that matches the change: `feature/`, `bugfix/`, `docs/`, `chore/`, or `refactor/`.
- Write commit messages and pull request titles in English using [Conventional Commits](https://www.conventionalcommits.org/): `<type>(<scope>): <imperative summary>`, for example `fix(android): keep selection after data update` or `docs(guides): explain viewport callbacks`.
- Common types are `feat`, `fix`, `docs`, `test`, `refactor`, `perf`, `build`, `ci`, and `chore`. Mark breaking changes with `!` and explain them in the body.
- Keep one logical change per pull request, add or update tests, and update the documentation when a public attribute or behavior changes.
- Fill in the pull request template, including the platforms you tested.

The project follows [Semantic Versioning](https://semver.org/). Changes that would break the 1.x contract described in the [migration guide](https://donmanueldev.github.io/nativephp-charts/guides/migration/) need discussion in an issue first.

## Reporting bugs

Open a [bug report](https://github.com/donmanueldev/nativephp-charts/issues/new/choose) with the smallest Blade or PHP example that reproduces the problem, the package, NativePHP Mobile, Laravel, and PHP versions, and the platform and OS version. Remove secrets and personal data from logs.

Do not report security vulnerabilities in public issues. Follow [SECURITY.md](SECURITY.md) instead.
