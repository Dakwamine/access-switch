<?php

declare(strict_types=1);

namespace AccessSwitch;

final class ClientIp
{
    /**
     * @param list<string> $trustedProxies IPs or IPv4 CIDRs; headers are trusted only when REMOTE_ADDR matches
     */
    public static function resolve(
        string $remoteAddr,
        ?string $xForwardedFor,
        ?string $xRealIp,
        array $trustedProxies,
    ): string {
        if ($remoteAddr === '') {
            return '';
        }

        if (!self::isTrusted($remoteAddr, $trustedProxies)) {
            return $remoteAddr;
        }

        $realIp = self::normalizeIp($xRealIp);
        if ($realIp !== null) {
            return $realIp;
        }

        if ($xForwardedFor !== null && $xForwardedFor !== '') {
            foreach (explode(',', $xForwardedFor) as $part) {
                $ip = self::normalizeIp(trim($part));
                if ($ip !== null) {
                    return $ip;
                }
            }
        }

        return $remoteAddr;
    }

    /** @param list<string> $trustedProxies */
    private static function isTrusted(string $ip, array $trustedProxies): bool
    {
        if ($trustedProxies === []) {
            return false;
        }

        foreach ($trustedProxies as $trusted) {
            if (self::matches($ip, $trusted)) {
                return true;
            }
        }

        return false;
    }

    private static function matches(string $ip, string $trusted): bool
    {
        if (!str_contains($trusted, '/')) {
            return $ip === $trusted;
        }

        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }

        [$subnet, $bits] = explode('/', $trusted, 2);
        if (!filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) || !ctype_digit($bits)) {
            return false;
        }

        $mask = (int) $bits;
        if ($mask < 0 || $mask > 32) {
            return false;
        }

        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        if ($ipLong === false || $subnetLong === false) {
            return false;
        }

        $maskLong = $mask === 0 ? 0 : (-1 << (32 - $mask));

        return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
    }

    private static function normalizeIp(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_IP) !== false ? $value : null;
    }
}
