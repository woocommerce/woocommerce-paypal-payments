<?php
/**
 * Handles migration of payment settings from legacy format to new structure.
 *
 * @package WooCommerce\PayPalCommerce\Settings\Service\Migration
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Settings\Service\Migration;

use WooCommerce\PayPalCommerce\ApiClient\Helper\DccApplies;
use WooCommerce\PayPalCommerce\Applepay\ApplePayGateway;
use WooCommerce\PayPalCommerce\Axo\Gateway\AxoGateway;
use WooCommerce\PayPalCommerce\Googlepay\GooglePayGateway;
use WooCommerce\PayPalCommerce\Settings\Data\Definition\PaymentMethodsDefinition;
use WooCommerce\PayPalCommerce\Settings\Data\PaymentSettings;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Helper\CardPaymentsConfiguration;
use WooCommerce\PayPalCommerce\WcGateway\Helper\DCCProductStatus;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;

/**
 * Class PaymentSettingsMigration
 *
 * Handles migration of payment settings.
 */
class PaymentSettingsMigration implements SettingsMigrationInterface {

	public const OPTION_NAME_BCDC_MIGRATION_OVERRIDE = 'woocommerce_paypal_payments_bcdc_migration_override';

	protected Settings $settings;
	protected PaymentSettings $payment_settings;
	protected DccApplies $dcc_applies;
	protected DCCProductStatus $dcc_status;
	protected CardPaymentsConfiguration $dcc_configuration;
	protected PaymentMethodsDefinition $methods_definition;

	/**
	 * The list of local apm methods.
	 *
	 * @var array<string, array>
	 */
	protected array $local_apms;

	public function __construct(
		Settings $settings,
		PaymentSettings $payment_settings,
		DccApplies $dcc_applies,
		DCCProductStatus $dcc_status,
		CardPaymentsConfiguration $dcc_configuration,
		PaymentMethodsDefinition $methods_definition,
		array $local_apms
	) {
		$this->settings           = $settings;
		$this->payment_settings   = $payment_settings;
		$this->dcc_applies        = $dcc_applies;
		$this->dcc_status         = $dcc_status;
		$this->local_apms         = $local_apms;
		$this->dcc_configuration  = $dcc_configuration;
		$this->methods_definition = $methods_definition;
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

		if ( $this->is_bcdc_enabled_for_acdc_merchant() ) {
			$this->disable_acdc_methods();
			update_option( self::OPTION_NAME_BCDC_MIGRATION_OVERRIDE, true );
		}

		$this->payment_settings->save();
	}

	/**
	 * Checks if BCDC is enabled for ACDC merchant.
	 *
	 * This method verifies two conditions:
	 * 1. The merchant is an ACDC merchant - determined by
	 *    checking if DCC applies for the current country/currency and DCC status is active
	 * 2. The BCDC is enabled
	 *
	 * @return bool True if BCDC is enabled for ACDC merchant, false otherwise.
	 */
	public function is_bcdc_enabled_for_acdc_merchant(): bool {
		$is_acdc_merchant = $this->dcc_applies->for_country_currency() && $this->dcc_status->is_active();
		if ( ! $is_acdc_merchant ) {
			return false;
		}

		if ( $this->dcc_configuration->is_acdc_enabled() ) {
			return false;
		}

		$disabled_funding = $this->settings->has( 'disable_funding' ) ? $this->settings->get( 'disable_funding' ) : array();
		return ! in_array( 'card', $disabled_funding, true );
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

	/**
	 * Disables all ACDC card payment methods.
	 *
	 * Iterates through all card payment methods defined in the methods definition
	 * and disables each one by toggling its state to false. This is used during
	 * migration when a merchant is switching from ACDC to BCDC classification.
	 *
	 * In the legacy UI, merchants could have certain methods (like Google Pay)
	 * enabled together with BCDC. During migration, we need to disable all ACDC
	 * card methods to ensure proper BCDC-only state in the new UI.
	 *
	 * @return void
	 */
	protected function disable_acdc_methods(): void {
		foreach ( $this->methods_definition->group_card_methods() as $method ) {
			$this->payment_settings->toggle_method_state( $method['id'], false );
			$this->payment_settings->save();
		}
	}
}
