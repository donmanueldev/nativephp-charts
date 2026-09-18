<?php

beforeEach(function () {
    $this->artifactDirectory = sys_get_temp_dir().'/nativephp-charts-performance-'.bin2hex(random_bytes(8));
    mkdir($this->artifactDirectory, 0700, true);
});

afterEach(function () {
    foreach (glob($this->artifactDirectory.'/*') ?: [] as $path) {
        if (is_file($path)) {
            unlink($path);
        }
    }
    rmdir($this->artifactDirectory);
});

it('assembles a complete physical Android candidate and rejects missing trace iterations', function () {
    $memoryRows = ["points,iteration,total_rss_kb"];
    foreach ([100, 1_000, 10_000] as $pointCount) {
        $markers = [];
        $frames = ["run_id,pid,trace_latency_ms,total_frames,slow_frames,maximum_frame_ms"];
        foreach (range(1, 5) as $iteration) {
            $runId = "run-{$pointCount}-{$iteration}";
            $latency = ($pointCount / 100) + ($iteration * 10);
            $markers[] = json_encode([
                'schema_version' => 1,
                'run_id' => $runId,
                'chart' => 'line',
                'points' => $pointCount,
                'scenario' => 'first-render',
                'latency_ms' => $latency,
                'payload_bytes' => $pointCount * 10,
                'callbacks' => 0,
            ], JSON_THROW_ON_ERROR);
            $memoryRows[] = "{$pointCount},{$iteration},".($pointCount + 100_000 + $iteration);
            $frames[] = "{$runId},".(20_000 + $iteration).",{$latency},3,".($iteration % 2).",24.5";
        }
        file_put_contents(
            "{$this->artifactDirectory}/markers-{$pointCount}.jsonl",
            implode("\n", $markers)."\n",
        );
        file_put_contents(
            "{$this->artifactDirectory}/frames-{$pointCount}.csv",
            implode("\n", $frames)."\n",
        );
    }
    file_put_contents("{$this->artifactDirectory}/memory.csv", implode("\n", $memoryRows)."\n");

    $outputPath = "{$this->artifactDirectory}/candidate.json";
    $command = implode(' ', array_map('escapeshellarg', [
        PHP_BINARY,
        dirname(__DIR__).'/scripts/assemble-android-performance-run.php',
        $this->artifactDirectory,
        'SM-S918B',
        'Android 16 (test)',
        '5',
        'abc123+dirty',
        '2026-09-16T21:39:05Z',
        $outputPath,
    ]));
    exec($command.' 2>&1', $output, $exitCode);

    expect($exitCode)->toBe(0)
        ->and(file_exists($outputPath))->toBeTrue();
    $candidate = json_decode(file_get_contents($outputPath), true, flags: JSON_THROW_ON_ERROR);
    expect($candidate['approved'])->toBeFalse()
        ->and($candidate['context']['iterations'])->toBe(5)
        ->and($candidate['context']['reduced_motion'])->toBeTrue()
        ->and($candidate['measurements'])->toHaveCount(3)
        ->and($candidate['measurements'][0])->toMatchArray([
            'points' => 100,
            'latency_ms' => 31.0,
            'memory_mb' => round(100_105 / 1024, 3),
            'slow_frames' => 1,
            'callbacks' => 0,
        ]);

    $framePath = "{$this->artifactDirectory}/frames-10000.csv";
    $frameLines = file($framePath, FILE_IGNORE_NEW_LINES);
    file_put_contents($framePath, implode("\n", array_slice($frameLines, 0, -1))."\n");
    $incompletePath = "{$this->artifactDirectory}/incomplete.json";
    $incompleteCommand = str_replace(
        escapeshellarg($outputPath),
        escapeshellarg($incompletePath),
        $command,
    );
    exec($incompleteCommand.' 2>&1', $incompleteOutput, $incompleteExitCode);

    expect($incompleteExitCode)->toBe(2)
        ->and(file_exists($incompletePath))->toBeFalse()
        ->and(implode("\n", $incompleteOutput))->toContain('Expected 5 retained Perfetto runs for 10000 points; found 4.');
});
