<?php

use Donmanueldev\NativephpCharts\Elements\CandlestickChart;
use Native\Mobile\Edge\CallbackRegistry;

it('publishes an ordered OHLC series with close as the selection value', function () {
    $props = CandlestickChart::make()
        ->xAxis(['type' => 'date'])
        ->series([[
            'id' => 'nio-usd',
            'name' => 'NIO/USD',
            'color' => '#2563EB',
            'points' => [[
                'id' => '2026-08-29',
                'label' => '29 Aug',
                'x' => '2026-08-29',
                'open' => 36.72,
                'high' => 36.91,
                'low' => 36.68,
                'close' => 36.84,
            ]],
        ]])
        ->toArray(new CallbackRegistry)['props'];

    $point = json_decode($props['series_json'], true, flags: JSON_THROW_ON_ERROR)[0]['points'][0];

    expect($point)->toBe([
        'id' => '2026-08-29',
        'label' => '29 Aug',
        'value' => 36.84,
        'x' => '2026-08-29',
        'open' => 36.72,
        'high' => 36.91,
        'low' => 36.68,
        'close' => 36.84,
        'error_min' => 36.68,
        'error_max' => 36.91,
    ]);
});

it('publishes neutral candlestick colors and wick width globally and per series', function () {
    $props = CandlestickChart::make()
        ->style([
            'candlestick' => [
                'risingColor' => '#15803D',
                'fallingColor' => '#B91C1C',
                'neutralColor' => '#64748B',
                'wickWidth' => 2,
            ],
        ])
        ->series([[
            'id' => 'market',
            'name' => 'Market',
            'color' => '#2563EB',
            'style' => [
                'candlestick' => [
                    'rising_color' => '#166534',
                    'wick_width' => 2.5,
                ],
            ],
            'points' => [[
                'id' => 'day',
                'label' => 'Day',
                'open' => 10,
                'high' => 12,
                'low' => 9,
                'close' => 11,
            ]],
        ]])
        ->toArray(new CallbackRegistry)['props'];

    expect(json_decode($props['style_json'], true, flags: JSON_THROW_ON_ERROR))->toBe([
        'candlestick' => [
            'rising_color' => '#15803D',
            'falling_color' => '#B91C1C',
            'neutral_color' => '#64748B',
            'wick_width' => 2,
        ],
    ])->and(json_decode($props['series_json'], true, flags: JSON_THROW_ON_ERROR)[0]['style'])->toBe([
        'candlestick' => [
            'rising_color' => '#166534',
            'wick_width' => 2.5,
        ],
    ]);
});

it('rejects invalid candlestick visual styles', function (array $style, string $message) {
    expect(fn () => CandlestickChart::make()->style(['candlestick' => $style]))
        ->toThrow(InvalidArgumentException::class, $message);
})->with([
    'invalid rising color' => [['risingColor' => 'green'], 'candlestick.risingColor'],
    'zero wick width' => [['wickWidth' => 0], 'wickWidth must be greater than zero'],
    'oversized wick width' => [['wickWidth' => 9], 'wickWidth must be greater than zero'],
    'unknown option' => [['bodyOpacity' => 0.5], "candlestick.bodyOpacity' is not supported"],
]);

it('rejects invalid OHLC ranges and multiple series', function () {
    $invalid = [[
        'id' => 'market',
        'name' => 'Market',
        'color' => '#2563EB',
        'points' => [[
            'id' => 'day', 'label' => 'Day',
            'open' => 10, 'high' => 11, 'low' => 10.5, 'close' => 10.2,
        ]],
    ]];

    expect(fn () => CandlestickChart::make()->series($invalid))
        ->toThrow(InvalidArgumentException::class, 'OHLC range')
        ->and(fn () => CandlestickChart::make()->series([$invalid[0], [...$invalid[0], 'id' => 'other']]))
        ->toThrow(InvalidArgumentException::class, 'zero or one ordered series');
});

it('rejects LTTB sampling because it can hide OHLC extremes', function () {
    expect(fn () => CandlestickChart::make()
        ->sampling(['mode' => 'lttb', 'threshold' => 3])
        ->toArray(new CallbackRegistry))
        ->toThrow(InvalidArgumentException::class, 'does not support LTTB sampling');
});

it('registers the candlestick native component on both platforms', function () {
    $manifest = json_decode(file_get_contents(__DIR__.'/../nativephp.json'), true, flags: JSON_THROW_ON_ERROR);
    $component = collect($manifest['components'])->firstWhere('type', 'candlestick_chart');

    expect($component)->toMatchArray([
        'element' => 'Donmanueldev\\NativephpCharts\\Elements\\CandlestickChart',
        'blade' => 'Donmanueldev\\NativephpCharts\\Components\\CandlestickChart',
        'android_renderer' => 'com.donmanueldev.plugins.nativephp_charts.ui.NativePHPChartsCandlestickChartRenderer',
        'ios_renderer' => 'NativePHPChartsCandlestickChartRenderer',
        'self_closing' => true,
    ]);
});
