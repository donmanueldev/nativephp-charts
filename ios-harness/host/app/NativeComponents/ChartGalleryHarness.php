<?php

namespace App\NativeComponents;

use Donmanueldev\NativephpCharts\PointSelection;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

class ChartGalleryHarness extends NativeComponent
{
    /** @var list<string> */
    public array $chartTypes = [
        'line',
        'area',
        'bar',
        'scatter',
        'pie',
        'donut',
        'radar',
        'candlestick',
        'progress',
        'contribution_heatmap',
    ];

    public int $selectedChartIndex = 0;

    public string $selectionResult = 'No gallery selection';

    public int $selectionCallbacks = 0;

    public function previousChart(): void
    {
        $count = count($this->chartTypes);
        $this->selectedChartIndex = ($this->selectedChartIndex + $count - 1) % $count;
        $this->resetSelection();
    }

    public function nextChart(): void
    {
        $this->selectedChartIndex = ($this->selectedChartIndex + 1) % count($this->chartTypes);
        $this->resetSelection();
    }

    public function pointSelected(string $payload): void
    {
        $selection = PointSelection::fromJson($payload);
        $this->selectionCallbacks++;
        $this->selectionResult = "Gallery selection {$this->selectionCallbacks}: {$selection->chartType}; {$selection->seriesId}; {$selection->pointId}; {$selection->label}";
    }

    public function closeGallery(): void
    {
        $this->back();
    }

    public function render(): View
    {
        return view('native.chart-gallery-harness', [
            'chartType' => $this->chartTypes[$this->selectedChartIndex],
            'cartesianSeries' => $this->cartesianSeries(),
            'segments' => $this->segments(),
            'radarAxes' => $this->radarAxes(),
            'radarSeries' => $this->radarSeries(),
            'candlestickSeries' => $this->candlestickSeries(),
            'metrics' => $this->metrics(),
            'contributions' => $this->contributions(),
        ]);
    }

    /** @return list<array{id: string, name: string, color: string, points: list<array{id: string, label: string, x: int, value: int}>}> */
    private function cartesianSeries(): array
    {
        return [[
            'id' => 'revenue',
            'name' => 'Revenue',
            'color' => '#ED3F16',
            'points' => [
                ['id' => 'jan', 'label' => 'Jan', 'x' => 0, 'value' => 18],
                ['id' => 'feb', 'label' => 'Feb', 'x' => 1, 'value' => 26],
                ['id' => 'mar', 'label' => 'Mar', 'x' => 2, 'value' => 22],
                ['id' => 'apr', 'label' => 'Apr', 'x' => 3, 'value' => 35],
                ['id' => 'may', 'label' => 'May', 'x' => 4, 'value' => 31],
                ['id' => 'jun', 'label' => 'Jun', 'x' => 5, 'value' => 42],
            ],
        ]];
    }

    /** @return list<array{id: string, label: string, value: int, color: string}> */
    private function segments(): array
    {
        return [
            ['id' => 'advisors', 'label' => 'Advisors', 'value' => 33, 'color' => '#00C982'],
            ['id' => 'search', 'label' => 'Search', 'value' => 23, 'color' => '#36A9E1'],
            ['id' => 'partners', 'label' => 'Partners', 'value' => 18, 'color' => '#F9C642'],
            ['id' => 'events', 'label' => 'Events', 'value' => 14, 'color' => '#8E75E8'],
            ['id' => 'direct', 'label' => 'Direct', 'value' => 12, 'color' => '#FF4254'],
        ];
    }

    /** @return list<array{id: string, label: string, maximum: int}> */
    private function radarAxes(): array
    {
        return [
            ['id' => 'speed', 'label' => 'Speed', 'maximum' => 100],
            ['id' => 'quality', 'label' => 'Quality', 'maximum' => 100],
            ['id' => 'stability', 'label' => 'Stability', 'maximum' => 100],
            ['id' => 'accessibility', 'label' => 'A11y', 'maximum' => 100],
            ['id' => 'customization', 'label' => 'Custom', 'maximum' => 100],
        ];
    }

    /** @return list<array{id: string, name: string, color: string, values: list<array{axis: string, value: int}>}> */
    private function radarSeries(): array
    {
        return [[
            'id' => 'nativephp',
            'name' => 'NativePHP',
            'color' => '#ED3F16',
            'values' => [
                ['axis' => 'speed', 'value' => 88],
                ['axis' => 'quality', 'value' => 92],
                ['axis' => 'stability', 'value' => 84],
                ['axis' => 'accessibility', 'value' => 78],
                ['axis' => 'customization', 'value' => 90],
            ],
        ]];
    }

    /** @return list<array{id: string, name: string, points: list<array{id: string, label: string, x: string, open: float, high: float, low: float, close: float}>}> */
    private function candlestickSeries(): array
    {
        return [[
            'id' => 'nio-usd',
            'name' => 'NIO/USD',
            'points' => [
                ['id' => 'sep-12', 'label' => '12 Sep', 'x' => '2026-09-12', 'open' => 36.70, 'high' => 36.86, 'low' => 36.66, 'close' => 36.82],
                ['id' => 'sep-13', 'label' => '13 Sep', 'x' => '2026-09-13', 'open' => 36.82, 'high' => 36.90, 'low' => 36.71, 'close' => 36.75],
                ['id' => 'sep-14', 'label' => '14 Sep', 'x' => '2026-09-14', 'open' => 36.75, 'high' => 36.93, 'low' => 36.72, 'close' => 36.89],
                ['id' => 'sep-15', 'label' => '15 Sep', 'x' => '2026-09-15', 'open' => 36.89, 'high' => 36.95, 'low' => 36.76, 'close' => 36.80],
                ['id' => 'sep-16', 'label' => '16 Sep', 'x' => '2026-09-16', 'open' => 36.80, 'high' => 36.98, 'low' => 36.77, 'close' => 36.94],
            ],
        ]];
    }

    /** @return list<array{id: string, label: string, value: float, color: string}> */
    private function metrics(): array
    {
        return [
            ['id' => 'build', 'label' => 'Build', 'value' => 0.92, 'color' => '#ED3F16'],
            ['id' => 'tests', 'label' => 'Tests', 'value' => 0.78, 'color' => '#2563EB'],
            ['id' => 'docs', 'label' => 'Docs', 'value' => 0.64, 'color' => '#00A878'],
        ];
    }

    /** @return list<array{id: string, date: string, value: int, label: string}> */
    private function contributions(): array
    {
        $values = [];
        $end = new \DateTimeImmutable('2026-09-16');

        for ($offset = 179; $offset >= 0; $offset--) {
            $date = $end->modify("-{$offset} days")->format('Y-m-d');
            $value = ($offset * 7 + 3) % 13;
            $values[] = [
                'id' => $date,
                'date' => $date,
                'value' => $value,
                'label' => "{$value} contributions",
            ];
        }

        return $values;
    }

    private function resetSelection(): void
    {
        $this->selectionCallbacks = 0;
        $this->selectionResult = 'No gallery selection';
    }
}
