<?php

declare(strict_types=1);

namespace AccessSwitch;

use InvalidArgumentException;

final class ServiceRegistry
{
    public const DEFAULT_SERVICE_ID = 'default';

    private const ID_PATTERN = '/^[a-zA-Z0-9][a-zA-Z0-9_-]{0,63}$/';

    /** @var list<string> */
    private readonly array $envAuthorized;

    /** @var list<string>|null null when /data/services.json does not exist */
    private readonly ?array $fileServices;

    /**
     * @param list<string>      $envAuthorizedServices from AUTHORIZED_SERVICES (may be empty)
     * @param list<string>|null $fileServices        from services.json, or null if file absent
     */
    public function __construct(
        array $envAuthorizedServices,
        ?array $fileServices,
        private readonly Paths $paths,
    ) {
        $this->envAuthorized = $this->normalizeServiceList($envAuthorizedServices);
        $this->fileServices = $fileServices !== null
            ? $this->normalizeServiceList($fileServices)
            : null;
    }

    public static function fromConfig(Config $config, Paths $paths): self
    {
        $fileServices = self::loadFromFile($paths->servicesFile());

        return new self($config->authorizedServices, $fileServices, $paths);
    }

    public function isAuthorizedForCheck(string $serviceId): bool
    {
        if ($serviceId === self::DEFAULT_SERVICE_ID) {
            return true;
        }

        if ($this->envAuthorized !== [] && !in_array($serviceId, $this->envAuthorized, true)) {
            return false;
        }

        if ($this->fileServices !== null) {
            return in_array($serviceId, $this->fileServices, true);
        }

        if ($this->envAuthorized !== []) {
            return in_array($serviceId, $this->envAuthorized, true);
        }

        return $this->stateFileExists($serviceId);
    }

    public function isAuthorizedForAdmin(string $serviceId): bool
    {
        if ($serviceId === self::DEFAULT_SERVICE_ID) {
            return true;
        }

        if ($this->envAuthorized !== [] && !in_array($serviceId, $this->envAuthorized, true)) {
            return false;
        }

        if ($this->fileServices !== null) {
            return in_array($serviceId, $this->fileServices, true);
        }

        if ($this->envAuthorized !== []) {
            return in_array($serviceId, $this->envAuthorized, true);
        }

        return true;
    }

    public function validateServiceId(string $serviceId): bool
    {
        return preg_match(self::ID_PATTERN, $serviceId) === 1;
    }

    /** @return list<string> */
    public function all(): array
    {
        $services = [self::DEFAULT_SERVICE_ID];

        if ($this->fileServices !== null) {
            return $this->mergeUnique($services, $this->fileServices);
        }

        if ($this->envAuthorized !== []) {
            return $this->mergeUnique($services, $this->envAuthorized);
        }

        return $this->mergeUnique($services, $this->discoverStateFiles());
    }

    public function hasServicesFile(): bool
    {
        return $this->fileServices !== null;
    }

    private function stateFileExists(string $serviceId): bool
    {
        return is_file($this->paths->statePath($serviceId));
    }

    /**
     * @return list<string>
     */
    private function discoverStateFiles(): array
    {
        $dir = $this->paths->statesDir();
        if (!is_dir($dir)) {
            return [];
        }

        $services = [];
        foreach (scandir($dir) ?: [] as $entry) {
            if (!str_ends_with($entry, '.json')) {
                continue;
            }
            $serviceId = substr($entry, 0, -5);
            if ($serviceId !== '' && $this->validateServiceId($serviceId)) {
                $services[] = $serviceId;
            }
        }

        sort($services);

        return $services;
    }

    /**
     * @param list<string> $services
     *
     * @return list<string>
     */
    private function normalizeServiceList(array $services): array
    {
        $normalized = [];

        foreach ($services as $service) {
            if ($service === self::DEFAULT_SERVICE_ID) {
                continue;
            }
            if (!$this->validateServiceId($service)) {
                throw new InvalidArgumentException('Invalid service id in configuration: ' . $service);
            }
            if (!in_array($service, $normalized, true)) {
                $normalized[] = $service;
            }
        }

        return $normalized;
    }

    /**
     * @param list<string> $base
     * @param list<string> $extra
     *
     * @return list<string>
     */
    private function mergeUnique(array $base, array $extra): array
    {
        $merged = $base;
        foreach ($extra as $service) {
            if ($service === self::DEFAULT_SERVICE_ID) {
                continue;
            }
            if (!in_array($service, $merged, true)) {
                $merged[] = $service;
            }
        }

        return $merged;
    }

    /**
     * @return list<string>|null
     */
    private static function loadFromFile(string $path): ?array
    {
        if (!is_file($path) || !is_readable($path)) {
            return null;
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return null;
        }

        $services = [];
        foreach ($decoded as $service) {
            if (!is_string($service)) {
                return null;
            }
            $service = trim($service);
            if ($service !== '') {
                $services[] = $service;
            }
        }

        return $services;
    }
}
