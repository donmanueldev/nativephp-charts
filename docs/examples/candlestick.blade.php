<native:candlestick-chart
    class="w-full h-80"
    :x-axis="['type' => 'date']"
    :series="[
        [
            'id' => 'nio-usd',
            'name' => 'NIO/USD',
            'color' => '#2563EB',
            'points' => [
                [
                    'id' => '2026-09-16',
                    'label' => '16 Sep',
                    'x' => '2026-09-16',
                    'open' => 36.72,
                    'high' => 36.91,
                    'low' => 36.68,
                    'close' => 36.84,
                ],
            ],
        ],
    ]"
    a11y-label="Daily NIO to USD price range"
/>
