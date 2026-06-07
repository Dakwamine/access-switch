<?php

declare(strict_types=1);

namespace AccessSwitch;

final class RateLimiter
{
    /** @var array<string, list<int>> */
    private static array $attempts = [];

    public function isAllowed(string $key, int $maxAttempts, int $windowSeconds, ?int $now = null): bool
    {
        if ($maxAttempts <= 0 || $windowSeconds <= 0) {
            return true;
        }

        $now ??= time();
        $cutoff = $now - $windowSeconds;
        $timestamps = self::$attempts[$key] ?? [];
        $timestamps = array_values(array_filter($timestamps, static fn (int $t): bool => $t > $cutoff));

        if (count($timestamps) >= $maxAttempts) {
            self::$attempts[$key] = $timestamps;

            return false;
        }

        $timestamps[] = $now;
        self::$attempts[$key] = $timestamps;

        return true;
    }

    /** Visible for tests only. */
    public static function reset(): void
    {
        self::$attempts = [];
    }
}
