<native:scatter-chart
    class="w-full h-80"
    :series="[
        [
            'id' => 'observed',
            'name' => 'Observed',
            'color' => '#ED3F16',
            'points' => [
                ['id' => 'one', 'label' => 'One', 'x' => 1, 'value' => 8],
                ['id' => 'two', 'label' => 'Two', 'x' => 2, 'value' => 13],
            ],
        ],
    ]"
    :x-axis="['type' => 'number']"
    :style="['points' => ['size' => 7]]"
    a11y-label="Response time compared with request volume"
/>
