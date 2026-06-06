<?php

declare(strict_types=1);

namespace AccessSwitch\Tests;

use AccessSwitch\Config;
use AccessSwitch\Paths;
use AccessSwitch\ServiceRegistry;
use InvalidArgumentException;

final class ServiceRegistryTest extends TestCase
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
        $registry = $this->registry([], null);

        $this->assertTrue($registry->isAuthorizedForCheck('default'));
        $this->assertTrue($registry->isAuthorizedForAdmin('default'));
        $this->assertSame(['default'], $registry->all());
    }

    public function testExistingStateFileAuthorizesCheckWithoutConfig(): void
    {
        file_put_contents($this->dataDir . '/states/toto.json', '{"open":false}');

        $registry = $this->registry([], null);

        $this->assertTrue($registry->isAuthorizedForCheck('toto'));
        $this->assertTrue($registry->isAuthorizedForAdmin('toto'));
        $this->assertSame(['default', 'toto'], $registry->all());
    }

    public function testUnknownServiceWithoutStateFileIsNotAuthorizedForCheck(): void
    {
        $registry = $this->registry([], null);

        $this->assertFalse($registry->isAuthorizedForCheck('toto'));
        $this->assertTrue($registry->isAuthorizedForAdmin('toto'));
    }

    public function testServicesFileRestrictsCheckToListedServices(): void
    {
        file_put_contents($this->dataDir . '/states/autre.json', '{"open":false}');

        $registry = $this->registry([], ['toto']);

        $this->assertTrue($registry->isAuthorizedForCheck('toto'));
        $this->assertFalse($registry->isAuthorizedForCheck('autre'));
        $this->assertFalse($registry->isAuthorizedForAdmin('autre'));
    }

    public function testEnvAuthorizedServicesRestrictsFurtherThanServicesFile(): void
    {
        $registry = $this->registry(['toto'], ['toto', 'autre']);

        $this->assertTrue($registry->isAuthorizedForCheck('toto'));
        $this->assertFalse($registry->isAuthorizedForCheck('autre'));
        $this->assertFalse($registry->isAuthorizedForAdmin('autre'));
    }

    public function testEnvAuthorizedServicesWithoutServicesFile(): void
    {
        file_put_contents($this->dataDir . '/states/autre.json', '{"open":false}');

        $registry = $this->registry(['toto'], null);

        $this->assertTrue($registry->isAuthorizedForCheck('toto'));
        $this->assertFalse($registry->isAuthorizedForCheck('autre'));
        $this->assertFalse($registry->isAuthorizedForAdmin('autre'));
    }

    public function testValidateServiceId(): void
    {
        $registry = $this->registry([], null);

        $this->assertTrue($registry->validateServiceId('toto'));
        $this->assertTrue($registry->validateServiceId('app-1'));
        $this->assertFalse($registry->validateServiceId('../etc'));
        $this->assertFalse($registry->validateServiceId(''));
    }

    public function testRejectsInvalidServiceInConfiguration(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->registry(['bad/id'], null);
    }

    public function testFromConfigLoadsServicesFileAndEnvIntersection(): void
    {
        file_put_contents(
            $this->dataDir . '/services.json',
            json_encode(['toto', 'autre'])
        );

        $config = new Config('token', false, ['toto']);
        $registry = ServiceRegistry::fromConfig($config, new Paths($this->dataDir));

        $this->assertTrue($registry->hasServicesFile());
        $this->assertTrue($registry->isAuthorizedForCheck('toto'));
        $this->assertFalse($registry->isAuthorizedForCheck('autre'));
    }

    /**
     * @param list<string>      $envServices
     * @param list<string>|null $fileServices
     */
    private function registry(array $envServices, ?array $fileServices): ServiceRegistry
    {
        return new ServiceRegistry($envServices, $fileServices, new Paths($this->dataDir));
    }
}
