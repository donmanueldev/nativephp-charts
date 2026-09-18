<native:contribution-heatmap
    class="w-full h-80"
    :values="[
        ['id' => '2026-09-15', 'date' => '2026-09-15', 'value' => 8, 'label' => 'Eight contributions'],
        ['id' => '2026-09-16', 'date' => '2026-09-16', 'value' => 12, 'label' => 'Twelve contributions'],
    ]"
    end-date="2026-09-16"
    :days="180"
    :week-starts-on="1"
    :colors="['#FFD0BE', '#FF8A5C', '#ED3F16', '#8F2108']"
    empty-color="#ECEAE7"
    :show-month-labels="true"
    :show-weekday-labels="true"
    a11y-label="Daily contributions for the last 180 days"
/>
