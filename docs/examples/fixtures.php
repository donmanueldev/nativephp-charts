<?php

use Donmanueldev\NativephpCharts\Elements\AreaChart;
use Donmanueldev\NativephpCharts\Elements\BarChart;
use Donmanueldev\NativephpCharts\Elements\CandlestickChart;
use Donmanueldev\NativephpCharts\Elements\ContributionHeatmap;
use Donmanueldev\NativephpCharts\Elements\DonutChart;
use Donmanueldev\NativephpCharts\Elements\LineChart;
use Donmanueldev\NativephpCharts\Elements\PieChart;
use Donmanueldev\NativephpCharts\Elements\ProgressChart;
use Donmanueldev\NativephpCharts\Elements\RadarChart;
use Donmanueldev\NativephpCharts\Elements\ScatterChart;

$cartesianSeries = [[
    'id' => 'revenue',
    'name' => 'Revenue',
    'color' => '#ED3F16',
    'points' => [
        ['id' => 'jan', 'label' => 'January', 'value' => 18],
        ['id' => 'feb', 'label' => 'February', 'value' => 26],
        ['id' => 'mar', 'label' => 'March', 'value' => 31],
    ],
]];

$segments = [
    ['id' => 'web', 'label' => 'Web', 'value' => 68, 'color' => '#ED3F16'],
    ['id' => 'store', 'label' => 'Store', 'value' => 32, 'color' => '#2563EB'],
];

return [
    'line' => [
        'element' => LineChart::class,
        'attributes' => ['series' => $cartesianSeries, 'theme' => 'system', 'preset' => 'default', 'error-label' => 'Revenue chart unavailable', 'a11y-label' => 'Revenue by month'],
    ],
    'area' => [
        'element' => AreaChart::class,
        'attributes' => ['series' => $cartesianSeries, 'area-mode' => 'stacked', 'style' => ['area' => ['opacity' => 0.35]], 'a11y-label' => 'Revenue magnitude by month'],
    ],
    'bar' => [
        'element' => BarChart::class,
        'attributes' => ['series' => $cartesianSeries, 'mode' => 'grouped', 'orientation' => 'vertical', 'style' => ['bar' => ['radius' => 6, 'width' => 18]], 'a11y-label' => 'Revenue by month'],
    ],
    'scatter' => [
        'element' => ScatterChart::class,
        'attributes' => ['series' => [[
            'id' => 'observed', 'name' => 'Observed', 'color' => '#ED3F16',
            'points' => [
                ['id' => 'one', 'label' => 'One', 'x' => 1, 'value' => 8],
                ['id' => 'two', 'label' => 'Two', 'x' => 2, 'value' => 13],
            ],
        ]], 'x-axis' => ['type' => 'number'], 'style' => ['points' => ['size' => 7]], 'a11y-label' => 'Response time compared with request volume'],
    ],
    'pie' => [
        'element' => PieChart::class,
        'attributes' => ['segments' => $segments, 'style' => ['segment' => ['gap' => 2, 'cornerRadius' => 4]], 'a11y-label' => 'Revenue share by channel'],
    ],
    'donut' => [
        'element' => DonutChart::class,
        'attributes' => ['segments' => $segments, 'inner-radius-ratio' => 0.62, 'a11y-label' => 'Spending by category'],
    ],
    'radar' => [
        'element' => RadarChart::class,
        'attributes' => [
            'axes' => [
                ['id' => 'speed', 'label' => 'Speed', 'maximum' => 100],
                ['id' => 'quality', 'label' => 'Quality', 'maximum' => 10],
                ['id' => 'cost', 'label' => 'Cost', 'maximum' => 500],
            ],
            'series' => [[
                'id' => 'nativephp', 'name' => 'NativePHP', 'color' => '#ED3F16',
                'values' => [
                    ['axis' => 'speed', 'value' => 88],
                    ['axis' => 'quality', 'value' => 9],
                    ['axis' => 'cost', 'value' => 220],
                ],
            ]],
            'grid-levels' => 4,
            'fill-opacity' => 0.3,
            'a11y-label' => 'Platform capability profile',
        ],
    ],
    'candlestick' => [
        'element' => CandlestickChart::class,
        'attributes' => [
            'x-axis' => ['type' => 'date'],
            'series' => [[
                'id' => 'nio-usd', 'name' => 'NIO/USD', 'color' => '#2563EB',
                'points' => [[
                    'id' => '2026-09-16', 'label' => '16 Sep', 'x' => '2026-09-16',
                    'open' => 36.72, 'high' => 36.91, 'low' => 36.68, 'close' => 36.84,
                ]],
            ]],
            'a11y-label' => 'Daily NIO to USD price range',
        ],
    ],
    'progress' => [
        'element' => ProgressChart::class,
        'attributes' => [
            'metrics' => [
                ['id' => 'build', 'label' => 'Build', 'value' => 0.92, 'color' => '#ED3F16'],
                ['id' => 'tests', 'label' => 'Tests', 'value' => 0.78, 'color' => '#2563EB'],
            ],
            'center-label' => 'Release readiness',
            'a11y-label' => 'Release readiness by gate',
        ],
    ],
    'contribution_heatmap' => [
        'element' => ContributionHeatmap::class,
        'attributes' => [
            'values' => [
                ['id' => '2026-09-15', 'date' => '2026-09-15', 'value' => 8, 'label' => 'Eight contributions'],
                ['id' => '2026-09-16', 'date' => '2026-09-16', 'value' => 12, 'label' => 'Twelve contributions'],
            ],
            'end-date' => '2026-09-16',
            'days' => 180,
            'week-starts-on' => 1,
            'colors' => ['#FFD0BE', '#FF8A5C', '#ED3F16', '#8F2108'],
            'empty-color' => '#ECEAE7',
            'show-month-labels' => true,
            'show-weekday-labels' => true,
            'a11y-label' => 'Daily contributions for the last 180 days',
        ],
    ],
];
