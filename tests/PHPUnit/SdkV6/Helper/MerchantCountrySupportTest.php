<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\SdkV6\Helper;

use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Filters\expectApplied;

class MerchantCountrySupportTest extends TestCase
{
    private const FILTER = 'woocommerce_paypal_payments_sdk_v6_unsupported_countries';

    /**
     * GIVEN a merchant based in Mexico
     * WHEN checking whether the v6 SDK may load for them
     * THEN it is not supported, so they are served the v5 stack instead
     */
    public function testMexicanMerchantIsNotSupported(): void
    {
        $testee = new MerchantCountrySupport('MX');

        $this->assertFalse($testee->is_supported());
    }

    /**
     * GIVEN a merchant based in the United States
     * WHEN checking whether the v6 SDK may load for them
     * THEN it is supported
     */
    public function testNonMexicanMerchantIsSupported(): void
    {
        $testee = new MerchantCountrySupport('US');

        $this->assertTrue($testee->is_supported());
    }

    /**
     * GIVEN a third party widens the unsupported-countries filter to include Brazil
     * WHEN checking whether the v6 SDK may load for a Brazilian merchant
     * THEN that merchant is no longer supported
     */
    public function testFilterCanWidenListToWithholdAnotherCountry(): void
    {
        expectApplied(self::FILTER)->andReturn(['MX', 'BR']);

        $testee = new MerchantCountrySupport('BR');

        $this->assertFalse($testee->is_supported());
    }

    /**
     * GIVEN a third party empties the unsupported-countries filter as an escape hatch
     * WHEN checking whether the v6 SDK may load for a Mexican merchant
     * THEN Mexico becomes supported again
     */
    public function testFilterCanEmptyListToRestoreMexicanSupport(): void
    {
        expectApplied(self::FILTER)->andReturn([]);

        $testee = new MerchantCountrySupport('MX');

        $this->assertTrue($testee->is_supported());
    }
}
