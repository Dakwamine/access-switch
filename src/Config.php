<?php

declare(strict_types=1);

namespace AccessSwitch;

final class Config
{
    /**
     * @param list<string> $authorizedServices
     */
    public function __construct(
        public readonly string $accessSwitchToken,
        public readonly bool $defaultOpen,
        public readonly array $authorizedServices = [],
        public readonly bool $uiEnabled = false,
        public readonly int $uiSessionTtl = 2_592_000,
        public readonly bool $uiCookieSecure = false,
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

        return new self(
            $accessSwitchToken,
            $defaultOpen,
            $authorizedServices,
            $uiEnabled,
            $uiSessionTtl,
            $uiCookieSecure,
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

    /**
     * @return list<string>
     */
    private static function parseAuthorizedServices(string $raw): array
    {
        if ($raw === '') {
            return [];
        }

        $services = [];
        foreach (explode(',', $raw) as $part) {
            $part = trim($part);
            if ($part !== '') {
                $services[] = $part;
            }
        }

        return $services;
    }
}
