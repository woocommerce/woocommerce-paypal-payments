<?php
declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\Tests\Integration\Settings\Service\Migration;

use WooCommerce\PayPalCommerce\ApiClient\Entity\SellerStatus;

class BrMigrationManagerTest extends AbstractMigrationManager {

	protected function getLegacyOnboardedMerchantSettings(): array {
		return array_merge(
			$this->getBaseLegacyOnboardedMerchantSettings(),
			array(
				'products_dcc_enabled'           => '',
				'products_pui_enabled'           => '',
				'merchant_email_production'      => '',
				'merchant_id_production'         => '',
				'client_id_production'           => '',
				'client_secret_production'       => '',
				'soft_descriptor'                => '',
				'prefix'                         => 'bcfcfd-',
				'logging_enabled'                => false,
				'stay_updated'                   => true,
				'subtotal_mismatch_behavior'     => 'extra_line',
				'subtotal_mismatch_line_name'    => '',
				'uninstall_clear_db_on_uninstall' => '',
				'vault_enabled'                  => false,
				'vault_enabled_dcc'              => false,
				'fraudnet_enabled'               => true,
				'capture_on_status_change'       => '',
				'capture_for_virtual_only'       => false,
				'card_billing_data_mode'         => 'minimal_input',
				'allow_card_button_gateway'      => true,
			)
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
		$this->assertBaseGeneralSettings();

		$settings = get_option( self::NEW_GENERAL_SETTINGS_OPTION );
		$this->assertEquals( 'unknown', $settings['seller_type'] );
		$this->assertEquals( 'BR', $settings['merchant_country'] );
	}

	protected function assertNewDataSettings(): void {
		$this->assertBaseDataSettings();

		$settings = get_option( self::NEW_DATA_SETTINGS_OPTION );
		$this->assertFalse( $settings['save_paypal_and_venmo'] );
		$this->assertFalse( $settings['save_card_details'] );
		$this->assertTrue( $settings['stay_updated'] );
	}

	protected function assertNewStylingSettings(): void {
		$this->assertBaseStylingSettings();

		$settings = get_option( self::NEW_STYLING_OPTION );
		$this->assertContains( 'venmo', $settings['cart']->methods );
		$this->assertContains( 'venmo', $settings['product']->methods );
	}

	protected function assertNewPaymentSettings(): void {
		$this->assertBasePaymentSettings();
	}
}
