<?php

declare(strict_types=1);

namespace AccessSwitch;

final class Config
{
    public function __construct(
        public readonly string $stateFile,
        public readonly string $accessSwitchToken,
        public readonly bool $defaultOpen,
    ) {
    }

    public static function fromEnvironment(): self
    {
        $stateFile = getenv('STATE_FILE') ?: '/data/state.json';
        $accessSwitchToken = getenv('ACCESS_SWITCH_TOKEN') ?: '';
        $defaultOpen = filter_var(
            getenv('DEFAULT_OPEN') ?: 'false',
            FILTER_VALIDATE_BOOLEAN
        );

        return new self($stateFile, $accessSwitchToken, $defaultOpen);
    }
}
