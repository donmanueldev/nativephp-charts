---
title: Installation
description: Install the plugin and render your first chart in a NativePHP Mobile screen.
sidebar:
  order: 2
---

## Requirements

Start with an existing NativePHP Mobile application and a working native development environment.

| Dependency | Required version |
| --- | --- |
| PHP | 8.4 or newer within PHP 8 |
| NativePHP Mobile | 4.x |
| iOS deployment target | 18.2 or newer |
| Android minimum SDK | API 26 or newer |

Charts render inside NativePHP Mobile native screens. NativePHP Desktop and browser or WebView rendering are not supported.

## Install and register

Run these commands from your application's root directory:

```bash
composer require donmanueldev/nativephp-charts
php artisan vendor:publish --tag=nativephp-plugins-provider --no-interaction
php artisan native:plugin:register donmanueldev/nativephp-charts --no-interaction
php artisan native:plugin:list
php artisan native:plugin:validate
```

Publishing creates `app/Providers/NativeServiceProvider.php` if it does not already exist. Registration adds `NativePHPChartsServiceProvider::class` to that provider's `plugins()` list so the native build includes the renderers. Composer installation alone does not enable the plugin.

Confirm that `native:plugin:list` shows `donmanueldev/nativephp-charts` as registered, and resolve any validation errors before building.

## Create a native screen

Create `app/NativeComponents/SalesDashboard.php`:

```php
<?php

namespace App\NativeComponents;

use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

final class SalesDashboard extends NativeComponent
{
    public function render(): View
    {
        return view('native.sales-dashboard');
    }
}
```

Add a route in `routes/web.php`:

```php
use App\NativeComponents\SalesDashboard;
use Illuminate\Support\Facades\Route;

Route::native('/sales', SalesDashboard::class);
```

## Add the chart

Create `resources/views/native/sales-dashboard.blade.php`:

```blade
<native:column class="w-full h-full gap-4 p-4 safe-area">
    <native:text class="text-2xl font-bold">Monthly revenue</native:text>

    <native:line-chart
        class="w-full h-80"
        :series="[[
            'id' => 'revenue',
            'name' => 'Revenue',
            'color' => '#ED3F16',
            'points' => [
                ['id' => 'jan', 'label' => 'Jan', 'value' => 18],
                ['id' => 'feb', 'label' => 'Feb', 'value' => 26],
                ['id' => 'mar', 'label' => 'Mar', 'value' => 31],
            ],
        ]]"
        empty-label="No revenue recorded"
        a11y-label="Revenue from January to March"
    />
</native:column>
```

Give the chart an explicit height. Keep series and point IDs stable when updating values so a selection can still identify the same item.

## Build and open the screen

Run the command for your target platform:

| Target | Command |
| --- | --- |
| iOS | `php artisan native:run ios --start-url=/sales` |
| Android | `php artisan native:run android --start-url=/sales` |

Rebuild after installing or updating the package: hot reload cannot add or replace compiled native renderer code. The screen should display three revenue points.

If the chart does not appear, check its height, plugin registration, and build output. See [troubleshooting](/nativephp-charts/guides/troubleshooting/) for the next steps, or add a [selection callback](/nativephp-charts/guides/callbacks-interactions/) to the example.
