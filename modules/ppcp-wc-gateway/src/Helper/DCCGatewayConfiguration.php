<?php
/**
 * Encapsulates all configuration details for "Credit & Debit Card" gateway.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Helper
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\WcGateway\Helper;

use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
use WooCommerce\PayPalCommerce\Axo\Helper\PropertiesDictionary;
use WooCommerce\PayPalCommerce\WcGateway\Exception\NotFoundException;

/**
 * A simple DTO that provides convenient access to the DCC/AXO gateway settings.
 *
 * This class should not implement business logic, but only provide a convenient
 * way to access gateway settings by wrapping the Settings instance.
 */
class DCCGatewayConfiguration {
	private string $gateway_title;
	private string $show_name_on_card;

	/**
	 * Initializes the gateway details based on the provided Settings instance.
	 *
	 * @throws NotFoundException If an expected gateway setting is not found.
	 *
	 * @param Settings $settings Plugin settings instance.
	 */
	public function __construct( Settings $settings ) {
		$this->gateway_title = $settings->has( 'dcc_gateway_title' ) ?
			$settings->get( 'dcc_gateway_title' ) : '';

		$show_on_card = '';
		if ( $settings->has( 'dcc_name_on_card' ) ) {
			$show_on_card = $settings->get( 'dcc_name_on_card' );
		} elseif ( $settings->has( 'axo_name_on_card' ) ) {
			// Legacy. The AXO gateway setting was replaced by the DCC setting.
			$show_on_card = $settings->get( 'axo_name_on_card' );
		}
		$valid_options = array_keys( PropertiesDictionary::cardholder_name_options() );

		$this->show_name_on_card = in_array( $show_on_card, $valid_options, true )
			? $show_on_card
			: $valid_options[0];
	}

	/**
	 * User facing title of the gateway.
	 *
	 * @return string Display title of the gateway.
	 */
	public function gateway_title() : string {
		return $this->gateway_title ?: __( 'Advanced Card Processing', 'woocommerce-paypal-payments' );
	}

	/**
	 * Whether to show a field for the cardholder's name in the payment form.
	 *
	 * @return string [yes|no]
	 */
	public function show_name_on_card() : string {
		return $this->show_name_on_card;
	}
}
