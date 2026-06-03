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
        $this->unsetEnv('STATE_FILE');
        $this->unsetEnv('ACCESS_SWITCH_TOKEN');
        $this->unsetEnv('DEFAULT_OPEN');

        $config = Config::fromEnvironment();

        $this->assertSame('/data/state.json', $config->stateFile);
        $this->assertSame('', $config->accessSwitchToken);
        $this->assertFalse($config->defaultOpen);
    }

    public function testFromEnvironmentReadsVariables(): void
    {
        $this->setEnv('STATE_FILE', '/tmp/custom.json');
        $this->setEnv('ACCESS_SWITCH_TOKEN', 'secret');
        $this->setEnv('DEFAULT_OPEN', 'true');

        $config = Config::fromEnvironment();

        $this->assertSame('/tmp/custom.json', $config->stateFile);
        $this->assertSame('secret', $config->accessSwitchToken);
        $this->assertTrue($config->defaultOpen);
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
