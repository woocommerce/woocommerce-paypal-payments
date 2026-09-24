<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\SdkV6\Helper;

use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Filters\expectApplied;

class MerchantCountrySupportTest extends TestCase
{
    private const FILTER = 'woocommerce_paypal_payments_sdk_v6_unsupported_countries';

    /**
     * GIVEN no third party filters the unsupported-countries list
     * WHEN checking whether the v6 SDK may load for a merchant
     * THEN the merchant is supported, including one based in Mexico, the only country
     *      this list ever withheld by default
     *
     * @dataProvider merchant_country_provider
     */
    public function testNoCountryIsWithheldByDefault(string $merchant_country): void
    {
        $testee = new MerchantCountrySupport($merchant_country);

        $this->assertTrue($testee->is_supported());
    }

    public function merchant_country_provider(): array
    {
        return [
            'Mexico, the country this list used to withhold' => ['MX'],
            'a country never withheld by this list' => ['US'],
        ];
    }

    /**
     * GIVEN a third party widens the unsupported-countries filter to include Brazil
     * WHEN checking whether the v6 SDK may load for merchants in and outside Brazil
     * THEN only the filtered-in Brazilian merchant is withheld from the v6 stack
     *
     * @dataProvider filtered_country_provider
     */
    public function testFilterCanWidenListToWithholdAnotherCountry(string $merchant_country, bool $expected_supported): void
    {
        expectApplied(self::FILTER)->andReturn(['BR']);

        $testee = new MerchantCountrySupport($merchant_country);

        $this->assertSame($expected_supported, $testee->is_supported());
    }

    public function filtered_country_provider(): array
    {
        return [
            'the filtered-in country is excluded' => ['BR', false],
            'an unfiltered country stays supported' => ['US', true],
        ];
    }

    /**
     * GIVEN a third party filters the unsupported-countries list
     * WHEN the filter receives the default value and returns it unchanged
     * THEN the default of withholding no country is honoured
     */
    public function testFilterReceivesAndHonoursTheDefaultValue(): void
    {
        expectApplied(self::FILTER)->once()->with([])->andReturnArg(0);

        $testee = new MerchantCountrySupport('MX');

        $this->assertTrue($testee->is_supported());
    }
}
