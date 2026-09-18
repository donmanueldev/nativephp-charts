<?php

namespace Donmanueldev\NativephpCharts\Support;

use InvalidArgumentException;

final class PerformanceBaselineComparator
{
    public const SCHEMA_VERSION = 1;

    public const MAX_LATENCY_REGRESSION_PERCENT = 10.0;

    public const MAX_MEMORY_REGRESSION_PERCENT = 15.0;

    /** @var list<string> */
    private const COMPARABLE_CONTEXT = [
        'platform',
        'device_model',
        'os_version',
        'build_type',
        'locale',
        'theme',
        'reduced_motion',
        'iterations',
        'aggregation',
    ];

    /** @var list<int> */
    private const REQUIRED_POINT_COUNTS = [100, 1_000, 10_000];

    /**
     * @param  array<string, mixed>  $baseline
     * @param  array<string, mixed>  $candidate
     * @return array{passed: bool, failures: list<string>, comparisons: list<array<string, int|float|string>>}
     */
    public static function compare(array $baseline, array $candidate): array
    {
        self::validateRun($baseline, 'baseline');
        self::validateRun($candidate, 'candidate');

        if ($baseline['approved'] !== true) {
            throw new InvalidArgumentException('The baseline must be explicitly approved.');
        }

        self::assertComparableContext($baseline['context'], $candidate['context']);

        $baselineMeasurements = self::indexMeasurements($baseline['measurements'], 'baseline');
        $candidateMeasurements = self::indexMeasurements($candidate['measurements'], 'candidate');
        $baselineKeys = array_keys($baselineMeasurements);
        $candidateKeys = array_keys($candidateMeasurements);
        sort($baselineKeys, SORT_NATURAL);
        sort($candidateKeys, SORT_NATURAL);

        if ($baselineKeys !== $candidateKeys) {
            $missing = array_values(array_diff($baselineKeys, $candidateKeys));
            $unexpected = array_values(array_diff($candidateKeys, $baselineKeys));

            throw new InvalidArgumentException(sprintf(
                'Measurement matrix mismatch. Missing: [%s]. Unexpected: [%s].',
                implode(', ', $missing),
                implode(', ', $unexpected),
            ));
        }

        $failures = [];
        $comparisons = [];

        foreach ($baselineKeys as $key) {
            $reference = $baselineMeasurements[$key];
            $current = $candidateMeasurements[$key];
            $latencyRegression = self::percentageChange($reference['latency_ms'], $current['latency_ms']);
            $memoryRegression = self::percentageChange($reference['memory_mb'], $current['memory_mb']);

            if ($latencyRegression > self::MAX_LATENCY_REGRESSION_PERCENT) {
                $failures[] = sprintf(
                    '%s latency regressed %.2f%% (%.3f ms -> %.3f ms; maximum %.1f%%).',
                    $key,
                    $latencyRegression,
                    $reference['latency_ms'],
                    $current['latency_ms'],
                    self::MAX_LATENCY_REGRESSION_PERCENT,
                );
            }

            if ($memoryRegression > self::MAX_MEMORY_REGRESSION_PERCENT) {
                $failures[] = sprintf(
                    '%s memory regressed %.2f%% (%.3f MB -> %.3f MB; maximum %.1f%%).',
                    $key,
                    $memoryRegression,
                    $reference['memory_mb'],
                    $current['memory_mb'],
                    self::MAX_MEMORY_REGRESSION_PERCENT,
                );
            }

            if ($current['callbacks'] !== $current['expected_callbacks']) {
                $failures[] = sprintf(
                    '%s emitted %d callbacks; expected %d.',
                    $key,
                    $current['callbacks'],
                    $current['expected_callbacks'],
                );
            }

            $comparisons[] = [
                'key' => $key,
                'latency_regression_percent' => round($latencyRegression, 3),
                'memory_regression_percent' => round($memoryRegression, 3),
                'slow_frames_delta' => $current['slow_frames'] - $reference['slow_frames'],
                'payload_bytes_delta' => $current['payload_bytes'] - $reference['payload_bytes'],
                'callbacks' => $current['callbacks'],
            ];
        }

        return [
            'passed' => $failures === [],
            'failures' => $failures,
            'comparisons' => $comparisons,
        ];
    }

    /**
     * @param  array<string, mixed>  $run
     */
    private static function validateRun(array $run, string $name): void
    {
        if (($run['schema_version'] ?? null) !== self::SCHEMA_VERSION) {
            throw new InvalidArgumentException("The {$name} schema_version must be ".self::SCHEMA_VERSION.'.');
        }

        if (! array_key_exists('approved', $run) || ! is_bool($run['approved'])) {
            throw new InvalidArgumentException("The {$name} approved field must be boolean.");
        }

        if (! isset($run['context']) || ! is_array($run['context'])) {
            throw new InvalidArgumentException("The {$name} context must be an object.");
        }

        foreach (self::COMPARABLE_CONTEXT as $field) {
            if (! array_key_exists($field, $run['context'])) {
                throw new InvalidArgumentException("The {$name} context.{$field} field is required.");
            }

            $value = $run['context'][$field];
            $invalid = match ($field) {
                'reduced_motion' => ! is_bool($value),
                'iterations' => ! is_int($value) || $value < 3,
                default => ! is_string($value) || trim($value) === '',
            };
            if ($invalid) {
                throw new InvalidArgumentException("The {$name} context.{$field} field has an invalid type or value.");
            }
        }

        self::assertAllowedContextValue($run['context'], 'platform', ['ios', 'android'], $name);
        self::assertAllowedContextValue($run['context'], 'build_type', ['release', 'profile'], $name);
        self::assertAllowedContextValue($run['context'], 'theme', ['light', 'dark'], $name);
        self::assertAllowedContextValue(
            $run['context'],
            'aggregation',
            ['median_latency_peak_memory'],
            $name,
        );

        if (! isset($run['measurements']) || ! is_array($run['measurements']) || ! array_is_list($run['measurements']) || $run['measurements'] === []) {
            throw new InvalidArgumentException("The {$name} measurements must be a non-empty list.");
        }
    }

