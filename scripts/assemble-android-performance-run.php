#!/usr/bin/env php
<?php

declare(strict_types=1);

const REQUIRED_POINT_COUNTS = [100, 1_000, 10_000];

if ($argc !== 8) {
    fwrite(STDERR, "usage: {$argv[0]} ARTIFACT_DIR DEVICE_MODEL OS_VERSION ITERATIONS REVISION RECORDED_AT OUTPUT_JSON\n");
    exit(2);
}

[, $artifactDirectory, $deviceModel, $osVersion, $iterationsRaw, $revision, $recordedAt, $outputPath] = $argv;
$iterations = filter_var($iterationsRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 3]]);
if ($iterations === false) {
    fail('ITERATIONS must be an integer greater than or equal to 3.');
}
if (! is_dir($artifactDirectory)) {
    fail("Artifact directory does not exist: {$artifactDirectory}");
}

$memoryRows = readCsv("{$artifactDirectory}/memory.csv");
$measurements = [];

foreach (REQUIRED_POINT_COUNTS as $pointCount) {
    $markers = readJsonLines("{$artifactDirectory}/markers-{$pointCount}.jsonl");
    if (count($markers) !== $iterations) {
        fail("Expected {$iterations} markers for {$pointCount} points; found ".count($markers).'.');
    }

    $expectedRunIds = [];
    $markerLatencies = [];
    $latencies = [];
    $payloadBytes = null;
    $callbacks = 0;
    foreach ($markers as $marker) {
        assertMarker($marker, $pointCount);
        $runId = $marker['run_id'];
        if (isset($expectedRunIds[$runId])) {
            fail("Duplicate marker run_id: {$runId}");
        }
        $expectedRunIds[$runId] = true;
        $markerLatency = (float) $marker['latency_ms'];
        $markerLatencies[$runId] = $markerLatency;
        $latencies[] = $markerLatency;
        $payloadBytes ??= $marker['payload_bytes'];
        if ($payloadBytes !== $marker['payload_bytes']) {
            fail("Payload size changed between {$pointCount}-point iterations.");
        }
        $callbacks = max($callbacks, $marker['callbacks']);
    }

    $frames = readCsv("{$artifactDirectory}/frames-{$pointCount}.csv");
    if (count($frames) !== $iterations) {
        fail("Expected {$iterations} retained Perfetto runs for {$pointCount} points; found ".count($frames).'.');
    }
    $frameRunIds = [];
    $slowFrames = 0;
    foreach ($frames as $frame) {
        $runId = requiredString($frame, 'run_id');
        if (! isset($expectedRunIds[$runId])) {
            fail("Perfetto contains unexpected run_id: {$runId}");
        }
        if (isset($frameRunIds[$runId])) {
            fail("Perfetto contains duplicate run_id: {$runId}");
        }
        $frameRunIds[$runId] = true;
        $totalFrames = requiredInteger($frame, 'total_frames', 1);
        $traceLatency = requiredNumber($frame, 'trace_latency_ms', true);
        if (abs($traceLatency - $markerLatencies[$runId]) > 2.0) {
            fail(sprintf(
                'Latency sources disagree for %s: marker=%.3f ms trace=%.3f ms.',
                $runId,
                $markerLatencies[$runId],
                $traceLatency,
            ));
        }
        $slowFrames = max($slowFrames, requiredInteger($frame, 'slow_frames', 0));
        requiredNumber($frame, 'maximum_frame_ms', true);
        if ($totalFrames < 1) {
            fail("Perfetto retained no frames for {$runId}.");
        }
    }
    $missingRunIds = array_diff(array_keys($expectedRunIds), array_keys($frameRunIds));
    $unexpectedRunIds = array_diff(array_keys($frameRunIds), array_keys($expectedRunIds));
    if ($missingRunIds !== [] || $unexpectedRunIds !== []) {
        fail(sprintf(
            'Perfetto run-id mismatch. Missing: [%s]. Unexpected: [%s].',
            implode(', ', $missingRunIds),
            implode(', ', $unexpectedRunIds),
        ));
    }

    $rssValues = [];
    foreach ($memoryRows as $row) {
        if (requiredInteger($row, 'points', 1) === $pointCount) {
            $rssValues[] = requiredInteger($row, 'total_rss_kb', 1);
        }
    }
    if (count($rssValues) !== $iterations) {
        fail("Expected {$iterations} TOTAL RSS samples for {$pointCount} points; found ".count($rssValues).'.');
    }

    sort($latencies, SORT_NUMERIC);
    $measurements[] = [
        'chart' => 'line',
        'points' => $pointCount,
        'scenario' => 'first-render',
        'latency_ms' => round($latencies[intdiv(count($latencies), 2)], 3),
        'memory_mb' => round(max($rssValues) / 1024, 3),
        'slow_frames' => $slowFrames,
        'payload_bytes' => $payloadBytes,
        'callbacks' => $callbacks,
        'expected_callbacks' => 0,
    ];
}

