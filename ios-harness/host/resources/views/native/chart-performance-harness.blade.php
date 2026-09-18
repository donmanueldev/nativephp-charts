<native:top-bar title="iOS chart performance" subtitle="Generated-shell density harness" />

<native:scroll-view ref="ios-chart-performance-screen" fill class="bg-theme-background">
    <native:column class="w-full p-4 gap-4">
        <native:text class="text-sm text-theme-on-surface-variant">This deterministic line fixture supports simulator triage and physical Instruments captures. Simulator timings are never accepted as physical baselines.</native:text>

        <native:row class="w-full gap-2">
            <native:button @tap="useOneHundredPoints">100 points</native:button>
            <native:button @tap="useOneThousandPoints">1,000 points</native:button>
            <native:button @tap="useTenThousandPoints">10,000 points</native:button>
        </native:row>

        <native:text ref="performance-density" class="text-lg font-bold">Performance density: {{ $pointCount }} points</native:text>
        <native:text ref="performance-payload" class="text-sm">Payload bytes: {{ $payloadBytes }}</native:text>
        <native:text ref="performance-callbacks" class="text-sm">{{ $selectionResult }}</native:text>

        <native:line-chart
            ref="ios-performance-chart"
            class="w-full h-96"
            :series="$series"
            :x-axis="['type' => 'number', 'labelCount' => 5]"
            :y-axis="['labelCount' => 5]"
            :interaction="['enabled' => true, 'mode' => 'tap', 'tooltip' => 'single']"
            :style="['line' => ['width' => 2, 'interpolation' => 'linear'], 'points' => ['visible' => $pointCount <= 100]]"
            :show-points="$pointCount <= 100"
            :animated="false"
            :begin-at-zero="false"
            theme="light"
            locale="en-US"
            error-label="Chart unavailable"
            a11y-label="iOS performance line chart with {{ $pointCount }} points"
            _select="pointSelected"
        />

        <native:button @tap="closePerformanceHarness" variant="secondary">Close performance harness</native:button>
    </native:column>
</native:scroll-view>
