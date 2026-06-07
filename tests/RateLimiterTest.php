<?php

declare(strict_types=1);

namespace AccessSwitch\Tests;

use AccessSwitch\RateLimiter;

final class RateLimiterTest extends TestCase
{
    protected function tearDown(): void
    {
        RateLimiter::reset();
    }

    public function testAllowsRequestsWithinLimit(): void
    {
        $limiter = new RateLimiter();

        $this->assertTrue($limiter->isAllowed('test', 3, 60));
        $this->assertTrue($limiter->isAllowed('test', 3, 60));
        $this->assertTrue($limiter->isAllowed('test', 3, 60));
        $this->assertFalse($limiter->isAllowed('test', 3, 60));
    }

    public function testDisablingLimitReturnsTrue(): void
    {
        $limiter = new RateLimiter();

        for ($i = 0; $i < 10; $i++) {
            $this->assertTrue($limiter->isAllowed('test', 0, 60));
        }
    }

    public function testWindowExpires(): void
    {
        $limiter = new RateLimiter();
        $base = 1_700_000_000;

        $this->assertTrue($limiter->isAllowed('test', 1, 2, $base));
        $this->assertFalse($limiter->isAllowed('test', 1, 2, $base));
        $this->assertTrue($limiter->isAllowed('test', 1, 2, $base + 2));
    }

    public function testIsBlockedWithoutRecording(): void
    {
        $limiter = new RateLimiter();
        $limiter->recordAttempt('test', 60, 100);
        $limiter->recordAttempt('test', 60, 101);
        $limiter->recordAttempt('test', 60, 102);

        $this->assertTrue($limiter->isBlocked('test', 3, 60, 103));
        $this->assertFalse($limiter->isBlocked('other', 3, 60, 103));
    }

    public function testFileStoreAcceptsFloatTimestampsFromJson(): void
    {
        $dir = sys_get_temp_dir() . '/access-switch-rl-' . uniqid('', true);
        mkdir($dir, 0777, true);
        $path = $dir . '/' . hash('sha256', 'test') . '.json';
        file_put_contents($path, '[' . (float) (time() - 5) . ',' . (float) (time() - 3) . ']');

        $limiter = new RateLimiter($dir);
        $this->assertTrue($limiter->isBlocked('test', 2, 60));

        RateLimiter::reset($dir);
        @unlink($path);
        @rmdir($dir);
    }

    public function testFileStoreSharedAcrossInstances(): void
    {
        $dir = sys_get_temp_dir() . '/access-switch-rl-' . uniqid('', true);
        mkdir($dir, 0777, true);
        RateLimiter::reset($dir);

        $limiterA = new RateLimiter($dir);
        $limiterB = new RateLimiter($dir);

        $this->assertTrue($limiterA->isAllowed('test', 2, 60));
        $this->assertTrue($limiterB->isAllowed('test', 2, 60));
        $this->assertFalse($limiterA->isAllowed('test', 2, 60));

        RateLimiter::reset($dir);
        @rmdir($dir);
    }
}
