<?php

use Donmanueldev\NativephpCharts\ViewportChange;

it('decodes the canonical version 1 viewport change payload', function () {
    $change = ViewportChange::fromJson(json_encode([
        'version' => 1,
        'chart_type' => 'line',
        'axis' => 'x',
        'reason' => 'pan_zoom',
        'x_type' => 'datetime',
        'minimum' => '2026-08-29T08:00:00Z',
        'maximum' => '2026-08-29T12:30:00-06:00',
    ], JSON_THROW_ON_ERROR));

    expect($change->chartType)->toBe('line')
        ->and($change->reason)->toBe('pan_zoom')
        ->and($change->xType)->toBe('datetime')
        ->and($change->minimum)->toBe('2026-08-29T08:00:00+00:00')
        ->and($change->maximum)->toBe('2026-08-29T12:30:00-06:00')
        ->and($change->toArray())->toBe([
            'version' => 1,
            'chart_type' => 'line',
            'axis' => 'x',
            'reason' => 'pan_zoom',
            'x_type' => 'datetime',
            'minimum' => '2026-08-29T08:00:00+00:00',
            'maximum' => '2026-08-29T12:30:00-06:00',
        ]);
});

it('decodes numeric and date viewport boundaries', function (string $xType, int|float|string $minimum, int|float|string $maximum) {
    $change = ViewportChange::fromJson(json_encode([
        'version' => 1,
        'chart_type' => 'area',
        'axis' => 'x',
        'reason' => 'zoom',
        'x_type' => $xType,
        'minimum' => $minimum,
        'maximum' => $maximum,
    ], JSON_THROW_ON_ERROR));

    expect($change->minimum)->toBe($minimum)
        ->and($change->maximum)->toBe($maximum);
})->with([
    'number' => ['number', -12.5, 42],
    'date' => ['date', '2026-08-01', '2026-08-31'],
]);

it('rejects malformed or incompatible viewport change payloads', function (string $json, string $message) {
    expect(fn () => ViewportChange::fromJson($json))->toThrow(InvalidArgumentException::class, $message);
})->with([
    'invalid json' => ['{', 'valid JSON'],
    'list instead of object' => ['[]', 'JSON object'],
    'unsupported version' => ['{"version":2}', "version '2'"],
    'unsupported chart' => ['{"version":1,"chart_type":"radar"}', 'chart type'],
    'unsupported axis' => ['{"version":1,"chart_type":"line","axis":"y"}', 'axis'],
    'generic reason' => ['{"version":1,"chart_type":"line","axis":"x","reason":"gesture"}', 'reason'],
    'category viewport' => ['{"version":1,"chart_type":"line","axis":"x","reason":"pan","x_type":"category"}', 'x type'],
    'missing boundary' => ['{"version":1,"chart_type":"line","axis":"x","reason":"pan","x_type":"number","minimum":0}', "property 'maximum' is required"],
    'string numeric boundary' => ['{"version":1,"chart_type":"line","axis":"x","reason":"pan","x_type":"number","minimum":"0","maximum":10}', 'finite integer or float'],
    'reversed range' => ['{"version":1,"chart_type":"line","axis":"x","reason":"zoom","x_type":"date","minimum":"2026-08-31","maximum":"2026-08-01"}', 'minimum must be less than maximum'],
]);
