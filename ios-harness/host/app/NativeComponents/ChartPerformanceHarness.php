<?php

namespace App\NativeComponents;

use Donmanueldev\NativephpCharts\PointSelection;
use Donmanueldev\NativephpCharts\Support\ChartDataNormalizer;
use Donmanueldev\NativephpCharts\Support\WireEncoder;
use Illuminate\View\View;
use InvalidArgumentException;
use Native\Mobile\Edge\NativeComponent;

class ChartPerformanceHarness extends NativeComponent
{
    /** @var list<array{id: string, name: string, points: list<array{id: string, x: int, label: string, value: float}>}> */
    public array $series = [];

    public int $pointCount = 100;

    public int $payloadBytes = 0;

    public int $selectionCallbacks = 0;

    public string $selectionResult = 'Performance callbacks: 0';

    public function mount(): void
    {
        $this->loadDensity(100);
    }

    public function useOneHundredPoints(): void
    {
        $this->loadDensity(100);
    }

    public function useOneThousandPoints(): void
    {
        $this->loadDensity(1_000);
    }

    public function useTenThousandPoints(): void
    {
        $this->loadDensity(10_000);
    }

    public function pointSelected(string $payload): void
    {
        $selection = PointSelection::fromJson($payload);
        $this->selectionCallbacks++;
        $this->selectionResult = "Performance callbacks: {$this->selectionCallbacks}; {$selection->seriesId}; {$selection->pointId}";
    }

    public function closePerformanceHarness(): void
    {
        $this->back();
    }

    public function render(): View
    {
        return view('native.chart-performance-harness');
    }

    private function loadDensity(int $pointCount): void
    {
        if (! in_array($pointCount, [100, 1_000, 10_000], true)) {
            throw new InvalidArgumentException('Performance density must be 100, 1000, or 10000 points.');
        }

        $points = [];
        for ($index = 0; $index < $pointCount; $index++) {
            $points[] = [
                'id' => "point-{$index}",
                'x' => $index,
                'label' => "Sample {$index}",
                'value' => round(50 + (sin($index / 18) * 28) + ($index % 9), 3),
            ];
        }

        $series = [[
            'id' => 'performance',
            'name' => 'Samples',
            'points' => $points,
        ]];
        $this->pointCount = $pointCount;
        $this->series = ChartDataNormalizer::normalize($series, 'number', 'line chart', 'line');
        $this->payloadBytes = strlen(WireEncoder::encode($this->series, 'line chart'));
        $this->selectionCallbacks = 0;
        $this->selectionResult = 'Performance callbacks: 0';
    }
}
