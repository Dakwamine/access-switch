<?php

declare(strict_types=1);

namespace AccessSwitch\Tests;

use AccessSwitch\Application;
use AccessSwitch\Config;
use AccessSwitch\Paths;
use AccessSwitch\ServiceRegistry;
use AccessSwitch\ServiceStateStore;
use AccessSwitch\RateLimiter;
use AccessSwitch\UiSession;

final class ApplicationTest extends TestCase
{
    private string $dataDir;

    protected function setUp(): void
    {
        $this->dataDir = $this->createTempDataDir();
    }

    protected function tearDown(): void
    {
        RateLimiter::reset($this->dataDir . '/.ratelimit');
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

    public function testUiDisabledReturns404(): void
    {
        $app = $this->app(uiEnabled: false);
        $this->assertSame(404, $app->handle('GET', '/ui')->status);
        $this->assertSame(404, $app->handle('GET', '/admin/status')->status);
        $this->assertSame(404, $app->handle('POST', '/ui/login', '{"token":"test-secret"}')->status);
    }

    public function testUiEnabledServesHtml(): void
    {
        $app = $this->app(uiEnabled: true);
        $response = $app->handle('GET', '/ui');
        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('text/html', $response->headers['Content-Type'] ?? '');
        $this->assertStringContainsString('access-switch', $response->body);
        $this->assertStringContainsString('Connexion', $response->body);
    }

    public function testUiServesSelectedLanguage(): void
    {
        $app = $this->app(uiEnabled: true);
        $cookie = 'access_switch_lang=en';
        $response = $app->handle('GET', '/ui', null, null, $cookie);
        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('Sign in', $response->body);
        $this->assertStringContainsString('lang="en"', $response->body);
    }

    public function testUiSetLangSetsCookie(): void
    {
        $app = $this->app(uiEnabled: true);
        $response = $app->handle('POST', '/ui/lang', '{"lang":"es"}');
        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('access_switch_lang=es', $response->headers['Set-Cookie'] ?? '');
    }

    public function testUiLoginErrorIsLocalized(): void
    {
        $app = $this->app(uiEnabled: true);
        $response = $app->handle(
            'POST',
            '/ui/login',
            '{"token":"wrong"}',
            null,
            'access_switch_lang=fr'
        );
        $this->assertSame(401, $response->status);
        $data = $this->decodeJsonResponse($response);
        $this->assertSame('Connexion refusée.', $data['error']);
    }

    public function testAdminStatusRequiresAuthWhenUiEnabled(): void
    {
        $app = $this->app(uiEnabled: true);
        $this->assertSame(401, $app->handle('GET', '/admin/status')->status);
    }

    public function testUiLoginAndSessionAdminFlow(): void
    {
        $app = $this->app(uiEnabled: true);
        $login = $app->handle('POST', '/ui/login', '{"token":"test-secret"}');
        $this->assertSame(200, $login->status);
        $this->assertArrayHasKey('Set-Cookie', $login->headers);
        $cookie = $login->headers['Set-Cookie'];

        $status = $app->handle('GET', '/admin/status', null, null, $cookie);
        $this->assertSame(200, $status->status);
        $data = $this->decodeJsonResponse($status);
        $this->assertArrayHasKey('services', $data);
        $this->assertNotEmpty($data['services']);

        $toggle = $app->handle('POST', '/admin', '{"open":true}', null, $cookie);
        $this->assertSame(200, $toggle->status);
        $this->assertSame(200, $app->handle('GET', '/check')->status);

        $logout = $app->handle('POST', '/ui/logout');
        $this->assertSame(200, $logout->status);
        $this->assertStringContainsString('Max-Age=0', $logout->headers['Set-Cookie'] ?? '');
    }

    public function testUiLoginRejectsInvalidToken(): void
    {
        $app = $this->app(uiEnabled: true);
        $response = $app->handle('POST', '/ui/login', '{"token":"wrong"}');
        $this->assertSame(401, $response->status);
    }

    public function testAdminReturns429WhenRateLimited(): void
    {
        RateLimiter::reset();
        $config = new Config('test-secret', false, [], false, 2_592_000, false, 'test-secret', 2, 60);
        $app = $this->appFromConfig($config);

        $this->assertSame(401, $app->handle('POST', '/admin', '{"open":true}', 'Bearer wrong', null, '10.0.0.1')->status);
        $this->assertSame(401, $app->handle('POST', '/admin', '{"open":true}', 'Bearer wrong', null, '10.0.0.1')->status);
        $this->assertSame(429, $app->handle('POST', '/admin', '{"open":true}', 'Bearer wrong', null, '10.0.0.1')->status);
    }

    public function testRateLimitSharedBetweenAdminAndUiLogin(): void
    {
        RateLimiter::reset();
        $config = new Config('test-secret', false, [], true, 2_592_000, false, 'test-secret', 2, 60);
        $app = $this->appFromConfig($config);
        $ip = '10.0.0.2';

        $this->assertSame(401, $app->handle('POST', '/admin', '{"open":true}', 'Bearer wrong', null, $ip)->status);
        $this->assertSame(401, $app->handle('POST', '/ui/login', '{"token":"wrong"}', null, null, $ip)->status);
        $this->assertSame(429, $app->handle('POST', '/admin', '{"open":true}', 'Bearer wrong', null, $ip)->status);
        $this->assertSame(429, $app->handle('POST', '/ui/login', '{"token":"wrong"}', null, null, $ip)->status);
    }

    public function testRateLimitWindowExpires(): void
    {
        RateLimiter::reset();
        $config = new Config('test-secret', false, [], false, 2_592_000, false, 'test-secret', 1, 1);
        $app = $this->appFromConfig($config);
        $ip = '10.0.0.3';

        $this->assertSame(401, $app->handle('POST', '/admin', '{"open":true}', 'Bearer wrong', null, $ip)->status);
        $this->assertSame(429, $app->handle('POST', '/admin', '{"open":true}', 'Bearer wrong', null, $ip)->status);

        sleep(1);

        $this->assertSame(401, $app->handle('POST', '/admin', '{"open":true}', 'Bearer wrong', null, $ip)->status);
    }

    public function testRateLimitUsesXRealIpBehindTrustedProxy(): void
    {
        RateLimiter::reset();
        $config = new Config(
            'test-secret',
            false,
            [],
            false,
            2_592_000,
            false,
            'test-secret',
            1,
            60,
            ['172.18.0.0/16'],
        );
        $app = $this->appFromConfig($config);

        $saved = [
            'REMOTE_ADDR' => $_SERVER['REMOTE_ADDR'] ?? null,
            'HTTP_X_REAL_IP' => $_SERVER['HTTP_X_REAL_IP'] ?? null,
        ];
        $_SERVER['REMOTE_ADDR'] = '172.18.0.5';
        $_SERVER['HTTP_X_REAL_IP'] = '203.0.113.50';

        try {
            $this->assertSame(401, $app->handle('POST', '/admin', '{"open":true}', 'Bearer wrong')->status);
            $this->assertSame(429, $app->handle('POST', '/admin', '{"open":true}', 'Bearer wrong')->status);

            $_SERVER['HTTP_X_REAL_IP'] = '203.0.113.51';
            $this->assertSame(401, $app->handle('POST', '/admin', '{"open":true}', 'Bearer wrong')->status);
        } finally {
            foreach ($saved as $key => $value) {
                if ($value === null) {
                    unset($_SERVER[$key]);
                } else {
                    $_SERVER[$key] = $value;
                }
            }
        }
    }

    public function testUiSessionUsesDedicatedSecretWhenConfigured(): void
    {
        $config = new Config('api-secret', false, [], true, 2_592_000, false, 'ui-secret');
        $app = $this->appFromConfig($config);
        $login = $app->handle('POST', '/ui/login', '{"token":"api-secret"}');
        $this->assertSame(200, $login->status);
        $cookie = $login->headers['Set-Cookie'];

        $sessionWithApiSecret = new UiSession('api-secret', 3600, false);
        $this->assertFalse($sessionWithApiSecret->isValid($cookie));

        $this->assertSame(200, $app->handle('GET', '/admin/status', null, null, $cookie)->status);
    }

    /**
     * @param list<string> $authorizedServices
     */
    private function app(
        string $token = 'test-secret',
        array $authorizedServices = [],
        bool $uiEnabled = false,
        ?string $uiSessionSecret = null,
    ): Application {
        $uiSecret = $uiSessionSecret ?? $token;
        $config = new Config($token, false, $authorizedServices, $uiEnabled, 2_592_000, false, $uiSecret);

        return $this->appFromConfig($config);
    }

    private function appFromConfig(Config $config): Application
    {
        $paths = new Paths($this->dataDir);
        $registry = ServiceRegistry::fromConfig($config, $paths);
        $store = new ServiceStateStore($paths, $config->defaultOpen);
        $uiSession = new UiSession($config->uiSessionSecret, 3600, false);

        $rateLimiter = new RateLimiter($paths->rateLimitDir());

        return new Application($config, $registry, $store, $uiSession, $rateLimiter);
    }

    public function testRateLimitFileStoreSharedAcrossInstances(): void
    {
        $dir = $this->dataDir . '/.ratelimit';
        RateLimiter::reset($dir);
        $limiterA = new RateLimiter($dir);
        $limiterB = new RateLimiter($dir);

        $this->assertTrue($limiterA->isAllowed('auth:1.2.3.4', 2, 60));
        $this->assertTrue($limiterB->isAllowed('auth:1.2.3.4', 2, 60));
        $this->assertFalse($limiterA->isAllowed('auth:1.2.3.4', 2, 60));
    }

    public function testUiLoginBlocksValidTokenWhenRateLimited(): void
    {
        RateLimiter::reset($this->dataDir . '/.ratelimit');
        $config = new Config('test-secret', false, [], true, 2_592_000, false, 'test-secret', 2, 60);
        $app = $this->appFromConfig($config);
        $ip = '10.0.0.9';

        $this->assertSame(401, $app->handle('POST', '/ui/login', '{"token":"wrong"}', null, null, $ip)->status);
        $this->assertSame(401, $app->handle('POST', '/ui/login', '{"token":"wrong"}', null, null, $ip)->status);
        $this->assertSame(429, $app->handle('POST', '/ui/login', '{"token":"test-secret"}', null, null, $ip)->status);
    }
}
