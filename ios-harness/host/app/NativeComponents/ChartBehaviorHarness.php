<?php

namespace App\NativeComponents;

use Donmanueldev\NativephpCharts\PointSelection;
use Donmanueldev\NativephpCharts\ViewportChange;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

class ChartBehaviorHarness extends NativeComponent
{
    /** @var list<array{id: string, name: string, points: list<array{id: string, x: int, label: string, value: int}>}> */
    public array $series = [];

    /** @var array<string, bool|string> */
    public array $interaction = [];

    /** @var array<string, bool|int> */
    public array $viewport = [];

    public bool $chartVisible = true;

    public int $selectionCallbacks = 0;

    public int $viewportCallbacks = 0;

    public string $selectionResult = 'Selection callbacks: 0';

    public string $viewportResult = 'Viewport callbacks: 0';

    public function mount(): void
    {
        $this->restore();
    }

    public function useTap(): void
    {
        $this->interaction = $this->tapInteraction();
        $this->viewport = [];
        $this->resetCallbackEvidence();
    }

    public function useScrub(): void
    {
        $this->interaction = [
            'enabled' => true,
            'mode' => 'scrub',
            'tooltip' => 'single',
            'crosshair' => 'both',
        ];
        $this->viewport = [];
        $this->resetCallbackEvidence();
    }

    public function useViewport(): void
    {
        $this->interaction = ['enabled' => false];
        $this->viewport = [
            'enabled' => true,
            'minimum' => 1,
            'maximum' => 3,
            'pan' => true,
            'zoom' => true,
            'minimum_span' => 0.5,
        ];
        $this->resetCallbackEvidence();
    }

    public function mutateStable(): void
    {
        $this->series = [[
            'id' => 'revenue',
            'name' => 'Revenue',
            'points' => [
                $this->point('jun', 5, 'Jun', 6),
                $this->point('may', 4, 'May', 5),
                $this->point('mar', 2, 'Mar', 9),
                $this->point('feb', 1, 'Feb', 2),
                $this->point('jan', 0, 'Jan', 1),
                $this->point('apr', 3, 'Apr', 4),
            ],
        ]];
    }

    public function deleteSelected(): void
    {
        $this->series = [[
            'id' => 'revenue',
            'name' => 'Revenue',
            'points' => array_values(array_filter(
                $this->series[0]['points'] ?? [],
                fn (array $point): bool => $point['id'] !== 'mar',
            )),
        ]];
    }

    public function emptyDataset(): void
    {
        $this->series = [];
    }

    public function toggleChart(): void
    {
        $this->chartVisible = ! $this->chartVisible;
    }

    public function openGallery(): void
    {
        $this->navigate('/gallery');
    }

    public function openPerformanceHarness(): void
    {
        $this->navigate('/performance');
    }

    public function restore(): void
    {
        $this->series = [[
            'id' => 'revenue',
            'name' => 'Revenue',
            'points' => [
                $this->point('jan', 0, 'Jan', 1),
                $this->point('feb', 1, 'Feb', 2),
                $this->point('mar', 2, 'Mar', 3),
                $this->point('apr', 3, 'Apr', 4),
                $this->point('may', 4, 'May', 5),
            ],
        ]];
        $this->interaction = $this->tapInteraction();
        $this->viewport = [];
        $this->chartVisible = true;
        $this->resetCallbackEvidence();
    }

    public function pointSelected(string $payload): void
    {
        $selection = PointSelection::fromJson($payload);
        $this->selectionCallbacks++;
        $this->selectionResult = "Selection callbacks: {$this->selectionCallbacks}; {$selection->seriesId}; {$selection->pointId}; {$selection->label}; {$selection->value}";
    }

    public function viewportChanged(string $payload): void
    {
        $viewport = ViewportChange::fromJson($payload);
        $this->viewportCallbacks++;
        $this->viewportResult = "Viewport callbacks: {$this->viewportCallbacks}; {$viewport->reason}; {$viewport->minimum}; {$viewport->maximum}";
    }

    public function render(): View
    {
        return view('native.chart-behavior-harness');
    }

    /** @return array{enabled: bool, mode: string, tooltip: string, crosshair: string} */
    private function tapInteraction(): array
    {
        return [
            'enabled' => true,
            'mode' => 'tap',
            'tooltip' => 'single',
            'crosshair' => 'both',
        ];
    }

    private function resetCallbackEvidence(): void
    {
        $this->selectionCallbacks = 0;
        $this->viewportCallbacks = 0;
        $this->selectionResult = 'Selection callbacks: 0';
        $this->viewportResult = 'Viewport callbacks: 0';
    }

    /** @return array{id: string, x: int, label: string, value: int} */
    private function point(string $id, int $x, string $label, int $value): array
    {
        return compact('id', 'x', 'label', 'value');
    }
}
