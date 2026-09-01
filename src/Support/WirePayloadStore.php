<?php

namespace Donmanueldev\NativephpCharts\Support;

use InvalidArgumentException;

final class WirePayloadStore
{
    public const INLINE_BYTE_LIMIT = 48 * 1024;

    private const RETAINED_PAYLOADS = 64;

    /** @return array{series_json: string}|array{series_json: string, series_json_file: string, series_transport: string} */
    public static function series(string $json, string $chartName): array
    {
        if (strlen($json) <= self::INLINE_BYTE_LIMIT) {
            return ['series_json' => $json];
        }

        $directory = self::directory();
        self::ensureDirectoryExists($directory, $chartName);

        $path = $directory.'/series-'.hash('sha256', $json).'.json';
        if (! is_file($path)) {
            self::writeAtomically($path, $json, $chartName);
            self::removeOldPayloads($directory, $path);
        }

        return [
            'series_json' => '[]',
            'series_json_file' => $path,
            'series_transport' => 'file-v1',
        ];
    }

    private static function directory(): string
    {
        if (function_exists('app') && method_exists(app(), 'storagePath')) {
            return app()->storagePath('framework/cache/nativephp-charts');
        }

        return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'nativephp-charts';
    }

    private static function ensureDirectoryExists(string $directory, string $chartName): void
    {
        if (is_dir($directory)) {
            return;
        }

        if (! @mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new InvalidArgumentException("The {$chartName} series payload could not be stored safely.");
        }
    }

    private static function writeAtomically(string $path, string $json, string $chartName): void
    {
        $temporaryPath = tempnam(dirname($path), 'writing-');
        if ($temporaryPath === false) {
            throw new InvalidArgumentException("The {$chartName} series payload could not be stored safely.");
        }

        try {
            $written = file_put_contents($temporaryPath, $json, LOCK_EX);
            if ($written !== strlen($json) || ! @chmod($temporaryPath, 0600) || ! @rename($temporaryPath, $path)) {
                throw new InvalidArgumentException("The {$chartName} series payload could not be stored safely.");
            }
        } finally {
            if (is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }
        }
    }

    private static function removeOldPayloads(string $directory, string $currentPath): void
    {
        $paths = glob($directory.'/series-*.json') ?: [];
        if (count($paths) <= self::RETAINED_PAYLOADS) {
            return;
        }

        usort($paths, static fn (string $left, string $right): int => filemtime($right) <=> filemtime($left));

        foreach (array_slice($paths, self::RETAINED_PAYLOADS) as $path) {
            if ($path !== $currentPath) {
                @unlink($path);
            }
        }
    }
}
