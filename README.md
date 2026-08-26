# NativePHPCharts Plugin for NativePHP Mobile

Native chart components for NativePHP Mobile, rendered with SwiftUI and Jetpack Compose.

## Installation

```bash
composer require donmanueldev/nativephp-charts
```

## Usage

```php
use Donmanueldev\NativephpCharts\Facades\NativePHPCharts;

// Execute functionality
$result = NativePHPCharts::execute(['option1' => 'value']);

// Get status
$status = NativePHPCharts::getStatus();
```

## Listening for Events

```php
use Livewire\Attributes\On;

#[On('native:Donmanueldev\NativephpCharts\Events\NativePHPChartsCompleted')]
public function handleNativePHPChartsCompleted($result, $id = null)
{
    // Handle the event
}
```

## License

MIT