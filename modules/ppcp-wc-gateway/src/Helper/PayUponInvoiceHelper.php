<?php
/**
 * Helper methods for PUI.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Helper
 */

declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\WcGateway\Helper;

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
	 * PayUponInvoiceHelper constructor.
	 *
	 * @param CheckoutHelper $checkout_helper The checkout helper.
	 */
	public function __construct( CheckoutHelper $checkout_helper ) {
		$this->checkout_helper = $checkout_helper;
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

		if ( 'EUR' !== get_woocommerce_currency() ) {
			return false;
		}

		if ( ! $this->checkout_helper->is_checkout_amount_allowed( 5, 2500 ) ) {
			return false;
		}

		return true;
	}
}
