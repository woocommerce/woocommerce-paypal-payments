<?php
declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\Tests\Integration\Settings\Service\Migration;

use WooCommerce\PayPalCommerce\ApiClient\Entity\SellerStatus;
use WooCommerce\PayPalCommerce\ApiClient\Entity\SellerStatusCapability;

class UsMigrationManagerTest extends AbstractMigrationManager {

	protected function getLegacyOnboardedMerchantSettings(): array {
		return array_merge(
			$this->getBaseLegacyOnboardedMerchantSettings(),
			array(
				'products_dcc_enabled'                 => 'yes',
				'products_pui_enabled'                 => '',
				'vault_enabled'                        => true,
				'products_apple_enabled'               => 'yes',
				'products_googlepay_enabled'           => 'yes',
				'allow_card_button_gateway'            => '',
				'dcc_enabled'                          => true,
				'disable_cards'                        => array(),
				'card_icons'                           => array(),
				'dcc_name_on_card'                     => 'yes',
				'3d_secure_contingency'                => 'NO_3D_SECURE',
				'vault_enabled_dcc'                    => true,
				'axo_enabled'                          => '',
				'axo_style_root_bg_color'              => '',
				'axo_style_root_error_color'           => '',
				'axo_style_root_font_family'           => '',
				'axo_style_root_text_color_base'       => '',
				'axo_style_root_font_size_base'        => '',
				'axo_style_root_padding'               => '',
				'axo_style_root_primary_color'         => '',
				'axo_style_input_bg_color'             => '',
				'axo_style_input_border_radius'        => '',
				'axo_style_input_border_color'         => '',
				'axo_style_input_border_width'         => '',
				'axo_style_input_text_color_base'      => '',
				'axo_style_input_focus_border_color'   => '',
				'googlepay_button_enabled'             => true,
				'googlepay_button_type'                => 'plain',
				'googlepay_button_color'               => 'black',
				'googlepay_button_language'            => 'en',
				'googlepay_button_shipping_enabled'    => '',
				'applepay_button_enabled'              => true,
				'applepay_button_type'                 => 'plain',
				'applepay_button_color'                => 'black',
				'applepay_button_language'             => '',
				'applepay_checkout_data_mode'          => 'use_wc',
				'capture_for_virtual_only'            => true,
			)
		);
	}

	protected function createSellerStatusMock(): SellerStatus {
		$seller_status = $this->createMock( SellerStatus::class );
		$capability    = $this->createMock( SellerStatusCapability::class );

		$capability->method( 'name' )->willReturn( 'COMMERCIAL_ENTITY' );
		$capability->method( 'status' )->willReturn( 'ACTIVE' );

		$seller_status->method( 'country' )->willReturn( 'US' );
		$seller_status->method( 'capabilities' )->willReturn( [ $capability ] );
		$seller_status->method( 'products' )->willReturn( [] );

		return $seller_status;
	}

	protected function assertNewGeneralSettings(): void {
		$this->assertBaseGeneralSettings();

		$settings = get_option( self::NEW_GENERAL_SETTINGS_OPTION );
		$this->assertEquals( 'business', $settings['seller_type'] );
		$this->assertEquals( 'US', $settings['merchant_country'] );
	}

	protected function assertNewDataSettings(): void {
		$this->assertBaseDataSettings();

		$settings = get_option( self::NEW_DATA_SETTINGS_OPTION );
		$this->assertTrue( $settings['capture_virtual_orders'] );
		$this->assertTrue( $settings['save_paypal_and_venmo'] );
		$this->assertTrue( $settings['save_card_details'] );
	}

	protected function assertNewStylingSettings(): void {
		$this->assertBaseStylingSettings();

		$settings = get_option( self::NEW_STYLING_OPTION );
		$this->assertContains( 'ppcp-applepay', $settings['cart']->methods );
		$this->assertContains( 'ppcp-googlepay', $settings['cart']->methods );
	}

	protected function assertNewPaymentSettings(): void {
		$this->assertBasePaymentSettings();
	}
}
