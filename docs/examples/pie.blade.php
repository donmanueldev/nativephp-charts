<native:pie-chart
    class="w-full h-80"
    :segments="[
        ['id' => 'web', 'label' => 'Web', 'value' => 68, 'color' => '#ED3F16'],
        ['id' => 'store', 'label' => 'Store', 'value' => 32, 'color' => '#2563EB'],
    ]"
    :style="['segment' => ['gap' => 2, 'cornerRadius' => 4]]"
    a11y-label="Revenue share by channel"
/>
