<?php

use App\NativeComponents\ChartPerformanceHarness;
use Donmanueldev\NativephpCharts\Elements\LineChart;
use Native\Mobile\Edge\CallbackRegistry;

require_once __DIR__.'/../ios-harness/host/app/NativeComponents/ChartPerformanceHarness.php';

dataset('iOS performance densities', [
    '100 points' => ['useOneHundredPoints', 100],
    '1,000 points' => ['useOneThousandPoints', 1_000],
    '10,000 points' => ['useTenThousandPoints', 10_000],
]);

it('builds deterministic iOS performance payloads that match the line chart wire bytes', function (string $method, int $pointCount) {
    $harness = new ChartPerformanceHarness;
    $harness->{$method}();

    $props = LineChart::make()
        ->xAxis(['type' => 'number', 'labelCount' => 5])
        ->series($harness->series)
        ->toArray(new CallbackRegistry)['props'];
    $transport = $props['series_transport'] ?? 'inline-v1';
    $wirePayload = $transport === 'file-v1'
        ? file_get_contents($props['series_json_file'])
        : $props['series_json'];

    expect($wirePayload)->toBeString();
    $decoded = json_decode($wirePayload, true, flags: JSON_THROW_ON_ERROR);

    expect($harness->pointCount)->toBe($pointCount)
        ->and($harness->payloadBytes)->toBe(strlen($wirePayload))
        ->and($transport)->toBe($pointCount === 100 ? 'inline-v1' : 'file-v1')
        ->and($decoded)->toHaveCount(1)
        ->and($decoded[0]['id'])->toBe('performance')
        ->and($decoded[0]['points'])->toHaveCount($pointCount)
        ->and($decoded[0]['points'][0]['id'])->toBe('point-0')
        ->and($decoded[0]['points'][$pointCount - 1]['id'])->toBe('point-'.($pointCount - 1))
        ->and($harness->selectionCallbacks)->toBe(0);

    if ($transport === 'file-v1') {
        expect(basename($props['series_json_file']))->toBe('series-'.hash('sha256', $wirePayload).'.json');
    }
})->with('iOS performance densities');
