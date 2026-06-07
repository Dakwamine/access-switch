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
}
