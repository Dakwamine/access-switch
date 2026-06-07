<?php

declare(strict_types=1);

namespace AccessSwitch\Tests;

use AccessSwitch\JsonFile;
use RuntimeException;

final class JsonFileTest extends TestCase
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
        $state = JsonFile::read($this->path, ['open' => false]);
        $this->assertSame(['open' => false], $state);
    }

    public function testWriteAndRead(): void
    {
        JsonFile::write($this->path, ['open' => true, 'updated_at' => '2026-01-01T00:00:00+00:00']);

        $state = JsonFile::read($this->path, ['open' => false]);
        $this->assertTrue($state['open']);
        $this->assertSame('2026-01-01T00:00:00+00:00', $state['updated_at']);
    }

    public function testInvalidJsonFallsBackToDefault(): void
    {
        file_put_contents($this->path, 'not-json');
        $state = JsonFile::read($this->path, ['open' => false]);
        $this->assertFalse($state['open']);
    }

    public function testThrowsWhenFileUnreadable(): void
    {
        file_put_contents($this->path, '{"open":true}');
        chmod($this->path, 0000);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot read file');
        JsonFile::read($this->path, ['open' => false]);
    }
}
