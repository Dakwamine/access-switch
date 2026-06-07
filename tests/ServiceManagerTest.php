<?php

declare(strict_types=1);

namespace AccessSwitch\Tests;

use AccessSwitch\Config;
use AccessSwitch\Paths;
use AccessSwitch\ServiceException;
use AccessSwitch\ServiceManager;
use InvalidArgumentException;

final class ServiceManagerTest extends TestCase
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

    public function testDefaultServiceAlwaysAuthorized(): void
    {
        $manager = $this->manager([], null);

        $this->assertTrue($manager->isAuthorizedForCheck('default'));
        $this->assertTrue($manager->isAuthorizedForAdmin('default'));
        $this->assertSame(['default'], $manager->all());
    }

    public function testExistingStateFileAuthorizesCheckWithoutConfig(): void
    {
        file_put_contents($this->dataDir . '/states/toto.json', '{"open":false}');

        $manager = $this->manager([], null);

        $this->assertTrue($manager->isAuthorizedForCheck('toto'));
        $this->assertTrue($manager->isAuthorizedForAdmin('toto'));
        $this->assertSame(['default', 'toto'], $manager->all());
    }

    public function testUnknownServiceWithoutStateFileIsNotAuthorizedForCheck(): void
    {
        $manager = $this->manager([], null);

        $this->assertFalse($manager->isAuthorizedForCheck('toto'));
        $this->assertTrue($manager->isAuthorizedForAdmin('toto'));
    }

    public function testServicesFileRestrictsCheckToListedServices(): void
    {
        file_put_contents($this->dataDir . '/states/autre.json', '{"open":false}');

        $manager = $this->manager([], ['toto']);

        $this->assertTrue($manager->isAuthorizedForCheck('toto'));
        $this->assertFalse($manager->isAuthorizedForCheck('autre'));
        $this->assertFalse($manager->isAuthorizedForAdmin('autre'));
    }

    public function testEnvAuthorizedServicesRestrictsFurtherThanServicesFile(): void
    {
        $manager = $this->manager(['toto'], ['toto', 'autre']);

        $this->assertTrue($manager->isAuthorizedForCheck('toto'));
        $this->assertFalse($manager->isAuthorizedForCheck('autre'));
        $this->assertFalse($manager->isAuthorizedForAdmin('autre'));
    }

    public function testEnvAuthorizedServicesWithoutServicesFile(): void
    {
        file_put_contents($this->dataDir . '/states/autre.json', '{"open":false}');

        $manager = $this->manager(['toto'], null);

        $this->assertTrue($manager->isAuthorizedForCheck('toto'));
        $this->assertFalse($manager->isAuthorizedForCheck('autre'));
        $this->assertFalse($manager->isAuthorizedForAdmin('autre'));
    }

    public function testValidateServiceId(): void
    {
        $manager = $this->manager([], null);

        $this->assertTrue($manager->validateServiceId('toto'));
        $this->assertTrue($manager->validateServiceId('app-1'));
        $this->assertFalse($manager->validateServiceId('../etc'));
        $this->assertFalse($manager->validateServiceId(''));
    }

    public function testRejectsInvalidServiceInConfiguration(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->manager(['bad/id'], null);
    }

    public function testFromConfigLoadsServicesFileAndEnvIntersection(): void
    {
        file_put_contents(
            $this->dataDir . '/services.json',
            json_encode(['toto', 'autre'])
        );

        $config = new Config('token', false, ['toto']);
        $manager = ServiceManager::fromConfig($config, new Paths($this->dataDir));

        $this->assertTrue($manager->hasServicesFile());
        $this->assertTrue($manager->isAuthorizedForCheck('toto'));
        $this->assertFalse($manager->isAuthorizedForCheck('autre'));
    }

    public function testSeparateStateFilesPerService(): void
    {
        $manager = $this->manager([], null);

        $manager->setOpen('toto', true);
        $manager->setOpen(ServiceManager::DEFAULT_SERVICE_ID, false);

        $this->assertTrue($manager->isOpen('toto'));
        $this->assertFalse($manager->isOpen(ServiceManager::DEFAULT_SERVICE_ID));
        $this->assertFileExists($this->dataDir . '/states/toto.json');
        $this->assertFileExists($this->dataDir . '/states/default.json');
    }

    public function testReadsLegacyDefaultStateFile(): void
    {
        file_put_contents(
            $this->dataDir . '/state.json',
            json_encode(['open' => true, 'updated_at' => '2026-01-01T00:00:00+00:00'])
        );

        $manager = $this->manager([], null);
        $this->assertTrue($manager->isOpen(ServiceManager::DEFAULT_SERVICE_ID));
    }

    public function testWriteUsesDefaultPathNotLegacy(): void
    {
        file_put_contents(
            $this->dataDir . '/state.json',
            json_encode(['open' => true])
        );

        $manager = $this->manager([], null);
        $manager->setOpen(ServiceManager::DEFAULT_SERVICE_ID, false);

        $this->assertFileExists($this->dataDir . '/states/default.json');
        $this->assertFalse($manager->isOpen(ServiceManager::DEFAULT_SERVICE_ID));
    }

    public function testAddToListWhenServicesJsonExists(): void
    {
        file_put_contents($this->dataDir . '/services.json', json_encode(['toto']));

        $manager = $this->manager([], ['toto']);
        $manager->setOpen('autre', false);

        $this->assertSame(['toto', 'autre'], json_decode(file_get_contents($this->dataDir . '/services.json'), true));
    }

    public function testSetOpenRejectsUnknownWithOpenTrueWhenServicesJsonExists(): void
    {
        file_put_contents($this->dataDir . '/services.json', json_encode(['toto']));

        $manager = $this->manager([], ['toto']);

        $this->expectException(ServiceException::class);
        $manager->setOpen('autre', true);
    }

    public function testRemoveUpdatesListAndDeletesStateFile(): void
    {
        file_put_contents($this->dataDir . '/services.json', json_encode(['toto', 'autre']));
        file_put_contents($this->dataDir . '/states/toto.json', json_encode(['open' => true]));

        $manager = $this->manager([], ['toto', 'autre']);
        $manager->remove('toto');

        $this->assertSame(['autre'], json_decode(file_get_contents($this->dataDir . '/services.json'), true));
        $this->assertFileDoesNotExist($this->dataDir . '/states/toto.json');
    }

    public function testRemoveDeletesStateFileInDiscoveryMode(): void
    {
        file_put_contents($this->dataDir . '/states/toto.json', json_encode(['open' => true]));

        $manager = $this->manager([], null);
        $manager->remove('toto');

        $this->assertFileDoesNotExist($this->dataDir . '/states/toto.json');
        $this->assertSame(['default'], $manager->all());
    }

    public function testRemoveThrowsWhenNothingToDelete(): void
    {
        $manager = $this->manager([], null);

        $this->expectException(ServiceException::class);
        $manager->remove('toto');
    }

    public function testCanRemove(): void
    {
        file_put_contents($this->dataDir . '/states/toto.json', json_encode(['open' => true]));

        $discovery = $this->manager([], null);
        $this->assertTrue($discovery->canRemove('toto'));
        $this->assertFalse($discovery->canRemove('default'));
        $this->assertFalse($discovery->canRemove('missing'));

        file_put_contents($this->dataDir . '/services.json', json_encode(['toto']));
        $listed = $this->manager([], ['toto']);
        $this->assertTrue($listed->canRemove('toto'));
    }

    /**
     * @param list<string>      $envServices
     * @param list<string>|null $fileServices
     */
    private function manager(array $envServices, ?array $fileServices): ServiceManager
    {
        return new ServiceManager($envServices, $fileServices, new Paths($this->dataDir), false);
    }
}
