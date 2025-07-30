<?php
/**
 * Handles migration of payment settings from legacy format to new structure.
 *
 * @package WooCommerce\PayPalCommerce\Settings\Service\Migration
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Settings\Service\Migration;

use WooCommerce\PayPalCommerce\Applepay\ApplePayGateway;
use WooCommerce\PayPalCommerce\Axo\Gateway\AxoGateway;
use WooCommerce\PayPalCommerce\Googlepay\GooglePayGateway;
use WooCommerce\PayPalCommerce\Settings\Data\PaymentSettings;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;

/**
 * Class PaymentSettingsMigration
 *
 * Handles migration of payment settings.
 */
class PaymentSettingsMigration implements SettingsMigrationInterface {

	protected Settings $settings;
	protected PaymentSettings $payment_settings;

	/**
	 * The list of local apm methods.
	 *
	 * @var array<string, array>
	 */
	protected array $local_apms;

	public function __construct(
		Settings $settings,
		PaymentSettings $payment_settings,
		array $local_apms
	) {
		$this->settings         = $settings;
		$this->payment_settings = $payment_settings;
		$this->local_apms       = $local_apms;
	}

	public function migrate(): void {
		$allow_local_apm_gateways = $this->settings->has( 'allow_local_apm_gateways' ) && $this->settings->get( 'allow_local_apm_gateways' );

		if ( $this->settings->has( 'disable_funding' ) ) {
			$disable_funding = (array) $this->settings->get( 'disable_funding' );
			if ( ! in_array( 'venmo', $disable_funding, true ) ) {
				$this->payment_settings->toggle_method_state( 'venmo', true );
			}

			if ( ! $allow_local_apm_gateways ) {
				foreach ( $this->local_apms as $apm ) {
					if ( ! in_array( $apm['id'], $disable_funding, true ) ) {
						$this->payment_settings->toggle_method_state( $apm['id'], true );
					}
				}
			}
		}

		foreach ( $this->map() as $old_key => $method_name ) {
			if ( $this->settings->has( $old_key ) && $this->settings->get( $old_key ) ) {
				$this->payment_settings->toggle_method_state( $method_name, true );
			}
		}

		$this->payment_settings->save();
	}

	/**
	 * Maps old setting keys to new payment method names.
	 *
	 * @return array<string, string>
	 */
	protected function map(): array {
		return array(
			'dcc_enabled'              => CreditCardGateway::ID,
			'axo_enabled'              => AxoGateway::ID,
			'applepay_button_enabled'  => ApplePayGateway::ID,
			'googlepay_button_enabled' => GooglePayGateway::ID,
			'pay_later_button_enabled' => 'pay-later',
		);
	}
}
