<?php
declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\Tests\Integration\Settings\Service\Migration;

use WooCommerce\PayPalCommerce\ApiClient\Entity\SellerStatus;

class BrMigrationManagerTest extends AbstractMigrationManager {

	protected function getLegacyOnboardedMerchantSettings(): array {
		return array(
			'title'                                    => 'PayPal',
			'description'                              => 'Pay via PayPal.',
			'smart_button_locations'                   => array(
				'product',
				'cart',
				'checkout',
				'checkout-block-express',
				'cart-block',
			),
			'smart_button_enable_styling_per_location' => false,
			'pay_later_messaging_enabled'              => true,
			'pay_later_button_enabled'                 => true,
			'pay_later_button_locations'               => array(
				'product',
				'cart',
				'checkout',
				'checkout-block-express',
				'cart-block',
			),
			'pay_later_messaging_locations'            => array(
				'product',
				'cart',
				'checkout',
				'shop',
			),
			'brand_name'                               => 'WooCommerce PayPal Payments',
			'dcc_gateway_title'                        => 'Debit &amp; Credit Cards',
			'dcc_gateway_description'                  => 'Pay with your credit card.',
			'allow_local_apm_gateways'                 => true,
			'products_dcc_enabled'                     => '',
			'products_pui_enabled'                     => '',
			'sandbox_on'                               => true,
			'merchant_email_production'                => '',
			'merchant_id_production'                   => '',
			'client_id_production'                     => '',
			'client_secret_production'                 => '',
			'merchant_email_sandbox'                   => 'example@business.example.com',
			'merchant_id_sandbox'                      => 'SOME_MERCHANT_ID',
			'client_id_sandbox'                        => 'ABC123',
			'client_secret_sandbox'                    => 'XYZ789',
			'soft_descriptor'                          => '',
			'prefix'                                   => 'bcfcfd-',
			'logging_enabled'                          => false,
			'stay_updated'                             => true,
			'subtotal_mismatch_behavior'               => 'extra_line',
			'subtotal_mismatch_line_name'              => '',
			'uninstall_clear_db_on_uninstall'          => '',
			'client_id'                                => 'ABC123',
			'client_secret'                            => 'XYZ789',
			'merchant_id'                              => 'SOME_MERCHANT_ID',
			'merchant_email'                           => 'example@business.example.com',
			'vault_enabled'                            => false,
			'vault_enabled_dcc'                        => false,
			'fraudnet_enabled'                         => true,
			'intent'                                   => 'capture',
			'capture_on_status_change'                 => '',
			'capture_for_virtual_only'                 => '',
			'landing_page'                             => 'LOGIN',
			'disable_funding'                          => array(),
			'card_billing_data_mode'                   => 'minimal_input',
			'allow_card_button_gateway'                => true,
			'subscriptions_mode'                       => 'vaulting_api',
			'blocks_final_review_enabled'              => true,
			'smart_button_language'                    => '',
			'button_general_layout'                    => 'vertical',
			'button_general_tagline'                   => '',
			'button_general_label'                     => 'paypal',
			'button_general_color'                     => 'gold',
			'button_general_shape'                     => 'rect',
			'button_layout'                            => 'vertical',
			'button_tagline'                           => '',
			'button_label'                             => 'paypal',
			'button_color'                             => 'gold',
			'button_shape'                             => 'rect',
			'button_product_layout'                    => 'horizontal',
			'button_product_tagline'                   => '',
			'button_product_label'                     => 'paypal',
			'button_product_color'                     => 'gold',
			'button_product_shape'                     => 'rect',
			'button_cart_layout'                       => 'horizontal',
			'button_cart_tagline'                      => '',
			'button_cart_label'                        => 'paypal',
			'button_cart_color'                        => 'gold',
			'button_cart_shape'                        => 'rect',
			'button_mini-cart_layout'                  => 'vertical',
			'button_mini-cart_tagline'                 => '',
			'button_mini-cart_label'                   => 'paypal',
			'button_mini-cart_color'                   => 'gold',
			'button_mini-cart_shape'                   => 'rect',
			'button_mini-cart_height'                  => 35,
			'button_checkout-block-express_label'      => 'paypal',
			'button_checkout-block-express_color'      => 'gold',
			'button_checkout-block-express_shape'      => 'rect',
			'button_checkout-block-express_height'     => 48,
			'button_cart-block_label'                  => 'paypal',
			'button_cart-block_color'                  => 'gold',
			'button_cart-block_shape'                  => 'rect',
			'button_cart-block_height'                 => 48,
			'enabled'                                  => true,
		);
	}

