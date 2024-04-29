<?php
/**
 * PageMatcherTrait.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Settings
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Settings;

use WooCommerce\PayPalCommerce\Axo\Gateway\AxoGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CardButtonGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;

/**
 * Class PageMatcherTrait.
 */
trait PageMatcherTrait {

	/**
	 * Checks whether the field config matches the current page (can be rendered here).
	 *
	 * @param array  $field_config The field config (from wcgateway.settings.fields).
	 * @param string $current_page_id ID of the current PPCP gateway settings page.
	 * @return bool
	 */
	protected function field_matches_page( array $field_config, string $current_page_id ): bool {
		$allowed_gateways = $field_config['gateway'];
		if ( ! is_array( $allowed_gateways ) ) {
			$allowed_gateways = array( $allowed_gateways );
		}

		$gateway_page_id_map = array(
			Settings::CONNECTION_TAB_ID => Settings::CONNECTION_TAB_ID,
			PayPalGateway::ID           => 'paypal',
			Settings::PAY_LATER_TAB_ID  => Settings::PAY_LATER_TAB_ID,
			CreditCardGateway::ID       => 'dcc', // TODO: consider using just the gateway ID for PayPal and DCC too.
			CardButtonGateway::ID       => CardButtonGateway::ID,
			AxoGateway::ID              => 'axo',
		);
		return array_key_exists( $current_page_id, $gateway_page_id_map )
			&& in_array( $gateway_page_id_map[ $current_page_id ], $allowed_gateways, true );
	}
}
