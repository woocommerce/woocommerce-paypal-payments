<?php
/**
 * A helper for mapping the old/new payment method settings.
 *
 * @package WooCommerce\PayPalCommerce\Compat\Settings
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Compat\Settings;

use WooCommerce\PayPalCommerce\Axo\Gateway\AxoGateway;
use WooCommerce\PayPalCommerce\Settings\Data\AbstractDataModel;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;

/**
 * A map of old to new payment method settings.
 *
 * @psalm-import-type newSettingsKey from SettingsMap
 * @psalm-import-type oldSettingsKey from SettingsMap
 */
class PaymentMethodSettingsMapHelper {

	/**
	 * Maps old setting keys to new payment method settings names.
	 *
	 * @psalm-return array<oldSettingsKey, newSettingsKey>
	 */
	public function map(): array {
		return array(
			'dcc_enabled' => CreditCardGateway::ID,
			'axo_enabled' => AxoGateway::ID,
		);
	}

	/**
	 * Retrieves the value of a mapped key from the new settings.
	 *
	 * @param string                 $old_key The key from the legacy settings.
	 * @param AbstractDataModel|null $payment_settings The payment settings model.
	 * @return mixed The value of the mapped setting, (null if not found).
	 */
	public function mapped_value( string $old_key, ?AbstractDataModel $payment_settings ) {
		$payment_method = $this->map()[ $old_key ] ?? false;

		if ( ! $payment_method ) {
			return null;
		}

		return $this->is_gateway_enabled( $payment_method );
	}

	/**
	 * Checks if the payment gateway with the given name is enabled.
	 *
	 * @param string $gateway_name The gateway name.
	 * @return bool True if the payment gateway with the given name is enabled, otherwise false.
	 */
	protected function is_gateway_enabled( string $gateway_name ): bool {
		$gateway_settings = get_option( "woocommerce_{$gateway_name}_settings", array() );
		$gateway_enabled  = $gateway_settings['enabled'] ?? false;

		return $gateway_enabled === 'yes';
	}
}
