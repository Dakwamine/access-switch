<?php

declare(strict_types=1);

namespace AccessSwitch\Tests;

use AccessSwitch\ClientIp;

final class ClientIpTest extends TestCase
{
    public function testUsesRemoteAddrWhenProxiesNotTrusted(): void
    {
        $ip = ClientIp::resolve(
            '203.0.113.10',
            '198.51.100.1',
            '198.51.100.2',
            [],
        );

        $this->assertSame('203.0.113.10', $ip);
    }

    public function testIgnoresHeadersWhenRemoteAddrNotInTrustedList(): void
    {
        $ip = ClientIp::resolve(
            '203.0.113.10',
            '198.51.100.1',
            '198.51.100.2',
            ['172.18.0.2'],
        );

        $this->assertSame('203.0.113.10', $ip);
    }

    public function testPrefersXRealIpFromTrustedProxy(): void
    {
        $ip = ClientIp::resolve(
            '172.18.0.2',
            '198.51.100.1, 172.18.0.2',
            '198.51.100.99',
            ['172.18.0.0/16'],
        );

        $this->assertSame('198.51.100.99', $ip);
    }

    public function testUsesFirstXForwardedForWhenNoXRealIp(): void
    {
        $ip = ClientIp::resolve(
            '172.18.0.2',
            '198.51.100.1, 172.18.0.2',
            null,
            ['172.18.0.2'],
        );

        $this->assertSame('198.51.100.1', $ip);
    }

    public function testFallsBackToRemoteAddrWhenHeadersMissing(): void
    {
        $ip = ClientIp::resolve('172.18.0.2', null, null, ['172.18.0.2']);

        $this->assertSame('172.18.0.2', $ip);
    }
}
