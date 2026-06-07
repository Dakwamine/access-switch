<?php

declare(strict_types=1);

namespace AccessSwitch\Tests;

use AccessSwitch\UiSession;

final class UiSessionTest extends TestCase
{
    public function testCreateAndValidateCookie(): void
    {
        $session = new UiSession('secret', 3600, false);
        $value = $session->createValue();
        $headers = $session->setCookieHeaders($value);
        $cookieHeader = $headers['Set-Cookie'];

        $this->assertStringContainsString(UiSession::COOKIE_NAME . '=', $cookieHeader);
        $this->assertTrue($session->isValid($cookieHeader));
    }

    public function testRejectsTamperedCookie(): void
    {
        $session = new UiSession('secret', 3600, false);
        $value = $session->createValue();
        $tampered = substr($value, 0, -1) . 'x';
        $cookieHeader = UiSession::COOKIE_NAME . '=' . $tampered;

        $this->assertFalse($session->isValid($cookieHeader));
    }

    public function testRejectsExpiredCookie(): void
    {
        $session = new UiSession('secret', -10, false);
        $value = $session->createValue();
        $cookieHeader = UiSession::COOKIE_NAME . '=' . $value;

        $this->assertFalse($session->isValid($cookieHeader));
    }
}
