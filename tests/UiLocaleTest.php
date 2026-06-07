<?php

declare(strict_types=1);

namespace AccessSwitch\Tests;

use AccessSwitch\UiLocale;

final class UiLocaleTest extends TestCase
{
    protected function tearDown(): void
    {
        $ref = new \ReflectionClass(UiLocale::class);
        $prop = $ref->getProperty('catalogCache');
        $prop->setValue(null, []);

        parent::tearDown();
    }

    public function testSupportedLanguages(): void
    {
        $this->assertContains('fr', UiLocale::SUPPORTED);
        $this->assertContains('en', UiLocale::SUPPORTED);
        $this->assertContains('zh', UiLocale::SUPPORTED);
        $this->assertTrue(UiLocale::isSupported('fr'));
        $this->assertFalse(UiLocale::isSupported('xx'));
    }

    public function testResolvePrefersLangCookie(): void
    {
        $cookie = 'access_switch_lang=en; other=1';

        $this->assertSame('en', UiLocale::resolve($cookie, 'fr-FR,fr;q=0.9'));
    }

    public function testResolveUsesAcceptLanguageWhenNoCookie(): void
    {
        $this->assertSame('de', UiLocale::resolve(null, 'de-DE,de;q=0.9,en;q=0.8'));
        $this->assertSame('fr', UiLocale::resolve(null, null));
    }

    public function testNormalizeRegionalTags(): void
    {
        $this->assertSame('en', UiLocale::resolve(null, 'en-US,en;q=0.9'));
        $this->assertSame('pt', UiLocale::resolve(null, 'pt-BR,pt;q=0.9'));
        $this->assertSame('zh', UiLocale::resolve(null, 'zh-CN,zh;q=0.9'));
    }

    public function testGetTranslatesWithVariables(): void
    {
        $text = UiLocale::get('fr', 'error.state_read_failed', ['service' => 'demo']);

        $this->assertStringContainsString('demo', $text);
    }

    public function testUiStringsExcludeErrors(): void
    {
        $strings = UiLocale::uiStrings('fr');

        $this->assertArrayHasKey('login.submit', $strings);
        $this->assertArrayNotHasKey('error.unauthorized', $strings);
    }

    public function testAllSupportedCatalogsLoad(): void
    {
        foreach (UiLocale::SUPPORTED as $lang) {
            $catalog = UiLocale::catalog($lang);
            $this->assertArrayHasKey('login.submit', $catalog);
            $this->assertArrayHasKey('error.unauthorized', $catalog);
        }
    }

    public function testIsSupportedDoesNotRecurse(): void
    {
        $start = hrtime(true);
        $this->assertTrue(UiLocale::isSupported('fr'));
        $this->assertTrue(UiLocale::isSupported('en-US'));
        $this->assertFalse(UiLocale::isSupported('xx'));
        $this->assertLessThan(100_000_000, hrtime(true) - $start);
    }

    public function testSetCookieHeadersRejectsUnknownLanguage(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        UiLocale::setCookieHeaders('xx', false);
    }
}
