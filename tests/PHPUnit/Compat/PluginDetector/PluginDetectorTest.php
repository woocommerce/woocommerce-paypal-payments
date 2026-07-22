<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Compat\PluginDetector;

use WooCommerce\PayPalCommerce\TestCase;

/**
 * @covers \WooCommerce\PayPalCommerce\Compat\PluginDetector\PluginDetector
 */
class PluginDetectorTest extends TestCase {

	private PluginDetector $sut;

	public function setUp(): void {
		parent::setUp();

		$this->sut = new PluginDetector();
	}

	/**
	 * The real markers aren't loaded in this environment, so this only
	 * locks in the result shape (keys, bool values), not detection itself.
	 *
	 * @test
	 */
	public function test_scan_returns_bool_for_every_known_plugin_key(): void {
		$result = $this->sut->scan();

		$this->assertSame(
			array(
				'woocommerce-subscriptions',
				'woocommerce-gift-cards',
				'woocommerce-product-bundles',
				'woocommerce-product-addons',
				'woocommerce-min-max-quantities',
				'woocommerce-composite-products',
				'woocommerce-shipping-per-product',
				'woocommerce-deposits',
			),
			array_keys( $result )
		);

		foreach ( $result as $plugin => $is_active ) {
			$this->assertIsBool( $is_active, "Expected boolean for \"{$plugin}\"." );
			$this->assertFalse( $is_active, "Expected \"{$plugin}\" to be inactive in the unit test environment." );
		}
	}
}
