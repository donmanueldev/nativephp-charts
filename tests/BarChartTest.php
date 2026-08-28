<?php

use Donmanueldev\NativephpCharts\Elements\BarChart;
use Native\Mobile\Edge\CallbackRegistry;

it('serializes the shared chart contract as a bar chart node', function () {
    $node = BarChart::make()
        ->series([[
            'id' => 'orders',
            'name' => 'Orders',
            'color' => '#14B8A6',
            'points' => [
                ['label' => 'Monday', 'value' => 12],
                ['label' => 'Tuesday', 'value' => -3],
            ],
        ]])
        ->showGrid(false)
        ->showPoints(false)
        ->beginAtZero(false)
        ->animated(false)
        ->emptyLabel('No orders yet')
        ->locale('en-US')
        ->valueFormat('currency')
        ->currencyCode('USD')
        ->minimumFractionDigits(0)
        ->maximumFractionDigits(2)
        ->style([
            'grid' => ['visible' => true, 'color' => '#E2E8F0'],
            'axis' => ['font' => 'accent', 'labelCount' => 4],
        ])
        ->a11yLabel('Daily orders')
        ->toArray(new CallbackRegistry);

    expect($node['type'])->toBe('bar_chart')
        ->and($node['props'])->toBe([
            'show_grid' => false,
            'show_points' => false,
            'begin_at_zero' => false,
            'animated' => false,
            'empty_label' => 'No orders yet',
            'a11y_label' => 'Daily orders',
            'locale' => 'en-US',
            'value_format' => 'currency',
            'currency_code' => 'USD',
            'minimum_fraction_digits' => 0,
            'maximum_fraction_digits' => 2,
            'style_json' => '{"grid":{"visible":true,"color":"#E2E8F0"},"axis":{"font":"accent","label_count":4}}',
            'series_json' => '[{"id":"orders","name":"Orders","color":"#14B8A6","points":[{"label":"Monday","value":12},{"label":"Tuesday","value":-3}]}]',
        ]);
});
