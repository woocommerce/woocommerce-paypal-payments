<?php
/**
 * OXXO integration.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Gateway\OXXO
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Gateway\OXXO;

use WC_Order;
use WooCommerce\PayPalCommerce\WcGateway\Helper\CheckoutHelper;

/**
 * Class OXXO.
 */
class OXXO {

	/**
	 * The checkout helper.
	 *
	 * @var CheckoutHelper
	 */
	protected $checkout_helper;

	/**
	 * OXXO constructor.
	 *
	 * @param CheckoutHelper $checkout_helper The checkout helper.
	 */
	public function __construct( CheckoutHelper $checkout_helper ) {

		$this->checkout_helper = $checkout_helper;
	}

	/**
	 * Initializes OXXO integration.
	 */
	public function init(): void {

		add_filter(
			'woocommerce_available_payment_gateways',
			function ( array $methods ): array {

				if ( ! $this->checkout_allowed_for_oxxo() ) {
					unset( $methods[ OXXOGateway::ID ] );
				}

				return $methods;
			}
		);

		add_filter(
			'woocommerce_thankyou_order_received_text',
			function( string $message, WC_Order $order ) {
				$payer_action = $order->get_meta( 'ppcp_oxxo_payer_action' ) ?? '';

				$button = '';
				if ( $payer_action ) {
					$button = '<p><a class="button" href="' . $payer_action . '" target="_blank">See OXXO Voucher/Ticket</a></p>';
				}

				return $message . ' ' . $button;
			},
			10,
			2
		);
	}

	/**
	 * Checks if checkout is allowed for OXXO.
	 *
	 * @return bool
	 */
	private function checkout_allowed_for_oxxo(): bool {
		if ( 'MXN' !== get_woocommerce_currency() ) {
			return false;
		}

		$billing_country = filter_input( INPUT_POST, 'country', FILTER_SANITIZE_STRING ) ?? null;
		if ( $billing_country && 'MX' !== $billing_country ) {
			return false;
		}

		if ( ! $this->checkout_helper->is_checkout_amount_allowed( 0, 10000 ) ) {
			return false;
		}

		return true;
	}
}
