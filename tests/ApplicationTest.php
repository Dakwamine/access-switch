<?php

declare(strict_types=1);

namespace AccessSwitch\Tests;

use AccessSwitch\Application;
use AccessSwitch\Config;
use AccessSwitch\StateStore;

final class ApplicationTest extends TestCase
{
    private string $statePath;

    protected function setUp(): void
    {
        $this->statePath = $this->createTempStatePath();
    }

    protected function tearDown(): void
    {
        if (is_file($this->statePath)) {
            @chmod($this->statePath, 0644);
            @unlink($this->statePath);
        }
        $dir = dirname($this->statePath);
        if (is_dir($dir)) {
            @rmdir($dir);
        }
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
        file_put_contents($this->statePath, '{"open":true}');
        chmod($this->statePath, 0000);

        $app = $this->app();
        $response = $app->handle('GET', '/check');
        $this->assertSame(503, $response->status);
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

    public function testAdminTogglesState(): void
    {
        $app = $this->app();
        $open = $app->handle('POST', '/admin', '{"open":true}', 'Bearer test-secret');
        $this->assertSame(200, $open->status);
        $data = $this->decodeJsonResponse($open);
        $this->assertTrue($data['open']);
        $this->assertArrayHasKey('updated_at', $data);

        $close = $app->handle('POST', '/admin', '{"open":false}', 'Bearer test-secret');
        $this->assertSame(200, $close->status);
        $this->assertFalse($this->decodeJsonResponse($close)['open']);
        $this->assertSame(503, $app->handle('GET', '/check')->status);
    }

    public function testPathTrailingSlashNormalized(): void
    {
        $app = $this->app();
        $this->assertSame(200, $app->handle('GET', '/health/')->status);
    }

    private function app(string $token = 'test-secret'): Application
    {
        $config = new Config($this->statePath, $token, false);

        return new Application($config, new StateStore($config->stateFile, $config->defaultOpen));
    }
}
