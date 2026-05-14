<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Abilities\Domain;

use WooCommerce\PayPalCommerce\Abilities\AbilitiesRegistrar;
use WooCommerce\PayPalCommerce\TestCase;

/**
 * Unit tests for the GetSettings ability shape.
 *
 * The delegate→REST→envelope path is exercised by the Phase V integration
 * harness against a real WC 10.9 install.
 */
class GetSettingsTest extends TestCase
{
	public function test_get_name_uses_the_extension_namespace(): void
	{
		$this->assertSame(
			'woocommerce-paypal-payments/get-settings',
			GetSettings::get_name()
		);
	}

	public function test_registration_args_are_zero_arg_read_only(): void
	{
		$args = GetSettings::get_registration_args();

		$this->assertSame(array( GetSettings::class, 'execute' ), $args['execute_callback']);
		$this->assertSame(array( AbilitiesRegistrar::class, 'can_manage_woocommerce' ), $args['permission_callback']);
		$this->assertSame(AbilitiesRegistrar::CATEGORY_SLUG, $args['category']);

		$this->assertSame(array(), $args['input_schema']['properties']);
		$this->assertFalse($args['input_schema']['additionalProperties']);

		$this->assertTrue($args['meta']['annotations']['readonly']);
		$this->assertFalse($args['meta']['annotations']['destructive']);
		$this->assertTrue($args['meta']['annotations']['idempotent']);
		$this->assertTrue($args['meta']['show_in_rest']);
		$this->assertTrue($args['meta']['mcp']['public']);
	}
}