	protected function createSellerStatusMock(): SellerStatus {
		$seller_status = $this->createMock( SellerStatus::class );

		$seller_status->method( 'country' )->willReturn( 'BR' );
		$seller_status->method( 'capabilities' )->willReturn( [] );
		$seller_status->method( 'products' )->willReturn( [] );

		return $seller_status;
	}

	protected function assertNewGeneralSettings(): void {
		$settings = get_option( self::NEW_GENERAL_SETTINGS_OPTION );

		$this->assertIsArray( $settings );
		$this->assertTrue( $settings['sandbox_merchant'] );
		$this->assertTrue( $settings['merchant_connected'] );
		$this->assertEquals( 'SOME_MERCHANT_ID', $settings['merchant_id'] );
		$this->assertEquals( 'example@business.example.com', $settings['merchant_email'] );
		$this->assertEquals( 'ABC123', $settings['client_id'] );
		$this->assertEquals( 'XYZ789', $settings['client_secret'] );
		$this->assertEquals( 'unknown', $settings['seller_type'] );
		$this->assertEquals( 'BR', $settings['merchant_country'] );
	}

	protected function assertNewDataSettings(): void {
		$settings = get_option( self::NEW_DATA_SETTINGS_OPTION );

		$this->assertIsArray( $settings );
		$this->assertEquals( 'WooCommerce PayPal Payments', $settings['brand_name'] );
		$this->assertEquals( 'login', $settings['landing_page'] );
		$this->assertEquals( 'no-3d-secure', $settings['three_d_secure'] );
		$this->assertFalse( $settings['save_paypal_and_venmo'] );
		$this->assertFalse( $settings['save_card_details'] );
		$this->assertTrue( $settings['stay_updated'] );
	}

	protected function assertNewStylingSettings(): void {
		$settings = get_option( self::NEW_STYLING_OPTION );

		$this->assertIsArray( $settings );

		$this->assertArrayHasKey( 'cart', $settings );
		$this->assertTrue( $settings['cart']->enabled );
		$this->assertContains( 'ppcp-gateway', $settings['cart']->methods );
		$this->assertContains( 'pay-later', $settings['cart']->methods );
		$this->assertContains( 'venmo', $settings['cart']->methods );
		$this->assertEquals( 'rect', $settings['cart']->shape );
		$this->assertEquals( 'paypal', $settings['cart']->label );
		$this->assertEquals( 'gold', $settings['cart']->color );
		$this->assertEquals( 'vertical', $settings['cart']->layout );

		$this->assertArrayHasKey( 'product', $settings );
		$this->assertTrue( $settings['product']->enabled );
		$this->assertContains( 'ppcp-gateway', $settings['product']->methods );
		$this->assertContains( 'pay-later', $settings['product']->methods );
		$this->assertContains( 'venmo', $settings['product']->methods );

		$this->assertArrayHasKey( 'classic_checkout', $settings );
		$this->assertTrue( $settings['classic_checkout']->enabled );

		$this->assertArrayHasKey( 'express_checkout', $settings );
		$this->assertTrue( $settings['express_checkout']->enabled );

		$this->assertArrayHasKey( 'mini_cart', $settings );
	}

	protected function assertNewPaymentSettings(): void {
		$settings = get_option( self::NEW_PAYMENT_OPTION );

		$this->assertIsArray( $settings );
		$this->assertTrue( $settings['venmo_enabled'] );
		$this->assertTrue( $settings['paylater_enabled'] );
	}
}
