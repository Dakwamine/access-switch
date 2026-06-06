<?php

declare(strict_types=1);

namespace AccessSwitch\Tests;

use AccessSwitch\Http\Response;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

abstract class TestCase extends PHPUnitTestCase
{
    protected function createTempDataDir(): string
    {
        $dir = sys_get_temp_dir() . '/access-switch-test-' . uniqid('', true);
        mkdir($dir . '/states', 0777, true);

        return $dir;
    }

    protected function createTempStatePath(): string
    {
        $dir = $this->createTempDataDir();

        return $dir . '/state.json';
    }

    protected function removeTempDataDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @chmod($item->getPathname(), 0644);
                @unlink($item->getPathname());
            }
        }

        @rmdir($dir);
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
