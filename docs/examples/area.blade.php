<native:area-chart
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
    area-mode="stacked"
    :style="['area' => ['opacity' => 0.35]]"
    a11y-label="Revenue magnitude by month"
/>
