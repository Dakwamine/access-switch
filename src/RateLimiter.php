<?php

declare(strict_types=1);

namespace AccessSwitch;

final class RateLimiter
{
    /** @var array<string, list<int>> */
    private static array $attempts = [];

    public function __construct(
        private readonly ?string $storeDir = null,
    ) {
    }

    public function isBlocked(string $key, int $maxAttempts, int $windowSeconds, ?int $now = null): bool
    {
        if ($maxAttempts <= 0 || $windowSeconds <= 0) {
            return false;
        }

        return count($this->readTimestamps($key, $windowSeconds, $now ?? time())) >= $maxAttempts;
    }

    public function recordAttempt(string $key, int $windowSeconds, ?int $now = null): void
    {
        if ($windowSeconds <= 0) {
            return;
        }

        $now ??= time();

        if ($this->storeDir !== null) {
            $this->recordAttemptFile($key, $windowSeconds, $now);

            return;
        }

        $this->recordAttemptMemory($key, $windowSeconds, $now);
    }

    /** @deprecated Use isBlocked() + recordAttempt(). Kept for unit tests. */
    public function isAllowed(string $key, int $maxAttempts, int $windowSeconds, ?int $now = null): bool
    {
        if ($this->isBlocked($key, $maxAttempts, $windowSeconds, $now)) {
            return false;
        }

        $this->recordAttempt($key, $windowSeconds, $now);

        return true;
    }

    /** Visible for tests only. */
    public static function reset(?string $storeDir = null): void
    {
        self::$attempts = [];

        if ($storeDir === null || !is_dir($storeDir)) {
            return;
        }

        foreach (scandir($storeDir) ?: [] as $entry) {
            if (str_ends_with($entry, '.json')) {
                @unlink($storeDir . '/' . $entry);
            }
        }
    }

    /** @return list<int> */
    private function readTimestamps(string $key, int $windowSeconds, int $now): array
    {
        if ($this->storeDir !== null) {
            return $this->readTimestampsFile($key, $windowSeconds, $now);
        }

        $cutoff = $now - $windowSeconds;
        $timestamps = self::$attempts[$key] ?? [];

        return array_values(array_filter($timestamps, static fn (int $t): bool => $t > $cutoff));
    }

    private function recordAttemptMemory(string $key, int $windowSeconds, int $now): void
    {
        $timestamps = $this->readTimestamps($key, $windowSeconds, $now);
        $timestamps[] = $now;
        self::$attempts[$key] = $timestamps;
    }

    /** @return list<int> */
    private function readTimestampsFile(string $key, int $windowSeconds, int $now): array
    {
        $dir = $this->storeDir;
        if ($dir === null) {
            return [];
        }

        $path = $dir . '/' . hash('sha256', $key) . '.json';
        if (!is_file($path)) {
            return [];
        }

        $raw = @file_get_contents($path);
        $timestamps = is_string($raw) && $raw !== '' ? json_decode($raw, true) : [];
        if (!is_array($timestamps)) {
            return [];
        }

        $cutoff = $now - $windowSeconds;

        return array_values(array_filter(
            $timestamps,
            static fn (mixed $t): bool => is_int($t) && $t > $cutoff,
        ));
    }

    private function recordAttemptFile(string $key, int $windowSeconds, int $now): void
    {
        $dir = $this->storeDir;
        if ($dir === null) {
            return;
        }

        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return;
        }

        $path = $dir . '/' . hash('sha256', $key) . '.json';
        $handle = @fopen($path, 'c+');
        if ($handle === false) {
            return;
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                return;
            }

            $cutoff = $now - $windowSeconds;
            $raw = stream_get_contents($handle);
            $timestamps = is_string($raw) && $raw !== '' ? json_decode($raw, true) : [];
            if (!is_array($timestamps)) {
                $timestamps = [];
            }

            $timestamps = array_values(array_filter(
                $timestamps,
                static fn (mixed $t): bool => is_int($t) && $t > $cutoff,
            ));
            $timestamps[] = $now;

            $payload = (string) json_encode($timestamps, JSON_THROW_ON_ERROR);
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, $payload);
            fflush($handle);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
