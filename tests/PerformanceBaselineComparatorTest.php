<?php

use Donmanueldev\NativephpCharts\Support\PerformanceBaselineComparator;

function performanceRun(array $measurementOverrides = [], array $contextOverrides = [], bool $approved = true): array
{
    $measurement = static fn (int $points): array => [
        'chart' => 'line',
        'points' => $points,
        'scenario' => 'completed-pan',
        'latency_ms' => 100.0,
        'memory_mb' => 200.0,
        'slow_frames' => 2,
        'payload_bytes' => $points * 80,
        'callbacks' => 1,
        'expected_callbacks' => 1,
    ];

    return [
        'schema_version' => 1,
        'approved' => $approved,
        'context' => array_replace([
            'platform' => 'android',
            'device_model' => 'SM-S918B',
            'os_version' => '16',
            'build_type' => 'release',
            'locale' => 'es-NI',
            'theme' => 'dark',
            'reduced_motion' => true,
            'iterations' => 5,
            'aggregation' => 'median_latency_peak_memory',
            'revision' => 'candidate-revision',
        ], $contextOverrides),
        'measurements' => [
            $measurement(100),
            $measurement(1_000),
            [...$measurement(10_000), ...$measurementOverrides],
        ],
    ];
}

it('accepts the documented latency and memory boundaries', function () {
    $report = PerformanceBaselineComparator::compare(
        performanceRun(),
        performanceRun(['latency_ms' => 110.0, 'memory_mb' => 230.0]),
    );

    expect($report['passed'])->toBeTrue()
        ->and($report['failures'])->toBe([])
        ->and($report['comparisons'][2]['latency_regression_percent'])->toBe(10.0)
        ->and($report['comparisons'][2]['memory_regression_percent'])->toBe(15.0);
});

it('rejects latency and memory regressions beyond the approved budgets', function () {
    $report = PerformanceBaselineComparator::compare(
        performanceRun(),
        performanceRun(['latency_ms' => 110.01, 'memory_mb' => 230.01]),
    );

    expect($report['passed'])->toBeFalse()
        ->and($report['failures'])->toHaveCount(2)
        ->and($report['failures'][0])->toContain('latency regressed')
        ->and($report['failures'][1])->toContain('memory regressed');
});

it('rejects callback amplification even when performance improves', function () {
    $report = PerformanceBaselineComparator::compare(
        performanceRun(),
        performanceRun(['latency_ms' => 80, 'memory_mb' => 150, 'callbacks' => 2]),
    );

    expect($report['passed'])->toBeFalse()
        ->and($report['failures'])->toHaveCount(1)
        ->and($report['failures'][0])->toContain('emitted 2 callbacks; expected 1');
});

it('requires an approved baseline and comparable physical context', function () {
    expect(fn () => PerformanceBaselineComparator::compare(
        performanceRun(approved: false),
        performanceRun(),
    ))->toThrow(InvalidArgumentException::class, 'explicitly approved')
        ->and(fn () => PerformanceBaselineComparator::compare(
            performanceRun(),
            performanceRun(contextOverrides: ['device_model' => 'Pixel 10']),
        ))->toThrow(InvalidArgumentException::class, 'Context mismatch for device_model');
});

it('rejects incomplete density matrices', function () {
    $candidate = performanceRun();
    $candidate['measurements'][2]['scenario'] = 'first-render';

    expect(fn () => PerformanceBaselineComparator::compare(performanceRun(), $candidate))
        ->toThrow(InvalidArgumentException::class, 'must include 100, 1000, and 10000 points');
});

it('rejects a different scenario matrix even when every density tier exists', function () {
    $candidate = performanceRun();
    foreach ($candidate['measurements'] as &$measurement) {
        $measurement['scenario'] = 'first-render';
    }
    unset($measurement);

    expect(fn () => PerformanceBaselineComparator::compare(performanceRun(), $candidate))
        ->toThrow(InvalidArgumentException::class, 'Measurement matrix mismatch');
});

it('rejects unsupported density tiers and an invalid approved callback baseline', function () {
    $unsupported = performanceRun();
    $unsupported['measurements'][2]['points'] = 500;
    $invalidCallbacks = performanceRun();
    $invalidCallbacks['measurements'][2]['callbacks'] = 2;

    expect(fn () => PerformanceBaselineComparator::compare(
        $unsupported,
        $unsupported,
    ))->toThrow(InvalidArgumentException::class, 'must be 100, 1000, or 10000')
        ->and(fn () => PerformanceBaselineComparator::compare(
            $invalidCallbacks,
            performanceRun(),
        ))->toThrow(InvalidArgumentException::class, 'approved baseline measurement');
});

it('runs the repository comparator command with machine-readable output', function () {
    $directory = sys_get_temp_dir().'/nativephp-charts-performance-'.bin2hex(random_bytes(6));
    mkdir($directory, 0700, true);
    $baseline = "{$directory}/baseline.json";
    $candidate = "{$directory}/candidate.json";

    try {
        file_put_contents($baseline, json_encode(performanceRun(), JSON_THROW_ON_ERROR));
        file_put_contents($candidate, json_encode(performanceRun(['latency_ms' => 90]), JSON_THROW_ON_ERROR));
        $command = implode(' ', [
            escapeshellarg(PHP_BINARY),
            escapeshellarg(dirname(__DIR__).'/scripts/compare-performance-baselines.php'),
            escapeshellarg($baseline),
            escapeshellarg($candidate),
            '--json',
        ]);
        exec($command, $output, $exitCode);

        $report = json_decode(implode("\n", $output), true, flags: JSON_THROW_ON_ERROR);
        expect($exitCode)->toBe(0)
            ->and($report['passed'])->toBeTrue()
            ->and($report['comparisons'][2]['latency_regression_percent'])->toBe(-10);
    } finally {
        foreach ([$baseline, $candidate] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        rmdir($directory);
    }
});
