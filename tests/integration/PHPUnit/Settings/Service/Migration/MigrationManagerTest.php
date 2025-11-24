<?php
declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\Tests\Integration\Settings\Service\Migration;

use WooCommerce\PayPalCommerce\Settings\Service\Migration\MigrationManager;
use WooCommerce\PayPalCommerce\Settings\Service\Migration\SettingsMigration;
use WooCommerce\PayPalCommerce\Tests\Integration\TestCase;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PartnersEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\SellerStatus;
use WooCommerce\PayPalCommerce\ApiClient\Entity\SellerStatusCapability;
use WooCommerce\PayPalCommerce\Settings\Service\Migration\SettingsTabMigration;
use WooCommerce\PayPalCommerce\Settings\Service\Migration\StylingSettingsMigration;
use WooCommerce\PayPalCommerce\Settings\Service\Migration\PaymentSettingsMigration;
use WooCommerce\PayPalCommerce\Compat\Settings\SettingsMapHelper;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;

class MigrationManagerTest extends TestCase {

	private const OLD_SETTINGS_OPTION = 'woocommerce-ppcp-settings';
	private const NEW_GENERAL_SETTINGS_OPTION = 'woocommerce-ppcp-data-common';
	private const NEW_DATA_SETTINGS_OPTION = 'woocommerce-ppcp-data-settings';
	private const NEW_STYLING_OPTION = 'woocommerce-ppcp-data-styling';
	private const NEW_PAYMENT_OPTION = 'woocommerce-ppcp-data-payment';

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		// Disable the new settings module to ensure the Settings class reads from
		// the legacy WordPress option structure (woocommerce-ppcp-settings).
		// This is required for the migration test to properly read old settings values
		// before they are migrated to the new structure.
		add_filter( 'woocommerce.feature-flags.woocommerce_paypal_payments.settings_enabled', '__return_false' );
	}

	public static function tearDownAfterClass(): void {
		remove_filter( 'woocommerce.feature-flags.woocommerce_paypal_payments.settings_enabled', '__return_false' );
		parent::tearDownAfterClass();
	}

	protected function setUp(): void {
		parent::setUp();
		$this->deleteOptions();
	}

	public function tearDown(): void {
		$this->deleteOptions();
		parent::tearDown();
	}

	public function testMigrateOnboardedMerchantSettings(): void {
		$legacy_settings = $this->getLegacyOnboardedMerchantSettings();
		update_option( self::OLD_SETTINGS_OPTION, $legacy_settings );

		$container         = $this->getContainer();
		$migration_manager = $this->createMigrationManager( $container );

		$migration_manager->migrate();

		$this->assertNewGeneralSettings();
		$this->assertNewDataSettings();
		$this->assertNewStylingSettings();
		$this->assertNewPaymentSettings();
	}

	private function assertNewGeneralSettings(): void {
		$settings = get_option( self::NEW_GENERAL_SETTINGS_OPTION );

		$this->assertIsArray( $settings );
		$this->assertTrue( $settings['sandbox_merchant'] );
		$this->assertTrue( $settings['merchant_connected'] );
		$this->assertEquals( 'SOME_MERCHANT_ID', $settings['merchant_id'] );
		$this->assertEquals( 'example@business.example.com', $settings['merchant_email'] );
		$this->assertEquals( 'ABC123', $settings['client_id'] );
		$this->assertEquals( 'XYZ789', $settings['client_secret'] );
		$this->assertEquals( 'business', $settings['seller_type'] );
		$this->assertEquals( 'US', $settings['merchant_country'] );
	}

	private function assertNewDataSettings(): void {
		$settings = get_option( self::NEW_DATA_SETTINGS_OPTION );

		$this->assertIsArray( $settings );
		$this->assertEquals( 'WooCommerce PayPal Payments', $settings['brand_name'] );
		$this->assertEquals( 'login', $settings['landing_page'] );
		$this->assertEquals( 'no-3d-secure', $settings['three_d_secure'] );
		$this->assertTrue( $settings['capture_virtual_orders'] );
		$this->assertTrue( $settings['save_paypal_and_venmo'] );
		$this->assertTrue( $settings['save_card_details'] );
	}

	private function assertNewStylingSettings(): void {
		$settings = get_option( self::NEW_STYLING_OPTION );

		$this->assertIsArray( $settings );

		$this->assertArrayHasKey( 'cart', $settings );
		$this->assertTrue( $settings['cart']->enabled );
		$this->assertContains( 'ppcp-gateway', $settings['cart']->methods );
		$this->assertContains( 'pay-later', $settings['cart']->methods );
		$this->assertContains( 'ppcp-applepay', $settings['cart']->methods );
		$this->assertContains( 'ppcp-googlepay', $settings['cart']->methods );
		$this->assertEquals( 'rect', $settings['cart']->shape );
		$this->assertEquals( 'paypal', $settings['cart']->label );
		$this->assertEquals( 'gold', $settings['cart']->color );
		$this->assertEquals( 'vertical', $settings['cart']->layout );

		$this->assertArrayHasKey( 'product', $settings );
		$this->assertTrue( $settings['product']->enabled );
		$this->assertContains( 'ppcp-gateway', $settings['product']->methods );
		$this->assertContains( 'pay-later', $settings['product']->methods );

		$this->assertArrayHasKey( 'classic_checkout', $settings );
		$this->assertTrue( $settings['classic_checkout']->enabled );

		$this->assertArrayHasKey( 'express_checkout', $settings );
		$this->assertTrue( $settings['express_checkout']->enabled );

		$this->assertArrayHasKey( 'mini_cart', $settings );
	}

	private function assertNewPaymentSettings(): void {
		$settings = get_option( self::NEW_PAYMENT_OPTION );

		$this->assertIsArray( $settings );
		$this->assertTrue( $settings['venmo_enabled'] );
		$this->assertTrue( $settings['paylater_enabled'] );
	}

	private function getLegacyOnboardedMerchantSettings(): array {
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
			'sandbox_on'                               => true,
			'products_dcc_enabled'                     => 'yes',
			'products_pui_enabled'                     => '',
			'client_secret_sandbox'                    => 'XYZ789',
			'client_id_sandbox'                        => 'ABC123',
			'client_secret'                            => 'XYZ789',
			'client_id'                                => 'ABC123',
			'disable_funding'                          => array(),
			'merchant_id'                              => 'SOME_MERCHANT_ID',
			'merchant_email'                           => 'example@business.example.com',
			'merchant_id_sandbox'                      => 'SOME_MERCHANT_ID',
			'merchant_email_sandbox'                   => 'example@business.example.com',
			'vault_enabled'                            => true,
			'products_apple_enabled'                   => 'yes',
			'products_googlepay_enabled'               => 'yes',
			'intent'                                   => 'capture',
			'landing_page'                             => 'LOGIN',
			'card_billing_data_mode'                   => 'use_wc',
			'allow_card_button_gateway'                => '',
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
			'dcc_enabled'                              => true,
			'disable_cards'                            => array(),
			'card_icons'                               => array(),
			'dcc_name_on_card'                         => 'yes',
			'3d_secure_contingency'                    => 'NO_3D_SECURE',
			'vault_enabled_dcc'                        => true,
			'axo_enabled'                              => '',
			'axo_style_root_bg_color'                  => '',
			'axo_style_root_error_color'               => '',
			'axo_style_root_font_family'               => '',
			'axo_style_root_text_color_base'           => '',
			'axo_style_root_font_size_base'            => '',
			'axo_style_root_padding'                   => '',
			'axo_style_root_primary_color'             => '',
			'axo_style_input_bg_color'                 => '',
			'axo_style_input_border_radius'            => '',
			'axo_style_input_border_color'             => '',
			'axo_style_input_border_width'             => '',
			'axo_style_input_text_color_base'          => '',
			'axo_style_input_focus_border_color'       => '',
			'googlepay_button_enabled'                 => true,
			'googlepay_button_type'                    => 'plain',
			'googlepay_button_color'                   => 'black',
			'googlepay_button_language'                => 'en',
			'googlepay_button_shipping_enabled'        => '',
			'applepay_button_enabled'                  => true,
			'applepay_button_type'                     => 'plain',
			'applepay_button_color'                    => 'black',
			'applepay_button_language'                 => '',
			'applepay_checkout_data_mode'              => 'use_wc',
		);
	}

	private function deleteOptions(): void {
		delete_option( self::OLD_SETTINGS_OPTION );
		delete_option( self::NEW_GENERAL_SETTINGS_OPTION );
		delete_option( self::NEW_DATA_SETTINGS_OPTION );
		delete_option( self::NEW_STYLING_OPTION );
		delete_option( self::NEW_PAYMENT_OPTION );
	}

	private function createMigrationManager( $container ): MigrationManager {
		$old_settings = $this->createOldSettingsInstance( $container );

		return new MigrationManager(
			$this->createSettingsMigration( $container, $old_settings ),
			$this->createSettingsTabMigration( $container, $old_settings ),
			$this->createStylingSettingsMigration( $container, $old_settings ),
			$this->createPaymentSettingsMigration( $container, $old_settings )
		);
	}

	private function createSettingsMigration( $container, $old_settings ): SettingsMigration {
		$partners_endpoint = $this->createMock( PartnersEndpoint::class );
		$seller_status     = $this->createMock( SellerStatus::class );
		$capability        = $this->createMock( SellerStatusCapability::class );

		$capability->method( 'name' )->willReturn( 'COMMERCIAL_ENTITY' );
		$capability->method( 'status' )->willReturn( 'ACTIVE' );

		$seller_status->method( 'country' )->willReturn( 'US' );
		$seller_status->method( 'capabilities' )->willReturn( [ $capability ] );
		$seller_status->method( 'products' )->willReturn( [] );

		$partners_endpoint->method( 'seller_status' )->willReturn( $seller_status );

		return new SettingsMigration(
			$old_settings,
			$container->get( 'settings.data.general' ),
			$partners_endpoint
		);
	}

	private function createSettingsTabMigration( $container, $old_settings ) {
		return new SettingsTabMigration(
			$old_settings,
			$container->get( 'settings.data.settings' ),
			$container->get( 'compat.settings.settings_tab_map_helper' )
		);
	}

	private function createStylingSettingsMigration( $container, $old_settings ) {
		return new StylingSettingsMigration(
			$old_settings,
			$container->get( 'settings.data.styling' )
		);
	}

	private function createPaymentSettingsMigration( $container, $old_settings ) {
		return new PaymentSettingsMigration(
			$old_settings,
			$container->get( 'settings.data.payment' ),
			$container->get( 'api.helpers.dccapplies' ),
			$container->get( 'wcgateway.helper.dcc-product-status' ),
			$container->get( 'wcgateway.configuration.card-configuration' ),
			$container->get( 'ppcp-local-apms.payment-methods' )
		);
	}

	private function createOldSettingsInstance( $container ) {
		$settings_map_helper = $this->createMock( SettingsMapHelper::class );
		$settings_map_helper->method( 'has_mapped_key' )->willReturn( false );
		$settings_map_helper->method( 'mapped_value' )->willReturn( null );

		return new Settings(
			[],
			'',
			[],
			[],
			$settings_map_helper
		);
	}

}
