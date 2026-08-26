## donmanueldev/nativephp-charts

Native chart components for NativePHP Mobile, rendered with SwiftUI and Jetpack Compose.

### Installation

```bash
composer require donmanueldev/nativephp-charts
```

### PHP Usage (Livewire/Blade)

Use the `NativePHPCharts` facade:

@verbatim
<code-snippet name="Using NativePHPCharts Facade" lang="php">
use Donmanueldev\NativephpCharts\Facades\NativePHPCharts;

// Execute the plugin functionality
$result = NativePHPCharts::execute(['option1' => 'value']);

// Get the current status
$status = NativePHPCharts::getStatus();
</code-snippet>
@endverbatim

### Available Methods

- `NativePHPCharts::execute()`: Execute the plugin functionality
- `NativePHPCharts::getStatus()`: Get the current status

### Events

- `NativePHPChartsCompleted`: Listen with `#[OnNative(NativePHPChartsCompleted::class)]`

@verbatim
<code-snippet name="Listening for NativePHPCharts Events" lang="php">
use Native\Mobile\Attributes\OnNative;
use Donmanueldev\NativephpCharts\Events\NativePHPChartsCompleted;

#[OnNative(NativePHPChartsCompleted::class)]
public function handleNativePHPChartsCompleted($result, $id = null)
{
    // Handle the event
}
</code-snippet>
@endverbatim

### JavaScript Usage (Vue/React/Inertia)

@verbatim
<code-snippet name="Using NativePHPCharts in JavaScript" lang="javascript">
import { nativePHPCharts } from '@donmanueldev/nativephp-charts';

// Execute the plugin functionality
const result = await nativePHPCharts.execute({ option1: 'value' });

// Get the current status
const status = await nativePHPCharts.getStatus();
</code-snippet>
@endverbatim