<?php

use Donmanueldev\NativephpCharts\Support\WirePayloadStore;

it('keeps fresh payloads for ten minutes and removes only expired overflow', function () {
    $directory = sys_get_temp_dir().'/nativephp-charts-retention-'.bin2hex(random_bytes(6));
    mkdir($directory, 0700, true);
    $now = time();

    try {
        for ($index = 0; $index < 65; $index++) {
            $path = sprintf('%s/series-fresh-%02d.json', $directory, $index);
            file_put_contents($path, '[]');
            touch($path, $now - $index);
        }
        foreach (['first', 'second'] as $offset => $name) {
            $path = "{$directory}/series-expired-{$name}.json";
            file_put_contents($path, '[]');
            touch($path, $now - 700 - $offset);
        }

        $method = new ReflectionMethod(WirePayloadStore::class, 'removeOldPayloads');
        $method->invoke(null, $directory, "{$directory}/series-fresh-00.json");

        expect(glob($directory.'/series-fresh-*.json'))->toHaveCount(65)
            ->and(glob($directory.'/series-expired-*.json'))->toBe([]);
    } finally {
        foreach (glob($directory.'/*') ?: [] as $path) {
            unlink($path);
        }
        rmdir($directory);
    }
});

it('does not prune an expired cache while it remains within the retained limit', function () {
    $directory = sys_get_temp_dir().'/nativephp-charts-retention-'.bin2hex(random_bytes(6));
    mkdir($directory, 0700, true);

    try {
        for ($index = 0; $index < 64; $index++) {
            $path = sprintf('%s/series-old-%02d.json', $directory, $index);
            file_put_contents($path, '[]');
            touch($path, time() - 1_000 - $index);
        }

        $method = new ReflectionMethod(WirePayloadStore::class, 'removeOldPayloads');
        $method->invoke(null, $directory, "{$directory}/series-old-00.json");

        expect(glob($directory.'/series-old-*.json'))->toHaveCount(64);
    } finally {
        foreach (glob($directory.'/*') ?: [] as $path) {
            unlink($path);
        }
        rmdir($directory);
    }
});
