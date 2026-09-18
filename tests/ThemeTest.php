<?php

use Donmanueldev\NativephpCharts\Elements\LineChart;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Native\Mobile\Edge\CallbackRegistry;

beforeEach(function () {
    $this->previousContainer = Container::getInstance();
    $container = new Container;
    $container->instance('config', new Repository(['nativephp-charts' => ['presets' => []]]));
    Container::setInstance($container);
});

afterEach(function () {
    Container::setInstance($this->previousContainer);
});

it('merges an application preset over the semantic default', function () {
    config()->set('nativephp-charts.presets.brand', [
        'light' => ['background' => '#FAFAFA', 'palette' => ['#123456']],
        'dark' => ['background' => '#101010', 'palette' => ['#ABCDEF']],
    ]);

    $props = LineChart::make()->preset('brand')->toArray(new CallbackRegistry)['props'];
    $theme = json_decode($props['theme_json'], true, flags: JSON_THROW_ON_ERROR);

    expect($theme['light'])->toMatchArray([
        'background' => '#FAFAFA',
        'foreground' => '#0F172A',
        'palette' => ['#123456'],
    ])->and($theme['dark'])->toMatchArray([
        'background' => '#101010',
        'foreground' => '#F8FAFC',
        'palette' => ['#ABCDEF'],
    ]);
});

it('rejects unknown theme modes and presets', function () {
    expect(fn () => LineChart::make()->theme('sepia'))
        ->toThrow(InvalidArgumentException::class, 'light, dark, or system')
        ->and(fn () => LineChart::make()->preset('missing'))
        ->toThrow(InvalidArgumentException::class, 'not registered');
});
