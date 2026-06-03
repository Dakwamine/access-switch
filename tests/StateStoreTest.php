<?php

declare(strict_types=1);

namespace AccessSwitch\Tests;

use AccessSwitch\StateStore;
use RuntimeException;

final class StateStoreTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = $this->createTempStatePath();
    }

    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            @chmod($this->path, 0644);
            @unlink($this->path);
        }
        $dir = dirname($this->path);
        if (is_dir($dir)) {
            @rmdir($dir);
        }
    }

    public function testDefaultsWhenFileMissing(): void
    {
        $store = new StateStore($this->path, false);
        $this->assertFalse($store->isOpen());
        $this->assertSame(['open' => false], $store->read());
    }

    public function testDefaultOpenWhenFileMissing(): void
    {
        $store = new StateStore($this->path, true);
        $this->assertTrue($store->isOpen());
    }

    public function testSetAndReadOpen(): void
    {
        $store = new StateStore($this->path, false);
        $store->setOpen(true);
        $this->assertTrue($store->isOpen());

        $state = $store->read();
        $this->assertTrue($state['open']);
        $this->assertArrayHasKey('updated_at', $state);
    }

    public function testInvalidJsonFallsBackToDefault(): void
    {
        file_put_contents($this->path, 'not-json');
        $store = new StateStore($this->path, false);
        $this->assertFalse($store->isOpen());
    }

    public function testThrowsWhenFileUnreadable(): void
    {
        file_put_contents($this->path, '{"open":true}');
        chmod($this->path, 0000);

        $store = new StateStore($this->path, false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot read state file');
        $store->read();
    }
}
