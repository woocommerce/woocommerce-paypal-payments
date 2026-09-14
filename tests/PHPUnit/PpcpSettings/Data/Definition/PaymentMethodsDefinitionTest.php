<?php
declare(strict_types=1);

namespace PHPUnit\PpcpSettings\Data\Definition;

use Mockery;
use WooCommerce\PayPalCommerce\Settings\Data\Definition\PaymentMethodsDefinition;
use WooCommerce\PayPalCommerce\Settings\Data\GeneralSettings;
use WooCommerce\PayPalCommerce\Settings\Data\PaymentSettings;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use function Brain\Monkey\Functions\when;

/**
 * @covers \WooCommerce\PayPalCommerce\Settings\Data\Definition\PaymentMethodsDefinition
 */
class PaymentMethodsDefinitionTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        when('apply_filters')->returnArg(2);
    }

    /**
     * Builds a PaymentSettings double whose ACDC-related getters return the
     * given values; every other getter the group might touch returns a safe default.
     */
    private function create_payment_settings(bool $cardholder_name, bool $show_card_logos): PaymentSettings
    {
        $settings = Mockery::mock(PaymentSettings::class);
        $settings->shouldReceive('get_cardholder_name')->andReturn($cardholder_name);
        $settings->shouldReceive('get_show_card_logos')->andReturn($show_card_logos);
        $settings->shouldReceive('get_fastlane_display_watermark')->andReturn(false);

        return $settings;
    }

    /**
     * Builds a GeneralSettings double that reports the store as not being
     * restricted to PayPal's own brand, so the ACDC group entry is produced.
     */
    private function create_general_settings_allowing_acdc(): GeneralSettings
    {
        $settings = Mockery::mock(GeneralSettings::class);
        $settings->shouldReceive('own_brand_only')->andReturn(false);

        return $settings;
    }

    private function acdc_fields(PaymentMethodsDefinition $definition): array
    {
        $group = $definition->group_card_methods();

        foreach ($group as $method) {
            if ($method['id'] === CreditCardGateway::ID) {
                return $method['fields'];
            }
        }

        $this->fail('ACDC group entry was not found in group_card_methods() output.');
    }

    /**
     * GIVEN the v6 SDK is not active (the default, v5 mode)
     * WHEN the ACDC group entry is built
     * THEN the fields include a "Display cardholder name" toggle
     * AND its default reflects the merchant's stored cardholder-name setting
     */
    public function testCardholderNameToggleIsOfferedUnderV5(): void
    {
        $settings = $this->create_payment_settings(true, false);
        $general_settings = $this->create_general_settings_allowing_acdc();

        $definition = new PaymentMethodsDefinition($settings, $general_settings);

        $fields = $this->acdc_fields($definition);

        $this->assertArrayHasKey('cardholderName', $fields);
        $this->assertSame(true, $fields['cardholderName']['default']);
        $this->assertArrayHasKey('showCardLogos', $fields, 'showCardLogos must remain present alongside cardholderName');
    }

    /**
     * GIVEN the v6 SDK is active
     * WHEN the ACDC group entry is built
     * THEN the fields no longer include the "Display cardholder name" toggle
     * AND showCardLogos is still present
     */
    public function testCardholderNameToggleIsDroppedUnderV6(): void
    {
        $settings = $this->create_payment_settings(true, true);
        $general_settings = $this->create_general_settings_allowing_acdc();

        $definition = new PaymentMethodsDefinition(
            $settings,
            $general_settings,
            '',
            '',
            true
        );

        $fields = $this->acdc_fields($definition);

        $this->assertArrayNotHasKey('cardholderName', $fields);
        $this->assertArrayHasKey('showCardLogos', $fields);
        $this->assertSame(true, $fields['showCardLogos']['default']);
    }
}
