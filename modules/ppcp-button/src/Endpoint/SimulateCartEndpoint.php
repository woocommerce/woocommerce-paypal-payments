<?php
/**
 * Endpoint to simulate adding products to the cart.
 *
 * @package WooCommerce\PayPalCommerce\Button\Endpoint
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Button\Endpoint;

use Exception;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\Button\Assets\SmartButton;

/**
 * Class SimulateCartEndpoint
 */
class SimulateCartEndpoint extends AbstractCartEndpoint {

	const ENDPOINT = 'ppc-simulate-cart';

	/**
	 * The SmartButton.
	 *
	 * @var SmartButton
	 */
	private $smart_button;

	/**
	 * ChangeCartEndpoint constructor.
	 *
	 * @param SmartButton     $smart_button The SmartButton.
	 * @param \WC_Cart        $cart The current WC cart object.
	 * @param RequestData     $request_data The request data helper.
	 * @param \WC_Data_Store  $product_data_store The data store for products.
	 * @param LoggerInterface $logger The logger.
	 */
	public function __construct(
		SmartButton $smart_button,
		\WC_Cart $cart,
		RequestData $request_data,
		\WC_Data_Store $product_data_store,
		LoggerInterface $logger
	) {
		$this->smart_button       = $smart_button;
		$this->cart               = clone $cart;
		$this->request_data       = $request_data;
		$this->product_data_store = $product_data_store;
		$this->logger             = $logger;

		$this->logger_tag = 'simulation';
	}

	/**
	 * Handles the request data.
	 *
	 * @return bool
	 * @throws Exception On error.
	 */
	protected function handle_data(): bool {
		if ( ! $products = $this->products_from_request() ) {
			return false;
		}

		// Set WC default cart as the clone.
		// Store a reference to the real cart.
		$activeCart = WC()->cart;
		WC()->cart = $this->cart;

		if ( ! $this->add_products($products) ) {
			return false;
		}

		$this->cart->calculate_totals();
		$total = (float) $this->cart->get_total( 'numeric' );

		$this->remove_cart_items();

		// Restore cart and unset cart clone
		WC()->cart = $activeCart;
		unset( $this->cart );

		wp_send_json_success(
			array(
				'total' => $total,
				'funding' => [
					'paylater' => [
						'enabled' => $this->smart_button->is_pay_later_button_enabled_for_location( 'cart', $total ),
						'messaging_enabled' => $this->smart_button->is_pay_later_messaging_enabled_for_location( 'cart', $total ),
					]
				],
				'button' => [
					'is_disabled' => $this->smart_button->is_button_disabled( 'cart', $total ),
				]
			)
		);
		return true;
	}

}
