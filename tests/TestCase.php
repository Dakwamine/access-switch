<?php

declare(strict_types=1);

namespace AccessSwitch\Tests;

use AccessSwitch\Http\Response;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

abstract class TestCase extends PHPUnitTestCase
{
    protected function createTempStatePath(): string
    {
        $dir = sys_get_temp_dir() . '/access-switch-test-' . uniqid('', true);
        mkdir($dir, 0777, true);

        return $dir . '/state.json';
    }

    /** @return array<string, mixed> */
    protected function decodeJsonResponse(Response $response): array
    {
        $this->assertNotSame('', $response->body);

        $data = json_decode($response->body, true);
        $this->assertIsArray($data);

        return $data;
    }
}