$result = [
    'schema_version' => 1,
    'approved' => false,
    'context' => [
        'platform' => 'android',
        'device_model' => $deviceModel,
        'os_version' => $osVersion,
        'build_type' => 'profile',
        'locale' => 'en-US',
        'theme' => 'light',
        'reduced_motion' => true,
        'iterations' => $iterations,
        'aggregation' => 'median_latency_peak_memory',
        'revision' => $revision,
        'recorded_at' => $recordedAt,
    ],
    'measurements' => $measurements,
];

$encoded = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
if (file_put_contents($outputPath, $encoded) === false) {
    fail("Unable to write {$outputPath}.");
}

fwrite(STDOUT, "Wrote unapproved performance candidate: {$outputPath}\n");

/** @return list<array<string, string>> */
function readCsv(string $path): array
{
    $handle = fopen($path, 'rb');
    if ($handle === false) {
        fail("Unable to read {$path}.");
    }
    $headers = fgetcsv($handle, escape: '');
    if (! is_array($headers) || $headers === []) {
        fail("CSV has no header: {$path}");
    }
    $rows = [];
    while (($values = fgetcsv($handle, escape: '')) !== false) {
        if ($values === [null] || $values === []) {
            continue;
        }
        if (count($values) !== count($headers)) {
            fail("CSV row width does not match header: {$path}");
        }
        $rows[] = array_combine($headers, $values);
    }
    fclose($handle);
    return $rows;
}

/** @return list<array<string, mixed>> */
function readJsonLines(string $path): array
{
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        fail("Unable to read {$path}.");
    }
    return array_map(
        static fn (string $line): array => json_decode($line, true, 512, JSON_THROW_ON_ERROR),
        $lines,
    );
}

/** @param array<string, mixed> $marker */
function assertMarker(array $marker, int $pointCount): void
{
    $expected = [
        'schema_version' => 1,
        'chart' => 'line',
        'points' => $pointCount,
        'scenario' => 'first-render',
    ];
    foreach ($expected as $field => $value) {
        if (($marker[$field] ?? null) !== $value) {
            fail("Invalid marker {$field} for {$pointCount} points.");
        }
    }
    requiredString($marker, 'run_id');
    requiredNumber($marker, 'latency_ms', true);
    requiredInteger($marker, 'payload_bytes', 1);
    requiredInteger($marker, 'callbacks', 0);
}

/** @param array<string, mixed> $row */
function requiredString(array $row, string $field): string
{
    $value = $row[$field] ?? null;
    if (! is_string($value) || trim($value) === '') {
        fail("{$field} must be a non-empty string.");
    }
    return $value;
}

/** @param array<string, mixed> $row */
function requiredInteger(array $row, string $field, int $minimum): int
{
    $value = filter_var($row[$field] ?? null, FILTER_VALIDATE_INT);
    if ($value === false || $value < $minimum) {
        fail("{$field} must be an integer >= {$minimum}.");
    }
    return $value;
}

/** @param array<string, mixed> $row */
function requiredNumber(array $row, string $field, bool $positive): float
{
    $value = $row[$field] ?? null;
    if (! is_int($value) && ! is_float($value) && ! (is_string($value) && is_numeric($value))) {
        fail("{$field} must be numeric.");
    }
    $number = (float) $value;
    if (! is_finite($number) || ($positive && $number <= 0)) {
        fail("{$field} has an invalid numeric value.");
    }
    return $number;
}

function fail(string $message): never
{
    fwrite(STDERR, $message."\n");
    exit(2);
}
