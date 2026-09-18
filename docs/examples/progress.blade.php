<native:progress-chart
    class="w-full h-80"
    :metrics="[
        ['id' => 'build', 'label' => 'Build', 'value' => 0.92, 'color' => '#ED3F16'],
        ['id' => 'tests', 'label' => 'Tests', 'value' => 0.78, 'color' => '#2563EB'],
    ]"
    center-label="Release readiness"
    a11y-label="Release readiness by gate"
/>
