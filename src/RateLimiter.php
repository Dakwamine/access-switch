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

        return $this->attemptCount($key, $windowSeconds, $now) >= $maxAttempts;
    }

    public function attemptCount(string $key, int $windowSeconds, ?int $now = null): int
    {
        if ($windowSeconds <= 0) {
            return 0;
        }

        return count($this->readTimestamps($key, $windowSeconds, $now ?? time()));
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

        return $this->filterTimestamps($timestamps, $cutoff);
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

        $path = $this->filePath($dir, $key);
        if (!is_file($path)) {
            return [];
        }

        $timestamps = [];
        $this->withLockedFile($path, $dir, static function ($handle) use (&$timestamps): void {
            $raw = stream_get_contents($handle);
            $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : [];
            if (is_array($decoded)) {
                $timestamps = $decoded;
            }
        });

        return $this->filterTimestamps($timestamps, $now - $windowSeconds);
    }

    private function recordAttemptFile(string $key, int $windowSeconds, int $now): void
    {
        $dir = $this->storeDir;
        if ($dir === null) {
            return;
        }

        if (!$this->ensureStoreDir($dir)) {
            return;
        }

        $path = $this->filePath($dir, $key);
        $written = false;
        $this->withLockedFile($path, $dir, function ($handle) use ($windowSeconds, $now, &$written): void {
            $cutoff = $now - $windowSeconds;
            $raw = stream_get_contents($handle);
            $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : [];
            $timestamps = is_array($decoded) ? $this->filterTimestamps($decoded, $cutoff) : [];
            $timestamps[] = $now;

            $payload = (string) json_encode($timestamps, JSON_THROW_ON_ERROR);
            ftruncate($handle, 0);
            rewind($handle);
            $written = fwrite($handle, $payload) !== false;
            fflush($handle);
        });

        if (!$written) {
            $this->logStoreError('cannot write rate-limit file');
        }
    }

    private function ensureStoreDir(string $dir): bool
    {
        if (is_dir($dir)) {
            return true;
        }

        if (@mkdir($dir, 0755, true) || is_dir($dir)) {
            return true;
        }

        $this->logStoreError('cannot create rate-limit directory: ' . $dir);

        return false;
    }

    private function filePath(string $dir, string $key): string
    {
        return $dir . '/' . hash('sha256', $key) . '.json';
    }

    /**
     * @param callable(resource): void $callback
     */
    private function withLockedFile(string $path, string $dir, callable $callback): void
    {
        if (!$this->ensureStoreDir($dir)) {
            return;
        }

        $handle = @fopen($path, 'c+');
        if ($handle === false) {
            $this->logStoreError('cannot open rate-limit file: ' . $path);

            return;
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                $this->logStoreError('cannot lock rate-limit file: ' . $path);

                return;
            }

            $callback($handle);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /** @param list<mixed> $timestamps */
    private function filterTimestamps(array $timestamps, int $cutoff): array
    {
        $filtered = [];
        foreach ($timestamps as $timestamp) {
            $value = self::normalizeTimestamp($timestamp);
            if ($value !== null && $value > $cutoff) {
                $filtered[] = $value;
            }
        }

        return $filtered;
    }

    private static function normalizeTimestamp(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_float($value) && (float) (int) $value === $value) {
            return (int) $value;
        }

        return null;
    }

    private function logStoreError(string $message): void
    {
        error_log('access-switch rate-limit: ' . $message, 4);
    }
}
