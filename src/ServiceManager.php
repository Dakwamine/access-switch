<?php

declare(strict_types=1);

namespace AccessSwitch;

use InvalidArgumentException;

/**
 * Manages service authorization, persisted open/closed state, and the optional services.json whitelist.
 *
 * Authorization rules are loaded once per request from environment configuration, services.json,
 * and existing state files. Writes update disk only; the in-memory snapshot is not refreshed
 * until the next HTTP request.
 */
final class ServiceManager
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
        private readonly bool $defaultOpen,
    ) {
        $this->envAuthorized = $this->normalizeServiceList($envAuthorizedServices);
        $this->fileServices = $fileServices !== null
            ? $this->normalizeServiceList($fileServices)
            : null;
    }

    public static function fromConfig(Config $config, Paths $paths): self
    {
        return new self(
            $config->authorizedServices,
            self::loadServicesFile($paths->servicesFile()),
            $paths,
            $config->defaultOpen,
        );
    }

    public function validateServiceId(string $serviceId): bool
    {
        return preg_match(self::ID_PATTERN, $serviceId) === 1;
    }

    /** Returns whether a public access check may succeed for the given service. */
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

    /** Returns whether admin may change state for the given service without registering it first. */
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

    /** @return list<string> known service ids (always includes {@see DEFAULT_SERVICE_ID}) */
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

    /** Returns whether /data/services.json was present when this instance was built. */
    public function hasServicesFile(): bool
    {
        return $this->fileServices !== null;
    }

    public function isOpen(string $serviceId): bool
    {
        $state = $this->readState($serviceId);

        return (bool) ($state['open'] ?? $this->defaultOpen);
    }

    /** @return array{open: bool, updated_at: string|null} */
    public function getState(string $serviceId): array
    {
        $state = $this->readState($serviceId);
        $updatedAt = $state['updated_at'] ?? null;

        return [
            'open' => (bool) ($state['open'] ?? $this->defaultOpen),
            'updated_at' => is_string($updatedAt) ? $updatedAt : null,
        ];
    }

    /**
     * Persists the open/closed state for a service, registering it when allowed.
     *
     * In discovery mode (no services.json, no env restriction), any valid id is accepted.
     * When services.json is active, an unknown id may only be registered closed (open=false)
     * before it can be opened in a later call.
     *
     * @throws ServiceException
     */
    public function setOpen(string $serviceId, bool $open): void
    {
        if (!$this->validateServiceId($serviceId)) {
            throw new ServiceException(ServiceException::INVALID_ID);
        }

        if (!$this->isAuthorizedForAdmin($serviceId)) {
            if (!$this->hasServicesFile() || $open) {
                throw new ServiceException(ServiceException::UNKNOWN);
            }

            $this->addToList($serviceId);
            $open = false;
        }

        JsonFile::write($this->writeStatePath($serviceId), [
            'open' => $open,
            'updated_at' => gmdate('c'),
        ]);
    }

    /** Whether admin may delete this service (not default, authorized, and listed or has a state file). */
    public function canRemove(string $serviceId): bool
    {
        if ($serviceId === self::DEFAULT_SERVICE_ID || !$this->isAuthorizedForAdmin($serviceId)) {
            return false;
        }

        if ($this->hasServicesFile() && in_array($serviceId, $this->fileServices ?? [], true)) {
            return true;
        }

        return is_file($this->paths->statePath($serviceId));
    }

    /**
     * Removes a service: drops it from services.json when listed, and deletes its state file.
     *
     * @throws ServiceException
     */
    public function remove(string $serviceId): void
    {
        if ($serviceId === self::DEFAULT_SERVICE_ID) {
            throw new ServiceException(ServiceException::CANNOT_MANAGE_DEFAULT);
        }

        if (!$this->validateServiceId($serviceId)) {
            throw new ServiceException(ServiceException::INVALID_ID);
        }

        if (!$this->isAuthorizedForAdmin($serviceId)) {
            throw new ServiceException(ServiceException::UNKNOWN);
        }

        $removedFromList = false;
        if ($this->hasServicesFile()) {
            $services = $this->fileServices ?? [];
            if (in_array($serviceId, $services, true)) {
                $services = array_values(array_filter(
                    $services,
                    static fn (string $id): bool => $id !== $serviceId,
                ));
                JsonFile::write($this->paths->servicesFile(), $services);
                $removedFromList = true;
            }
        }

        $statePath = $this->paths->statePath($serviceId);
        $removedState = is_file($statePath) && unlink($statePath);

        if (!$removedFromList && !$removedState) {
            throw new ServiceException(ServiceException::NOT_FOUND);
        }
    }

    private function addToList(string $serviceId): void
    {
        $this->assertCanManageListEntry($serviceId);

        $services = $this->listForWrite();
        if (in_array($serviceId, $services, true)) {
            throw new ServiceException(ServiceException::ALREADY_EXISTS);
        }

        $services[] = $serviceId;
        JsonFile::write($this->paths->servicesFile(), $services);
    }

    private function assertCanManageListEntry(string $serviceId): void
    {
        if ($serviceId === self::DEFAULT_SERVICE_ID) {
            throw new ServiceException(ServiceException::CANNOT_MANAGE_DEFAULT);
        }

        if (!$this->validateServiceId($serviceId)) {
            throw new ServiceException(ServiceException::INVALID_ID);
        }

        if ($this->envAuthorized !== [] && !in_array($serviceId, $this->envAuthorized, true)) {
            throw new ServiceException(ServiceException::NOT_IN_ENV);
        }
    }

    /** @return list<string> */
    private function listForWrite(): array
    {
        if ($this->fileServices !== null) {
            return $this->fileServices;
        }

        $services = [];
        foreach ($this->all() as $serviceId) {
            if ($serviceId === self::DEFAULT_SERVICE_ID) {
                continue;
            }
            $services[] = $serviceId;
        }

        return $services;
    }

    /** @return array<string, mixed> */
    private function readState(string $serviceId): array
    {
        return JsonFile::read($this->readStatePath($serviceId), ['open' => $this->defaultOpen]);
    }

    /** Resolves the state file used for reads (supports legacy /data/state.json for default). */
    private function readStatePath(string $serviceId): string
    {
        if ($serviceId === self::DEFAULT_SERVICE_ID) {
            $newPath = $this->paths->statePath(self::DEFAULT_SERVICE_ID);
            if (is_file($newPath)) {
                return $newPath;
            }

            $legacyPath = $this->paths->legacyStateFile();
            if (is_file($legacyPath)) {
                return $legacyPath;
            }
        }

        return $this->paths->statePath($serviceId);
    }

    private function writeStatePath(string $serviceId): string
    {
        return $this->paths->statePath($serviceId);
    }

    private function stateFileExists(string $serviceId): bool
    {
        return is_file($this->paths->statePath($serviceId));
    }

    /** @return list<string> */
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

    /** @return list<string>|null */
    private static function loadServicesFile(string $path): ?array
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
