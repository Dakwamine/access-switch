<?php

declare(strict_types=1);

namespace AccessSwitch\Tests;

use AccessSwitch\Application;
use AccessSwitch\Config;
use AccessSwitch\Paths;
use AccessSwitch\ServiceRegistry;
use AccessSwitch\ServiceStateStore;

final class ApplicationTest extends TestCase
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

    public function testHealthReturnsOk(): void
    {
        $app = $this->app();
        $response = $app->handle('GET', '/health');
        $this->assertSame(200, $response->status);
        $this->assertSame(['status' => 'ok'], $this->decodeJsonResponse($response));
    }

    public function testCheckClosedByDefault(): void
    {
        $app = $this->app();
        $response = $app->handle('GET', '/check');
        $this->assertSame(503, $response->status);
    }

    public function testCheckDefaultAlias(): void
    {
        $app = $this->app();
        $app->handle('POST', '/admin', '{"open":true}', 'Bearer test-secret');

        $this->assertSame(200, $app->handle('GET', '/check')->status);
        $this->assertSame(200, $app->handle('GET', '/check/default')->status);
    }

    public function testCheckOpenAfterSet(): void
    {
        $app = $this->app();
        $app->handle(
            'POST',
            '/admin',
            '{"open":true}',
            'Bearer test-secret'
        );
        $response = $app->handle('GET', '/check');
        $this->assertSame(200, $response->status);
    }

    public function testCheckFailsClosedWhenStateUnreadable(): void
    {
        $path = $this->dataDir . '/states/default.json';
        file_put_contents($path, '{"open":true}');
        chmod($path, 0000);

        $app = $this->app();
        $response = $app->handle('GET', '/check');
        $this->assertSame(503, $response->status);
    }

    public function testCheckLegacyStateFile(): void
    {
        file_put_contents($this->dataDir . '/state.json', json_encode(['open' => true]));

        $app = $this->app();
        $this->assertSame(200, $app->handle('GET', '/check')->status);
    }

    public function testCheckMultiService(): void
    {
        $app = $this->app(authorizedServices: ['toto']);
        $app->handle(
            'POST',
            '/admin',
            '{"service":"toto","open":true}',
            'Bearer test-secret'
        );

        $this->assertSame(200, $app->handle('GET', '/check/toto')->status);
        $this->assertSame(503, $app->handle('GET', '/check')->status);
    }

    public function testCheckUnauthorizedServiceReturns503(): void
    {
        $app = $this->app();
        $this->assertSame(503, $app->handle('GET', '/check/toto')->status);
    }

    public function testCheckWorksForExistingStateFileWithoutConfig(): void
    {
        file_put_contents($this->dataDir . '/states/toto.json', json_encode(['open' => true]));

        $app = $this->app();
        $this->assertSame(200, $app->handle('GET', '/check/toto')->status);
    }

    public function testCheckBlockedWhenStateFileExistsButNotInServicesJson(): void
    {
        file_put_contents($this->dataDir . '/states/autre.json', json_encode(['open' => true]));
        file_put_contents($this->dataDir . '/services.json', json_encode(['toto']));

        $app = $this->app();
        $this->assertSame(503, $app->handle('GET', '/check/autre')->status);
    }

    public function testCheckBlockedWhenInServicesJsonButNotInEnv(): void
    {
        file_put_contents($this->dataDir . '/services.json', json_encode(['toto', 'autre']));

        $app = $this->app(authorizedServices: ['toto']);
        $app->handle('POST', '/admin', '{"service":"toto","open":true}', 'Bearer test-secret');

        $this->assertSame(200, $app->handle('GET', '/check/toto')->status);
        $this->assertSame(503, $app->handle('GET', '/check/autre')->status);
    }

    public function testAdminCanCreateServiceWithoutConfig(): void
    {
        $app = $this->app();
        $response = $app->handle(
            'POST',
            '/admin',
            '{"service":"toto","open":true}',
            'Bearer test-secret'
        );

        $this->assertSame(200, $response->status);
        $this->assertSame(200, $app->handle('GET', '/check/toto')->status);
    }

    public function testCheckInvalidServiceIdReturns503(): void
    {
        $app = $this->app(authorizedServices: ['toto']);
        $this->assertSame(503, $app->handle('GET', '/check/bad.id')->status);
    }

    public function testUnknownRouteReturns404(): void
    {
        $app = $this->app();
        $this->assertSame(404, $app->handle('GET', '/missing')->status);
        $this->assertSame(404, $app->handle('POST', '/missing')->status);
    }

    public function testUnsupportedMethodReturns405(): void
    {
        $app = $this->app();
        $this->assertSame(405, $app->handle('DELETE', '/check')->status);
    }

    public function testAdminRequiresTokenConfigured(): void
    {
        $app = $this->app(token: '');
        $response = $app->handle('POST', '/admin', '{"open":true}', 'Bearer x');
        $this->assertSame(503, $response->status);
        $this->assertStringContainsString('ACCESS_SWITCH_TOKEN', $response->body);
    }

    public function testAdminRejectsInvalidBearer(): void
    {
        $app = $this->app();
        $response = $app->handle('POST', '/admin', '{"open":true}', 'Bearer wrong');
        $this->assertSame(401, $response->status);
    }

    public function testAdminRequiresBody(): void
    {
        $app = $this->app();
        $response = $app->handle('POST', '/admin', '', 'Bearer test-secret');
        $this->assertSame(400, $response->status);
    }

    public function testAdminRejectsInvalidJson(): void
    {
        $app = $this->app();
        $response = $app->handle('POST', '/admin', '{', 'Bearer test-secret');
        $this->assertSame(400, $response->status);
    }

    public function testAdminRequiresOpenField(): void
    {
        $app = $this->app();
        $response = $app->handle('POST', '/admin', '{}', 'Bearer test-secret');
        $this->assertSame(400, $response->status);
    }

    public function testAdminRequiresBooleanOpen(): void
    {
        $app = $this->app();
        $response = $app->handle('POST', '/admin', '{"open":"yes"}', 'Bearer test-secret');
        $this->assertSame(400, $response->status);
    }

    public function testAdminRejectsUnauthorizedService(): void
    {
        $app = $this->app(authorizedServices: ['toto']);
        $response = $app->handle(
            'POST',
            '/admin',
            '{"service":"autre","open":true}',
            'Bearer test-secret'
        );
        $this->assertSame(400, $response->status);
        $this->assertStringContainsString('unknown or unauthorized service', $response->body);
    }

    public function testAdminRejectsInvalidServiceField(): void
    {
        $app = $this->app(authorizedServices: ['toto']);
        $response = $app->handle(
            'POST',
            '/admin',
            '{"service":123,"open":true}',
            'Bearer test-secret'
        );
        $this->assertSame(400, $response->status);
    }

    public function testAdminTogglesState(): void
    {
        $app = $this->app();
        $open = $app->handle('POST', '/admin', '{"open":true}', 'Bearer test-secret');
        $this->assertSame(200, $open->status);
        $data = $this->decodeJsonResponse($open);
        $this->assertTrue($data['open']);
        $this->assertSame('default', $data['service']);
        $this->assertArrayHasKey('updated_at', $data);

        $close = $app->handle('POST', '/admin', '{"open":false}', 'Bearer test-secret');
        $this->assertSame(200, $close->status);
        $this->assertFalse($this->decodeJsonResponse($close)['open']);
        $this->assertSame(503, $app->handle('GET', '/check')->status);
    }

    public function testAdminMigratesLegacyStateToDefaultPath(): void
    {
        file_put_contents($this->dataDir . '/state.json', json_encode(['open' => true]));
        $app = $this->app();

        $app->handle('POST', '/admin', '{"open":false}', 'Bearer test-secret');

        $this->assertFileExists($this->dataDir . '/states/default.json');
        $this->assertSame(503, $app->handle('GET', '/check')->status);
    }

    public function testPathTrailingSlashNormalized(): void
    {
        $app = $this->app();
        $this->assertSame(200, $app->handle('GET', '/health/')->status);
    }

    /**
     * @param list<string> $authorizedServices
     */
    private function app(string $token = 'test-secret', array $authorizedServices = []): Application
    {
        $config = new Config($token, false, $authorizedServices);
        $paths = new Paths($this->dataDir);
        $registry = ServiceRegistry::fromConfig($config, $paths);
        $store = new ServiceStateStore($paths, $config->defaultOpen);

        return new Application($config, $registry, $store);
    }
}
