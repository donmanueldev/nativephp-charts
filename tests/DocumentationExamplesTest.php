<?php

use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Compilers\BladeCompiler;
use Native\Mobile\Edge\CallbackRegistry;

it('keeps every canonical documentation fixture executable against the public elements', function () {
    $fixtures = require __DIR__.'/../docs/examples/fixtures.php';

    expect($fixtures)->toHaveCount(10);
    foreach ($fixtures as $name => $fixture) {
        expect($fixture)->toHaveKeys(['element', 'attributes']);
        $element = $fixture['element']::make();
        $element->applyAttributes($fixture['attributes']);
        $props = $element->toArray(new CallbackRegistry)['props'];

        expect($props['contract_version'])->toBe(1, "The {$name} fixture must emit contract v1.")
            ->and($props['a11y_label'])->not->toBe('Chart', "The {$name} fixture needs a specific accessibility label.");
    }
});

it('compiles all canonical Blade examples', function () {
    $compiler = new BladeCompiler(new Filesystem, sys_get_temp_dir());
    $paths = glob(__DIR__.'/../docs/examples/*.blade.php') ?: [];

    expect($paths)->toHaveCount(10);
    foreach ($paths as $path) {
        $compiled = $compiler->compileString(file_get_contents($path));
        expect($compiled)->toContain('native:');
    }
});
