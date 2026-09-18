<native:bar-chart
    class="w-full h-80"
    :series="[
        [
            'id' => 'revenue',
            'name' => 'Revenue',
            'color' => '#ED3F16',
            'points' => [
                ['id' => 'jan', 'label' => 'January', 'value' => 18],
                ['id' => 'feb', 'label' => 'February', 'value' => 26],
                ['id' => 'mar', 'label' => 'March', 'value' => 31],
            ],
        ],
    ]"
    mode="grouped"
    orientation="vertical"
    :style="['bar' => ['radius' => 6, 'width' => 18]]"
    a11y-label="Revenue by month"
/>
