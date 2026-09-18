<native:top-bar title="Native chart gallery" subtitle="Fresh generated-shell evidence" />

<native:scroll-view ref="ios-chart-gallery-screen" fill class="bg-theme-background">
    <native:column class="w-full p-4 gap-3">
        <native:row class="w-full gap-2">
            <native:button @tap="previousChart" variant="secondary">Previous chart</native:button>
            <native:button @tap="nextChart" variant="secondary">Next chart</native:button>
        </native:row>

        <native:text ref="gallery-chart-name" class="text-xl font-bold text-theme-on-surface">{{ str_replace('_', ' ', strtoupper($chartType)) }}</native:text>

        <native:column class="w-full border-y border-theme-outline bg-theme-surface py-2">
            @if ($chartType === 'line')
                <native:line-chart class="w-full h-96" :series="$cartesianSeries" :x-axis="['type' => 'number']" :interaction="['enabled' => true, 'mode' => 'tap', 'tooltip' => 'single']" :animated="false" theme="system" _select="pointSelected" a11y-label="iOS gallery line chart" />
            @elseif ($chartType === 'area')
                <native:area-chart class="w-full h-96" :series="$cartesianSeries" :x-axis="['type' => 'number']" area-mode="stacked" :interaction="['enabled' => true, 'mode' => 'tap', 'tooltip' => 'single']" :animated="false" theme="system" _select="pointSelected" a11y-label="iOS gallery area chart" />
            @elseif ($chartType === 'bar')
                <native:bar-chart class="w-full h-96" :series="$cartesianSeries" :x-axis="['type' => 'number']" mode="grouped" orientation="vertical" :interaction="['enabled' => true, 'mode' => 'tap', 'tooltip' => 'single']" :animated="false" theme="system" _select="pointSelected" a11y-label="iOS gallery bar chart" />
            @elseif ($chartType === 'scatter')
                <native:scatter-chart class="w-full h-96" :series="$cartesianSeries" :x-axis="['type' => 'number']" :interaction="['enabled' => true, 'mode' => 'tap', 'tooltip' => 'single']" :animated="false" theme="system" _select="pointSelected" a11y-label="iOS gallery scatter chart" />
            @elseif ($chartType === 'pie')
                <native:pie-chart class="w-full h-96" :segments="$segments" :legend="['visible' => true]" :animated="false" theme="system" _select="pointSelected" a11y-label="iOS gallery pie chart" />
            @elseif ($chartType === 'donut')
                <native:donut-chart class="w-full h-96" :segments="$segments" :inner-radius-ratio="0.62" :legend="['visible' => true]" :animated="false" theme="system" _select="pointSelected" a11y-label="iOS gallery donut chart" />
            @elseif ($chartType === 'radar')
                <native:radar-chart class="w-full h-96" :axes="$radarAxes" :series="$radarSeries" :grid-levels="4" :fill-opacity="0.3" :animated="false" theme="system" _select="pointSelected" a11y-label="iOS gallery radar chart" />
            @elseif ($chartType === 'candlestick')
                <native:candlestick-chart class="w-full h-96" :series="$candlestickSeries" :x-axis="['type' => 'date']" :interaction="['enabled' => true, 'mode' => 'tap', 'tooltip' => 'single']" :animated="false" theme="system" locale="en-US" value-format="currency" currency-code="USD" _select="pointSelected" a11y-label="iOS gallery candlestick chart" />
            @elseif ($chartType === 'progress')
                <native:progress-chart class="w-full h-96" :metrics="$metrics" center-label="Release readiness" :legend="['visible' => true]" :animated="false" theme="system" _select="pointSelected" a11y-label="iOS gallery progress chart" />
            @else
                <native:contribution-heatmap class="w-full h-96" :values="$contributions" end-date="2026-09-16" :days="180" :week-starts-on="1" :colors="['#FFD0BE', '#FF8A5C', '#ED3F16', '#8F2108']" empty-color="#ECEAE7" :show-month-labels="true" :show-weekday-labels="true" :animated="false" theme="system" _select="pointSelected" a11y-label="iOS gallery contribution heatmap chart" />
            @endif
        </native:column>

        <native:text ref="gallery-selection-result" class="text-sm font-semibold text-theme-primary">{{ $selectionResult }}</native:text>
        <native:button @tap="closeGallery" variant="secondary">Close gallery</native:button>
    </native:column>
</native:scroll-view>
