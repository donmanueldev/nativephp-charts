<?php

namespace Donmanueldev\NativephpCharts\Support;

use InvalidArgumentException;

/**
 * Chooses the inline or file-backed transport for normalized series JSON.
 *
 * Small payloads travel directly in `series_json`. Larger payloads are addressed by
 * their SHA-256 content hash, written atomically with owner-only permissions, and
 * announced with the versioned `file-v1` transport understood by native decoders.
 */
final class WirePayloadStore
{
    public const INLINE_BYTE_LIMIT = 48 * 1024;

    private const RETAINED_PAYLOADS = 64;

    /**
     * Return the wire attributes for inline JSON or a file-backed payload.
     *
     * File transport keeps the required `series_json` field present as an empty list;
     * decoders that support `file-v1` read the private path instead.
     *
     * @return array{series_json: string}|array{series_json: string, series_json_file: string, series_transport: 'file-v1'}
     *
     * @throws InvalidArgumentException When a large payload cannot be stored safely.
     */
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

    /** Resolve Laravel storage when bootstrapped, with a temp-directory fallback for isolated consumers. */
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

    /** Write a complete owner-readable payload before atomically exposing its content-addressed path. */
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

    /** Retain the newest bounded set of cached payloads without deleting the active file. */
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
