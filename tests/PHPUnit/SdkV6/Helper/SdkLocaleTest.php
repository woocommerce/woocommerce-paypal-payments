<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\SdkV6\Helper;

use WooCommerce\PayPalCommerce\TestCase;

class SdkLocaleTest extends TestCase
{
    /**
     * GIVEN a WordPress locale (full, with variant suffix, bare language or empty)
     * WHEN formatting it for the v6 SDK
     * THEN a language-region pair without variant suffix is returned, or '' when there is no region
     *
     * @dataProvider wp_locale_provider
     */
    public function testMapsWordPressLocaleToSdkLocale(string $wp_locale, string $expected): void
    {
        $this->assertSame($expected, SdkLocale::from_wp_locale($wp_locale));
    }

    public function wp_locale_provider(): array
    {
        return [
            'region uses hyphen' => ['en_US', 'en-US'],
            'formal variant dropped' => ['de_DE_formal', 'de-DE'],
            'bare language' => ['ja', ''],
            'empty locale' => ['', ''],
        ];
    }
}
