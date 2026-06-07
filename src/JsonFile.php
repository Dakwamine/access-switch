<?php

declare(strict_types=1);

namespace AccessSwitch;

use RuntimeException;

final class JsonFile
{
    /**
     * @param  array<string, mixed> $defaultIfMissing
     * @return array<string, mixed>
     */
    public static function read(string $path, array $defaultIfMissing): array
    {
        if (!is_file($path)) {
            return $defaultIfMissing;
        }

        $raw = @file_get_contents($path);
        if ($raw === false) {
            throw new RuntimeException('Cannot read file');
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return $defaultIfMissing;
        }

        return $decoded;
    }

    /** @param array<mixed> $data */
    public static function write(string $path, array $data): void
    {
        $payload = json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . "\n";
        self::writeRaw($path, $payload);
    }

    private static function writeRaw(string $path, string $payload): void
    {
        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Cannot create directory');
        }

        $handle = @fopen($path, 'c+');
        if ($handle === false) {
            throw new RuntimeException('Cannot open file');
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException('Cannot lock file');
            }
            ftruncate($handle, 0);
            rewind($handle);
            $written = fwrite($handle, $payload);
            fflush($handle);
            if ($written === false) {
                throw new RuntimeException('Cannot write file');
            }
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
