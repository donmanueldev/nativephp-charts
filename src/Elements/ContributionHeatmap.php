<?php

namespace Donmanueldev\NativephpCharts\Elements;

use Donmanueldev\NativephpCharts\Support\ColorNormalizer;
use Donmanueldev\NativephpCharts\Support\ContributionNormalizer;
use Donmanueldev\NativephpCharts\Support\WireEncoder;
use InvalidArgumentException;
use Native\Mobile\Edge\CallbackRegistry;

class ContributionHeatmap extends ChartElement
{
    protected string $type = 'contribution_heatmap';

    /** @var list<array<string, mixed>> */
    private array $values = [];
    private string $endDate = '';
    private int $days = 365;
    private int $weekStartsOn = 1;
    /** @var list<string> */
    private array $colors = ['#DBEAFE', '#93C5FD', '#3B82F6', '#1D4ED8'];
    private string $emptyColor = '#E2E8F0';
    private bool $showMonthLabels = true;
    private bool $showWeekdayLabels = true;

    public function applyAttributes(array $attrs): void
    {
        $this->applyCommonAttributes($attrs);
        $this->applyArrayAttributes($attrs, ['values'], 'values');
        $this->applyStringAttributes($attrs, ['end-date', 'endDate'], 'endDate');
        $this->applyIntegerAttributes($attrs, ['days'], 'days');
        $this->applyIntegerAttributes($attrs, ['week-starts-on', 'weekStartsOn'], 'weekStartsOn');
        $this->applyArrayAttributes($attrs, ['colors'], 'colors');
        $this->applyStringAttributes($attrs, ['empty-color', 'emptyColor'], 'emptyColor');
        $this->applyBooleanAttributes($attrs, ['show-month-labels', 'showMonthLabels'], 'showMonthLabels');
        $this->applyBooleanAttributes($attrs, ['show-weekday-labels', 'showWeekdayLabels'], 'showWeekdayLabels');
    }

    public function values(array $values): static
    {
        $this->values = ContributionNormalizer::normalize($values);

        return $this;
    }

    public function endDate(string $date): static
    {
        $this->endDate = ContributionNormalizer::date($date, 'end date');

        return $this;
    }

    public function days(int $days): static
    {
        if ($days < 7 || $days > 371) {
            throw new InvalidArgumentException('The contribution heatmap days must be between 7 and 371.');
        }
        $this->days = $days;

        return $this;
    }

    public function weekStartsOn(int $day): static
    {
        if ($day < 0 || $day > 6) {
            throw new InvalidArgumentException('The contribution heatmap week start must be between 0 and 6.');
        }
        $this->weekStartsOn = $day;

        return $this;
    }

    public function colors(array $colors): static
    {
        if (! array_is_list($colors) || count($colors) < 2 || count($colors) > 9) {
            throw new InvalidArgumentException('The contribution heatmap colors must be a list of 2 to 9 colors.');
        }
        $this->colors = array_map(
            fn (mixed $color): string => ColorNormalizer::normalize($color, $this->chartName(), 'color scale'),
            $colors,
        );

        return $this;
    }

    public function emptyColor(string $color): static
    {
        $this->emptyColor = ColorNormalizer::normalize($color, $this->chartName(), 'empty color');

        return $this;
    }

    public function showMonthLabels(bool $show): static
    {
        $this->showMonthLabels = $show;

        return $this;
    }

    public function showWeekdayLabels(bool $show): static
    {
        $this->showWeekdayLabels = $show;

        return $this;
    }

    protected function chartType(): string
    {
        return 'contribution_heatmap';
    }

    protected function resolveProps(CallbackRegistry $registry): array
    {
        return [
            ...$this->resolveCommonProps($registry),
            'values_json' => WireEncoder::encode($this->values, $this->chartName()),
            'end_date' => $this->endDate,
            'days' => $this->days,
            'week_starts_on' => $this->weekStartsOn,
            'colors_json' => WireEncoder::encode($this->colors, $this->chartName()),
            'empty_color' => $this->emptyColor,
            'show_month_labels' => $this->showMonthLabels,
            'show_weekday_labels' => $this->showWeekdayLabels,
        ];
    }
}
