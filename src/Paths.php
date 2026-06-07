<?php

declare(strict_types=1);

namespace AccessSwitch;

final class Paths
{
    public const DATA_DIR = '/data';

    public function __construct(
        private readonly string $dataDir = self::DATA_DIR,
    ) {
    }

    public function statePath(string $serviceId): string
    {
        return $this->dataDir . '/states/' . $serviceId . '.json';
    }

    public function servicesFile(): string
    {
        return $this->dataDir . '/services.json';
    }

    public function legacyStateFile(): string
    {
        return $this->dataDir . '/state.json';
    }

    public function statesDir(): string
    {
        return $this->dataDir . '/states';
    }

    public function rateLimitDir(): string
    {
        return $this->dataDir . '/.ratelimit';
    }
}
