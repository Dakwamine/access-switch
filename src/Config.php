<?php

declare(strict_types=1);

namespace AccessSwitch;

final class Config
{
    /**
     * @param list<string> $authorizedServices
     * @param list<string>      $trustedProxies
     */
    public function __construct(
        public readonly string $accessSwitchToken,
        public readonly bool $defaultOpen,
        public readonly array $authorizedServices = [],
        public readonly bool $uiEnabled = false,
        public readonly int $uiSessionTtl = 2_592_000,
        public readonly bool $uiCookieSecure = false,
        public readonly string $uiSessionSecret = '',
        public readonly int $rateLimitMaxAttempts = 2,
        public readonly int $rateLimitWindowSeconds = 60,
        public readonly array $trustedProxies = [],
        public readonly bool $logClientIp = false,
    ) {
    }

    public static function fromEnvironment(): self
    {
        $accessSwitchToken = getenv('ACCESS_SWITCH_TOKEN') ?: '';
        $defaultOpen = filter_var(
            getenv('DEFAULT_OPEN') ?: 'false',
            FILTER_VALIDATE_BOOLEAN
        );
        $authorizedServices = self::parseAuthorizedServices(getenv('AUTHORIZED_SERVICES') ?: '');
        $uiEnabled = filter_var(
            getenv('UI_ENABLED') ?: 'false',
            FILTER_VALIDATE_BOOLEAN
        );
        $uiSessionTtl = self::parsePositiveInt(getenv('UI_SESSION_TTL') ?: '2592000', 2_592_000);
        $uiCookieSecure = filter_var(
            getenv('UI_COOKIE_SECURE') ?: 'false',
            FILTER_VALIDATE_BOOLEAN
        );
        $uiSecretRaw = getenv('ACCESS_SWITCH_UI_SECRET') ?: '';
        $uiSessionSecret = $uiSecretRaw !== '' ? $uiSecretRaw : $accessSwitchToken;
        $rateLimitMaxAttempts = self::parseNonNegativeInt(self::env('RATE_LIMIT_MAX_ATTEMPTS', '2'), 2);
        $rateLimitWindowSeconds = self::parseNonNegativeInt(self::env('RATE_LIMIT_WINDOW_SECONDS', '60'), 60);
        $trustedProxies = self::parseList(getenv('TRUSTED_PROXIES') ?: '');
        $logClientIp = filter_var(
            getenv('LOG_CLIENT_IP') ?: 'false',
            FILTER_VALIDATE_BOOLEAN
        );

        return new self(
            $accessSwitchToken,
            $defaultOpen,
            $authorizedServices,
            $uiEnabled,
            $uiSessionTtl,
            $uiCookieSecure,
            $uiSessionSecret,
            $rateLimitMaxAttempts,
            $rateLimitWindowSeconds,
            $trustedProxies,
            $logClientIp,
        );
    }

    private static function parsePositiveInt(string $raw, int $default): int
    {
        if ($raw === '' || !ctype_digit($raw)) {
            return $default;
        }

        $value = (int) $raw;

        return $value > 0 ? $value : $default;
    }

    private static function env(string $key, string $default): string
    {
        $value = getenv($key);

        return ($value === false || $value === '') ? $default : $value;
    }

    /** `0` is valid (disables rate limiting in {@see RateLimiter}). */
    private static function parseNonNegativeInt(string $raw, int $default): int
    {
        if ($raw === '' || !ctype_digit($raw)) {
            return $default;
        }

        return (int) $raw;
    }

    /**
     * @return list<string>
     */
    private static function parseAuthorizedServices(string $raw): array
    {
        return self::parseList($raw);
    }

    /**
     * @return list<string>
     */
    private static function parseList(string $raw): array
    {
        if ($raw === '') {
            return [];
        }

        $items = [];
        foreach (explode(',', $raw) as $part) {
            $part = trim($part);
            if ($part !== '') {
                $items[] = $part;
            }
        }

        return $items;
    }
}
