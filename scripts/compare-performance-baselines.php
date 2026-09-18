#!/usr/bin/env php
<?php

use Donmanueldev\NativephpCharts\Support\PerformanceBaselineComparator;

$autoloadCandidates = [
    dirname(__DIR__).'/vendor/autoload.php',
    dirname(__DIR__, 4).'/vendor/autoload.php',
];
$autoload = array_find($autoloadCandidates, static fn (string $path): bool => is_file($path));

if ($autoload === null) {
    fwrite(STDERR, "Composer autoload.php was not found. Run composer install first.\n");
    exit(2);
}

require $autoload;

if ($argc < 3 || $argc > 4 || ($argc === 4 && $argv[3] !== '--json')) {
    fwrite(STDERR, "Usage: php scripts/compare-performance-baselines.php baseline.json candidate.json [--json]\n");
    exit(2);
}

try {
    $decode = static function (string $path): array {
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException("Unable to read {$path}.");
        }

        $value = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($value)) {
            throw new RuntimeException("{$path} must contain a JSON object.");
        }

        return $value;
    };

    $report = PerformanceBaselineComparator::compare($decode($argv[1]), $decode($argv[2]));
} catch (Throwable $exception) {
    fwrite(STDERR, "Invalid performance evidence: {$exception->getMessage()}\n");
    exit(2);
}

if (($argv[3] ?? null) === '--json') {
    fwrite(STDOUT, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
} else {
    foreach ($report['comparisons'] as $comparison) {
        fwrite(STDOUT, sprintf(
            "%s latency=%+.3f%% memory=%+.3f%% slow_frames=%+d payload=%+dB callbacks=%d\n",
            $comparison['key'],
            $comparison['latency_regression_percent'],
            $comparison['memory_regression_percent'],
            $comparison['slow_frames_delta'],
            $comparison['payload_bytes_delta'],
            $comparison['callbacks'],
        ));
    }

    foreach ($report['failures'] as $failure) {
        fwrite(STDERR, "FAIL: {$failure}\n");
    }
}

exit($report['passed'] ? 0 : 1);
