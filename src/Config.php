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

        return new self($accessSwitchToken, $defaultOpen, $authorizedServices);
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
