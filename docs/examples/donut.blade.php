<native:donut-chart
    class="w-full h-80"
    :segments="[
        ['id' => 'web', 'label' => 'Web', 'value' => 68, 'color' => '#ED3F16'],
        ['id' => 'store', 'label' => 'Store', 'value' => 32, 'color' => '#2563EB'],
    ]"
    :inner-radius-ratio="0.62"
    a11y-label="Spending by category"
/>
