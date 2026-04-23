<?php
declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\Tests\Integration\Settings\Service\Migration;

use WooCommerce\PayPalCommerce\Settings\Service\Migration\MigrationManager;
use WooCommerce\PayPalCommerce\Settings\Service\Migration\SettingsMigration;
use WooCommerce\PayPalCommerce\Tests\Integration\TestCase;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PartnersEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\SellerStatus;
use WooCommerce\PayPalCommerce\Settings\Service\Migration\SettingsTabMigration;
use WooCommerce\PayPalCommerce\Settings\Service\Migration\StylingSettingsMigration;
use WooCommerce\PayPalCommerce\Settings\Service\Migration\PaymentSettingsMigration;
use WooCommerce\PayPalCommerce\Settings\Service\Migration\FastlaneSettingsMigration;

abstract class AbstractMigrationManager extends TestCase {

	protected const OLD_SETTINGS_OPTION = 'woocommerce-ppcp-settings';
	protected const NEW_GENERAL_SETTINGS_OPTION = 'woocommerce-ppcp-data-common';
	protected const NEW_DATA_SETTINGS_OPTION = 'woocommerce-ppcp-data-settings';
	protected const NEW_STYLING_OPTION = 'woocommerce-ppcp-data-styling';
	protected const NEW_PAYMENT_OPTION = 'woocommerce-ppcp-data-payment';

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

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

	protected function deleteOptions(): void {
		delete_option( self::OLD_SETTINGS_OPTION );
		delete_option( self::NEW_GENERAL_SETTINGS_OPTION );
		delete_option( self::NEW_DATA_SETTINGS_OPTION );
		delete_option( self::NEW_STYLING_OPTION );
		delete_option( self::NEW_PAYMENT_OPTION );
		delete_option( MigrationManager::OPTION_NAME_MIGRATION_IS_DONE );
	}

	protected function createMigrationManager( $container ): MigrationManager {
		$old_settings = get_option( self::OLD_SETTINGS_OPTION, array() );

		return new MigrationManager(
			$this->createSettingsMigration( $container, $old_settings ),
			$this->createSettingsTabMigration( $container, $old_settings ),
			$this->createStylingSettingsMigration( $container, $old_settings ),
			$this->createPaymentSettingsMigration( $container, $old_settings ),
			$this->createFastlaneSettingsMigration( $container, $old_settings ),
			$container->get( 'settings.data.onboarding' ),
			$container->get( 'woocommerce.logger.woocommerce' )
		);
	}

	protected function createSettingsMigration( $container, $old_settings ): SettingsMigration {
		$partners_endpoint = $this->createMock( PartnersEndpoint::class );
		$seller_status     = $this->createSellerStatusMock();

		$partners_endpoint->method( 'seller_status' )->willReturn( $seller_status );

		return new SettingsMigration(
			$old_settings,
			$container->get( 'settings.data.general' ),
			$partners_endpoint,
			$container->get( 'woocommerce.logger.woocommerce' ),
			$container->get( 'settings.service.seller-type-resolver' )
		);
	}

	protected function createSettingsTabMigration( $container, $old_settings ) {
		return new SettingsTabMigration(
			$old_settings,
			$container->get( 'settings.data.settings' )
		);
	}

	protected function createStylingSettingsMigration( $container, $old_settings ) {
		return new StylingSettingsMigration(
			$old_settings,
			$container->get( 'settings.data.styling' )
		);
	}

	protected function createPaymentSettingsMigration( $container, $old_settings ) {
		return new PaymentSettingsMigration(
			$old_settings,
			$container->get( 'settings.data.payment' ),
			$container->get( 'api.helpers.dccapplies' ),
			$container->get( 'wcgateway.helper.dcc-product-status' ),
			$container->get( 'wcgateway.configuration.card-configuration' ),
			$container->get( 'ppcp-local-apms.payment-methods' )
		);
	}

	protected function createFastlaneSettingsMigration( $container, $old_settings ) {
		return new FastlaneSettingsMigration(
			$old_settings,
			$container->get( 'settings.data.fastlane' )
		);
	}

	abstract protected function getLegacyOnboardedMerchantSettings(): array;

	abstract protected function createSellerStatusMock(): SellerStatus;

	abstract protected function assertNewGeneralSettings(): void;

	abstract protected function assertNewDataSettings(): void;

	abstract protected function assertNewStylingSettings(): void;

	abstract protected function assertNewPaymentSettings(): void;

	protected function getBaseLegacyOnboardedMerchantSettings(): array {
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
			'client_secret_sandbox'                    => 'XYZ789',
			'client_id_sandbox'                        => 'ABC123',
			'client_secret'                            => 'XYZ789',
			'client_id'                                => 'ABC123',
			'disable_funding'                          => array(),
			'merchant_id'                              => 'SOME_MERCHANT_ID',
			'merchant_email'                           => 'example@business.example.com',
			'merchant_id_sandbox'                      => 'SOME_MERCHANT_ID',
			'merchant_email_sandbox'                   => 'example@business.example.com',
			'intent'                                   => 'capture',
			'landing_page'                             => 'LOGIN',
			'card_billing_data_mode'                   => 'use_wc',
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

	protected function assertBaseGeneralSettings(): void {
		$settings = get_option( self::NEW_GENERAL_SETTINGS_OPTION );

		$this->assertIsArray( $settings );
		$this->assertTrue( $settings['sandbox_merchant'] );
		$this->assertTrue( $settings['merchant_connected'] );
		$this->assertEquals( 'SOME_MERCHANT_ID', $settings['merchant_id'] );
		$this->assertEquals( 'example@business.example.com', $settings['merchant_email'] );
		$this->assertEquals( 'ABC123', $settings['client_id'] );
		$this->assertEquals( 'XYZ789', $settings['client_secret'] );
	}

	protected function assertBaseDataSettings(): void {
		$settings = get_option( self::NEW_DATA_SETTINGS_OPTION );

		$this->assertIsArray( $settings );
		$this->assertEquals( 'WooCommerce PayPal Payments', $settings['brand_name'] );
		$this->assertEquals( 'login', $settings['landing_page'] );
		$this->assertEquals( 'no-3d-secure', $settings['three_d_secure'] );
	}

	protected function assertBaseStylingSettings(): void {
		$settings = get_option( self::NEW_STYLING_OPTION );

		$this->assertIsArray( $settings );

		$this->assertArrayHasKey( 'cart', $settings );
		$this->assertTrue( $settings['cart']->enabled );
		$this->assertContains( 'ppcp-gateway', $settings['cart']->methods );
		$this->assertContains( 'pay-later', $settings['cart']->methods );
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

	protected function assertBasePaymentSettings(): void {
		$settings = get_option( self::NEW_PAYMENT_OPTION );

		$this->assertIsArray( $settings );
		$this->assertTrue( $settings['venmo_enabled'] );
		$this->assertTrue( $settings['paylater_enabled'] );
	}

}