    /**
     * @param  array<string, mixed>  $baseline
     * @param  array<string, mixed>  $candidate
     */
    private static function assertComparableContext(array $baseline, array $candidate): void
    {
        foreach (self::COMPARABLE_CONTEXT as $field) {
            if ($baseline[$field] !== $candidate[$field]) {
                throw new InvalidArgumentException(sprintf(
                    'Context mismatch for %s: baseline=%s candidate=%s.',
                    $field,
                    json_encode($baseline[$field], JSON_THROW_ON_ERROR),
                    json_encode($candidate[$field], JSON_THROW_ON_ERROR),
                ));
            }
        }
    }

    /**
     * @param  list<mixed>  $measurements
     * @return array<string, array{chart: string, points: int, scenario: string, latency_ms: float, memory_mb: float, slow_frames: int, payload_bytes: int, callbacks: int, expected_callbacks: int}>
     */
    private static function indexMeasurements(array $measurements, string $name): array
    {
        $indexed = [];

        foreach ($measurements as $index => $measurement) {
            if (! is_array($measurement)) {
                throw new InvalidArgumentException("The {$name} measurement at index {$index} must be an object.");
            }

            $chart = self::requiredString($measurement, 'chart', $name, $index);
            $scenario = self::requiredString($measurement, 'scenario', $name, $index);
            $points = self::requiredInteger($measurement, 'points', $name, $index, minimum: 1);
            if (! in_array($points, self::REQUIRED_POINT_COUNTS, true)) {
                throw new InvalidArgumentException(
                    "The {$name} measurement {$index}.points must be 100, 1000, or 10000.",
                );
            }
            $latency = self::requiredNumber($measurement, 'latency_ms', $name, $index, positive: true);
            $memory = self::requiredNumber($measurement, 'memory_mb', $name, $index, positive: true);
            $slowFrames = self::requiredInteger($measurement, 'slow_frames', $name, $index, minimum: 0);
            $payloadBytes = self::requiredInteger($measurement, 'payload_bytes', $name, $index, minimum: 1);
            $callbacks = self::requiredInteger($measurement, 'callbacks', $name, $index, minimum: 0);
            $expectedCallbacks = self::requiredInteger($measurement, 'expected_callbacks', $name, $index, minimum: 0);
            $key = "{$chart}:{$points}:{$scenario}";

            if ($name === 'baseline' && $callbacks !== $expectedCallbacks) {
                throw new InvalidArgumentException(
                    "The approved baseline measurement {$key} emitted {$callbacks} callbacks; expected {$expectedCallbacks}.",
                );
            }

            if (isset($indexed[$key])) {
                throw new InvalidArgumentException("The {$name} contains duplicate measurement {$key}.");
            }

            $indexed[$key] = [
                'chart' => $chart,
                'points' => $points,
                'scenario' => $scenario,
                'latency_ms' => $latency,
                'memory_mb' => $memory,
                'slow_frames' => $slowFrames,
                'payload_bytes' => $payloadBytes,
                'callbacks' => $callbacks,
                'expected_callbacks' => $expectedCallbacks,
            ];
        }

        $densityGroups = [];
        foreach ($indexed as $measurement) {
            $densityGroups[$measurement['chart'].':'.$measurement['scenario']][] = $measurement['points'];
        }
        foreach ($densityGroups as $group => $pointCounts) {
            sort($pointCounts);
            if ($pointCounts !== self::REQUIRED_POINT_COUNTS) {
                throw new InvalidArgumentException(
                    "The {$name} measurement group {$group} must include 100, 1000, and 10000 points.",
                );
            }
        }

        return $indexed;
    }

    /** @param array<string, mixed> $measurement */
    private static function requiredString(array $measurement, string $field, string $name, int $index): string
    {
        $value = $measurement[$field] ?? null;
        if (! is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException("The {$name} measurement {$index}.{$field} must be a non-empty string.");
        }

        return $value;
    }

    /** @param array<string, mixed> $measurement */
    private static function requiredInteger(array $measurement, string $field, string $name, int $index, int $minimum): int
    {
        $value = $measurement[$field] ?? null;
        if (! is_int($value) || $value < $minimum) {
            throw new InvalidArgumentException("The {$name} measurement {$index}.{$field} must be an integer >= {$minimum}.");
        }

        return $value;
    }

    /** @param array<string, mixed> $measurement */
    private static function requiredNumber(array $measurement, string $field, string $name, int $index, bool $positive): float
    {
        $value = $measurement[$field] ?? null;
        if (! is_int($value) && ! is_float($value)) {
            throw new InvalidArgumentException("The {$name} measurement {$index}.{$field} must be numeric.");
        }

        $number = (float) $value;
        if (! is_finite($number) || ($positive && $number <= 0)) {
            throw new InvalidArgumentException("The {$name} measurement {$index}.{$field} must be finite and positive.");
        }

        return $number;
    }

    private static function percentageChange(float $baseline, float $candidate): float
    {
        return (($candidate - $baseline) / $baseline) * 100;
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  list<string>  $allowed
     */
    private static function assertAllowedContextValue(array $context, string $field, array $allowed, string $name): void
    {
        if (! in_array($context[$field], $allowed, true)) {
            throw new InvalidArgumentException(sprintf(
                'The %s context.%s field must be one of: %s.',
                $name,
                $field,
                implode(', ', $allowed),
            ));
        }
    }
}
