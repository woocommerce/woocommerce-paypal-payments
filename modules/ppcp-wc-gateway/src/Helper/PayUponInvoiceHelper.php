<?php
/**
 * Helper methods for PUI.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Helper
 */

declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\WcGateway\Helper;

use WC_Order;
use WooCommerce\PayPalCommerce\WcGateway\Exception\NotFoundException;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;

/**
 * Class PayUponInvoiceHelper
 */
class PayUponInvoiceHelper {

	/**
	 * The checkout helper.
	 *
	 * @var CheckoutHelper
	 */
	protected $checkout_helper;

	/**
	 * The api shop country.
	 *
	 * @var string
	 */
	protected $api_shop_country;

	/**
	 * PayUponInvoiceHelper constructor.
	 *
	 * @param CheckoutHelper $checkout_helper The checkout helper.
	 * @param string         $api_shop_country The api shop country.
	 */
	public function __construct( CheckoutHelper $checkout_helper, string $api_shop_country ) {
		$this->checkout_helper  = $checkout_helper;
		$this->api_shop_country = $api_shop_country;
	}

	/**
	 * Checks whether checkout is ready for PUI.
	 *
	 * @return bool
	 */
	public function is_checkout_ready_for_pui(): bool {
		$gateway_settings = get_option( 'woocommerce_ppcp-pay-upon-invoice-gateway_settings' );
		if ( $gateway_settings && '' === $gateway_settings['customer_service_instructions'] ) {
			return false;
		}

		$billing_country = filter_input( INPUT_POST, 'country', FILTER_SANITIZE_STRING ) ?? null;
		if ( $billing_country && 'DE' !== $billing_country ) {
			return false;
		}

		if ( ! $this->is_valid_currency() ) {
			return false;
		}

		if ( ! $this->checkout_helper->is_checkout_amount_allowed( 5, 2500 ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Checks if currency is allowed for PUI.
	 *
	 * @return bool
	 */
	private function is_valid_currency(): bool {
		global $wp;
		$order_id = isset( $wp->query_vars['order-pay'] ) ? (int) $wp->query_vars['order-pay'] : 0;
		if ( 0 === $order_id ) {
			return 'EUR' === get_woocommerce_currency();
		}

		$order = wc_get_order( $order_id );
		if ( is_a( $order, WC_Order::class ) ) {
			return 'EUR' === $order->get_currency();
		}

		return false;
	}

	/**
	 * Checks whether PUI gateway is enabled.
	 *
	 * @return bool True if PUI gateway is enabled, otherwise false.
	 */
	public function is_pui_gateway_enabled(): bool {
		$gateway_settings = get_option( 'woocommerce_ppcp-pay-upon-invoice-gateway_settings' );
		return isset( $gateway_settings['enabled'] ) && $gateway_settings['enabled'] === 'yes' && 'DE' === $this->api_shop_country;
	}
}
