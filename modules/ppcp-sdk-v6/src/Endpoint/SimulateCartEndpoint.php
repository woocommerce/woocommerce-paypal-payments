<?php
/**
 * Prices a product without touching the shopper's cart.
 *
 * Exists because Safari requires the Apple Pay sheet to be constructed
 * synchronously in the click handler, so its total cannot be resolved on demand.
 *
 * Button\Endpoint\SimulateCartEndpoint answers the same question but cannot serve
 * these pages: it requires button.smart-button to be a real SmartButton, which this
 * module replaces with DisabledSmartButton wherever it owns the page (see
 * extensions.php). Extending this endpoint is the way to add the Pay Later
 * eligibility and button-disabled answers that one also returns.
 *
 * @package WooCommerce\PayPalCommerce\SdkV6\Endpoint
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\SdkV6\Endpoint;

use Exception;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\Button\Helper\IsolatedCartSimulator;
use WooCommerce\PayPalCommerce\OrderEndpoints\Endpoint\AbstractCartEndpoint;
use WooCommerce\PayPalCommerce\OrderEndpoints\Endpoint\RequestData;
use WooCommerce\PayPalCommerce\OrderEndpoints\Helper\CartProductsHelper;

class SimulateCartEndpoint extends AbstractCartEndpoint {

	const ENDPOINT = 'ppc-sdk-v6-simulate-cart';

	private IsolatedCartSimulator $cart_simulator;

	public function __construct(
		RequestData $request_data,
		CartProductsHelper $cart_products,
		IsolatedCartSimulator $cart_simulator,
		LoggerInterface $logger
	) {
		$this->request_data   = $request_data;
		$this->cart_products  = $cart_products;
		$this->cart_simulator = $cart_simulator;
		$this->logger         = $logger;

		$this->logger_tag = 'simulation';
	}

	/**
	 * Responds with the simulated total for the posted products.
	 *
	 * @throws Exception If the cart simulation fails.
	 */
	protected function handle_data(): void {
		/**
		 * The filter that switches cart simulation off, honoured here too so a
		 * merchant who disabled it does not get it back through this endpoint.
		 */
		if ( ! apply_filters( 'woocommerce_paypal_payments_simulate_cart_enabled', true ) ) {
			wp_send_json_error(
				array(
					'name'    => '',
					'message' => 'Cart simulation is disabled.',
					'code'    => 0,
					'details' => array(),
				)
			);
		}

		// Validates the nonce, and responds on its own when no usable products were posted.
		$products = $this->products_from_request();
		if ( ! $products ) {
			return;
		}

		$result = $this->cart_simulator->simulate( $products );

		wp_send_json_success(
			array(
				// A string at the currency's own precision, because the caller puts it
				// straight into a payment sheet: a float renders as 10 where the shopper
				// expects 10.00, and a fixed 2 truncates 3-decimal currencies.
				'total'         => wc_format_decimal( $result['total'], wc_get_price_decimals() ),
				'currency_code' => get_woocommerce_currency(),
			)
		);
	}
}
