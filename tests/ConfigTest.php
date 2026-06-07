<?php

declare(strict_types=1);

namespace AccessSwitch\Tests;

use AccessSwitch\Config;

final class ConfigTest extends TestCase
{
    /** @var array<string, string|false> */
    private array $saved = [];

    protected function tearDown(): void
    {
        foreach ($this->saved as $key => $value) {
            if ($value === false) {
                putenv($key);
            } else {
                putenv($key . '=' . $value);
            }
        }
        $this->saved = [];
    }

    public function testFromEnvironmentUsesDefaults(): void
    {
        $this->unsetEnv('ACCESS_SWITCH_TOKEN');
        $this->unsetEnv('DEFAULT_OPEN');
        $this->unsetEnv('AUTHORIZED_SERVICES');
        $this->unsetEnv('UI_ENABLED');
        $this->unsetEnv('ACCESS_SWITCH_UI_SECRET');
        $this->unsetEnv('RATE_LIMIT_MAX_ATTEMPTS');
        $this->unsetEnv('RATE_LIMIT_WINDOW_SECONDS');
        $this->unsetEnv('TRUSTED_PROXIES');

        $config = Config::fromEnvironment();

        $this->assertSame('', $config->accessSwitchToken);
        $this->assertSame('', $config->uiSessionSecret);
        $this->assertFalse($config->defaultOpen);
        $this->assertSame([], $config->authorizedServices);
        $this->assertFalse($config->uiEnabled);
        $this->assertSame(2, $config->rateLimitMaxAttempts);
        $this->assertSame(60, $config->rateLimitWindowSeconds);
    }

    public function testFromEnvironmentReadsVariables(): void
    {
        $this->setEnv('ACCESS_SWITCH_TOKEN', 'secret');
        $this->setEnv('DEFAULT_OPEN', 'true');
        $this->setEnv('AUTHORIZED_SERVICES', 'toto, autre');
        $this->setEnv('UI_ENABLED', 'true');

        $config = Config::fromEnvironment();

        $this->assertSame('secret', $config->accessSwitchToken);
        $this->assertSame('secret', $config->uiSessionSecret);
        $this->assertTrue($config->defaultOpen);
        $this->assertSame(['toto', 'autre'], $config->authorizedServices);
        $this->assertTrue($config->uiEnabled);
    }

    public function testUiSecretFallsBackToAccessToken(): void
    {
        $this->setEnv('ACCESS_SWITCH_TOKEN', 'api-secret');

        $config = Config::fromEnvironment();

        $this->assertSame('api-secret', $config->uiSessionSecret);
    }

    public function testUiSecretUsesDedicatedValueWhenSet(): void
    {
        $this->setEnv('ACCESS_SWITCH_TOKEN', 'api-secret');
        $this->setEnv('ACCESS_SWITCH_UI_SECRET', 'ui-secret');

        $config = Config::fromEnvironment();

        $this->assertSame('api-secret', $config->accessSwitchToken);
        $this->assertSame('ui-secret', $config->uiSessionSecret);
    }

    private function setEnv(string $key, string $value): void
    {
        if (!array_key_exists($key, $this->saved)) {
            $previous = getenv($key);
            $this->saved[$key] = $previous === false ? false : (string) $previous;
        }
        putenv($key . '=' . $value);
    }

    private function unsetEnv(string $key): void
    {
        if (!array_key_exists($key, $this->saved)) {
            $previous = getenv($key);
            $this->saved[$key] = $previous === false ? false : (string) $previous;
        }
        putenv($key);
    }
}
