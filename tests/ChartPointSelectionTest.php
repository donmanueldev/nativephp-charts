<?php

use Donmanueldev\NativephpCharts\PointSelection;

it('decodes the canonical version 1 point selection payload', function () {
    $selection = PointSelection::fromJson(json_encode([
        'version' => 1,
        'chart_type' => 'area',
        'series_id' => 'actual',
        'series_name' => 'Actual',
        'point_id' => 'actual-2026-08',
        'point_index' => 7,
        'x_type' => 'date',
        'x' => '2026-08-28',
        'label' => 'August',
        'value' => 42000.5,
        'localized_value' => 'C$42,000.50',
    ], JSON_THROW_ON_ERROR));

    expect($selection->chartType)->toBe('area')
        ->and($selection->seriesId)->toBe('actual')
        ->and($selection->pointId)->toBe('actual-2026-08')
        ->and($selection->x)->toBe('2026-08-28')
        ->and($selection->value)->toBe(42000.5)
        ->and($selection->toArray())->toHaveKeys([
            'version', 'chart_type', 'series_id', 'series_name', 'point_id', 'point_index',
            'x_type', 'x', 'label', 'value', 'localized_value',
        ]);
});

it('decodes numeric and datetime x values', function (string $xType, mixed $x, mixed $expected) {
    $selection = PointSelection::fromJson(json_encode([
        'version' => 1,
        'chart_type' => 'line',
        'series_id' => 'series',
        'series_name' => 'Series',
        'point_id' => 'point',
        'point_index' => 0,
        'x_type' => $xType,
        'x' => $x,
        'label' => 'Point',
        'value' => 1,
        'localized_value' => '1',
    ], JSON_THROW_ON_ERROR));

    expect($selection->x)->toBe($expected);
})->with([
    'number' => ['number', 8.5, 8.5],
    'datetime' => ['datetime', '2026-08-28T14:30:00Z', '2026-08-28T14:30:00+00:00'],
    'fractional datetime' => ['datetime', '2026-08-28T14:30:00.123456Z', '2026-08-28T14:30:00.123456+00:00'],
]);

it('rejects malformed or incompatible selection payloads', function (string $json, string $message) {
    expect(fn () => PointSelection::fromJson($json))->toThrow(InvalidArgumentException::class, $message);
})->with([
    'invalid json' => ['{', 'valid JSON'],
    'list instead of object' => ['[]', 'JSON object'],
    'unsupported version' => ['{"version":2,"chart_type":"line"}', "version '2'"],
    'unsupported chart' => [json_encode([
        'version' => 1, 'chart_type' => 'radar', 'x_type' => 'category', 'x' => 'A',
    ]), 'chart type'],
    'negative index' => [json_encode([
        'version' => 1, 'chart_type' => 'bar', 'series_id' => 's', 'series_name' => 'S',
        'point_id' => 'p', 'point_index' => -1, 'x_type' => 'category', 'x' => 'A',
        'label' => 'A', 'value' => 1, 'localized_value' => '1',
    ]), 'must not be negative'],
    'invalid numeric x' => [json_encode([
        'version' => 1, 'chart_type' => 'bar', 'x_type' => 'number', 'x' => '1', 'value' => 1,
    ]), 'finite integer or float'],
]);

it('decodes scatter and radial selections with the shared version 1 payload', function (string $chartType) {
    $selection = PointSelection::fromJson(json_encode([
        'version' => 1,
        'chart_type' => $chartType,
        'series_id' => 'web',
        'series_name' => 'Web',
        'point_id' => 'web',
        'point_index' => 0,
        'x_type' => $chartType === 'scatter' ? 'number' : 'category',
        'x' => $chartType === 'scatter' ? 1.5 : 'Web',
        'label' => 'Web',
        'value' => 70,
        'localized_value' => '70',
    ], JSON_THROW_ON_ERROR));

    expect($selection->chartType)->toBe($chartType)
        ->and($selection->seriesId)->toBe('web')
        ->and($selection->pointId)->toBe('web');
})->with(['scatter', 'pie', 'donut']);

it('rejects radial payloads that violate segment identity invariants', function (array $changes, string $message) {
    $payload = [
        'version' => 1,
        'chart_type' => 'donut',
        'series_id' => 'web',
        'series_name' => 'Web',
        'point_id' => 'web',
        'point_index' => 0,
        'x_type' => 'category',
        'x' => 'Web',
        'label' => 'Web',
        'value' => 70,
        'localized_value' => '70',
    ];

    expect(fn () => PointSelection::fromJson(json_encode([...$payload, ...$changes], JSON_THROW_ON_ERROR)))
        ->toThrow(InvalidArgumentException::class, $message);
})->with([
    'typed x' => [['x_type' => 'number', 'x' => 1], 'category x type'],
    'different ids' => [['point_id' => 'other'], 'both series_id and point_id'],
    'different x label' => [['x' => 'Other'], 'series_name, x, and label'],
    'different visible label' => [['label' => 'Other'], 'series_name, x, and label'],
]);
