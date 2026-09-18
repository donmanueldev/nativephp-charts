<?php

use App\NativeComponents\ChartBehaviorHarness;
use App\NativeComponents\ChartGalleryHarness;
use App\NativeComponents\ChartPerformanceHarness;
use Illuminate\Support\Facades\Route;

Route::native('/', ChartBehaviorHarness::class);
Route::native('/gallery', ChartGalleryHarness::class);
Route::native('/performance', ChartPerformanceHarness::class);
