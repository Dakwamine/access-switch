<?php

declare(strict_types=1);

namespace AccessSwitch;

final class ServiceStateStore
{
    public function __construct(
        private readonly Paths $paths,
        private readonly bool $defaultOpen,
    ) {
    }

    public function isOpen(string $serviceId): bool
    {
        $store = new StateStore($this->readPath($serviceId), $this->defaultOpen);

        return $store->isOpen();
    }

    public function setOpen(string $serviceId, bool $open): void
    {
        $store = new StateStore($this->writePath($serviceId), $this->defaultOpen);
        $store->setOpen($open);
    }

    private function readPath(string $serviceId): string
    {
        if ($serviceId === ServiceRegistry::DEFAULT_SERVICE_ID) {
            $newPath = $this->paths->statePath(ServiceRegistry::DEFAULT_SERVICE_ID);
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

    private function writePath(string $serviceId): string
    {
        return $this->paths->statePath($serviceId);
    }
}
