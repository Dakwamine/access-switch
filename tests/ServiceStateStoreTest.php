<?php

declare(strict_types=1);

namespace AccessSwitch\Tests;

use AccessSwitch\Paths;
use AccessSwitch\ServiceRegistry;
use AccessSwitch\ServiceStateStore;

final class ServiceStateStoreTest extends TestCase
{
    private string $dataDir;

    protected function setUp(): void
    {
        $this->dataDir = $this->createTempDataDir();
    }

    protected function tearDown(): void
    {
        $this->removeTempDataDir($this->dataDir);
    }

    public function testSeparateFilesPerService(): void
    {
        $store = $this->store();

        $store->setOpen('toto', true);
        $store->setOpen(ServiceRegistry::DEFAULT_SERVICE_ID, false);

        $this->assertTrue($store->isOpen('toto'));
        $this->assertFalse($store->isOpen(ServiceRegistry::DEFAULT_SERVICE_ID));
        $this->assertFileExists($this->dataDir . '/states/toto.json');
        $this->assertFileExists($this->dataDir . '/states/default.json');
    }

    public function testReadsLegacyDefaultStateFile(): void
    {
        file_put_contents(
            $this->dataDir . '/state.json',
            json_encode(['open' => true, 'updated_at' => '2026-01-01T00:00:00+00:00'])
        );

        $store = $this->store();
        $this->assertTrue($store->isOpen(ServiceRegistry::DEFAULT_SERVICE_ID));
    }

    public function testWriteUsesDefaultPathNotLegacy(): void
    {
        file_put_contents(
            $this->dataDir . '/state.json',
            json_encode(['open' => true])
        );

        $store = $this->store();
        $store->setOpen(ServiceRegistry::DEFAULT_SERVICE_ID, false);

        $this->assertFileExists($this->dataDir . '/states/default.json');
        $this->assertFalse($store->isOpen(ServiceRegistry::DEFAULT_SERVICE_ID));
    }

    private function store(): ServiceStateStore
    {
        return new ServiceStateStore(new Paths($this->dataDir), false);
    }
}
