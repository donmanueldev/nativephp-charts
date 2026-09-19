<native:top-bar title="iOS chart behavior" subtitle="Generated-shell acceptance harness" />

<native:scroll-view ref="ios-chart-behavior-screen" class="w-full h-full bg-theme-background">
    <native:column class="w-full p-4 gap-4">
        <native:text class="text-sm text-theme-on-surface-variant">The controls mutate one real NativePHP chart while XCTest observes native semantics and callback counts.</native:text>
        <native:button @tap="openGallery" variant="secondary">Open chart gallery</native:button>

        @if ($chartVisible)
            <native:line-chart
                ref="ios-behavior-chart"
                class="w-full h-80"
                :series="$series"
                :x-axis="['type' => 'number', 'labelCount' => 5]"
                :y-axis="['labelCount' => 5]"
                :interaction="$interaction"
                :viewport="$viewport"
                :style="['line' => ['width' => 3], 'points' => ['size' => 7]]"
                :animated="false"
                :begin-at-zero="false"
                empty-label="No data"
                error-label="Chart unavailable"
                a11y-label="iOS behavior chart"
                _select="pointSelected"
                _viewport_change="viewportChanged"
            />
        @else
            <native:text>Chart closed</native:text>
        @endif

        <native:text ref="selection-result" class="text-sm font-semibold text-theme-primary">{{ $selectionResult }}</native:text>
        <native:text ref="viewport-result" class="text-sm font-semibold text-theme-primary">{{ $viewportResult }}</native:text>

        <native:row class="w-full gap-2">
            <native:button @tap="useTap">Use tap</native:button>
            <native:button @tap="useScrub">Use scrub</native:button>
            <native:button @tap="useViewport">Use viewport</native:button>
        </native:row>

        <native:button @tap="mutateStable">Insert reorder and update</native:button>
        <native:button @tap="deleteSelected">Delete selected point</native:button>
        <native:button @tap="emptyDataset">Empty dataset</native:button>
        <native:button @tap="toggleChart">Toggle chart</native:button>
        <native:button @tap="restore">Restore fixture</native:button>
        <native:button @tap="openPerformanceHarness" variant="secondary">Open performance harness</native:button>

        <native:spacer class="h-96" />
        <native:text ref="scroll-sentinel" class="text-sm font-semibold">Scroll sentinel</native:text>
    </native:column>
</native:scroll-view>
