<native:line-chart
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
    theme="system"
    preset="default"
    error-label="Revenue chart unavailable"
    a11y-label="Revenue by month"
/>
