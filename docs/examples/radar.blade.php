<native:radar-chart
    class="w-full h-80"
    :axes="[
        ['id' => 'speed', 'label' => 'Speed', 'maximum' => 100],
        ['id' => 'quality', 'label' => 'Quality', 'maximum' => 10],
        ['id' => 'cost', 'label' => 'Cost', 'maximum' => 500],
    ]"
    :series="[
        [
            'id' => 'nativephp',
            'name' => 'NativePHP',
            'color' => '#ED3F16',
            'values' => [
                ['axis' => 'speed', 'value' => 88],
                ['axis' => 'quality', 'value' => 9],
                ['axis' => 'cost', 'value' => 220],
            ],
        ],
    ]"
    :grid-levels="4"
    :fill-opacity="0.3"
    a11y-label="Platform capability profile"
/>
