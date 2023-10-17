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
use WooCommerce\PayPalCommerce\ApiClient\Entity\Money;
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
	 * The WooCommerce real active cart.
	 *
	 * @var \WC_Cart|null
	 */
	private $real_cart = null;

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
		$products = $this->products_from_request();

		if ( ! $products ) {
			return false;
		}

		$this->replace_real_cart();

		$this->add_products( $products );

		$this->cart->calculate_totals();
		$total = (float) $this->cart->get_total( 'numeric' );

		$this->restore_real_cart();

		// Process filters.
		$pay_later_enabled           = true;
		$pay_later_messaging_enabled = true;
		$button_enabled              = true;

		foreach ( $products as $product ) {
			$context_data = array(
				'product'     => $product['product'],
				'order_total' => $total,
			);

			$pay_later_enabled           = $pay_later_enabled && $this->smart_button->is_pay_later_button_enabled_for_location( 'product', $context_data );
			$pay_later_messaging_enabled = $pay_later_messaging_enabled && $this->smart_button->is_pay_later_messaging_enabled_for_location( 'product', $context_data );
			$button_enabled              = $button_enabled && ! $this->smart_button->is_button_disabled( 'product', $context_data );
		}

		// Shop settings.
		$base_location     = wc_get_base_location();
		$shop_country_code = $base_location['country'];
		$currency_code     = get_woocommerce_currency();

		wp_send_json_success(
			array(
				'total'         => $total,
				'total_str'     => ( new Money( $total, $currency_code ) )->value_str(),
				'currency_code' => $currency_code,
				'country_code'  => $shop_country_code,
				'funding'       => array(
					'paylater' => array(
						'enabled' => $pay_later_enabled,
					),
				),
				'button'        => array(
					'is_disabled' => ! $button_enabled,
				),
				'messages'      => array(
					'is_hidden' => ! $pay_later_messaging_enabled,
				),
			)
		);
		return true;
	}

	/**
	 * Handles errors.
	 *
	 * @param bool $send_response If this error handling should return the response.
	 * @return void
	 *
	 * phpcs:disable Generic.CodeAnalysis.UselessOverridingMethod.Found
	 */
	protected function handle_error( bool $send_response = false ): void {
		parent::handle_error( $send_response );
	}

	/**
	 * Replaces the real cart with the clone.
	 *
	 * @return void
	 */
	private function replace_real_cart() {
		// Set WC default cart as the clone.
		// Store a reference to the real cart.
		$this->real_cart = WC()->cart;
		WC()->cart       = $this->cart;
	}

	/**
	 * Restores the real cart.
	 * Currently, unsets the WC cart to prevent race conditions arising from it being persisted.
	 *
	 * @return void
	 */
	private function restore_real_cart() {
		// Remove from cart because some plugins reserve resources internally when adding to cart.
		$this->remove_cart_items();

		if ( apply_filters( 'woocommerce_paypal_payments_simulate_cart_prevent_updates', true ) ) {
			// Removes shutdown actions to prevent persisting session, transients and save cookies.
			remove_all_actions( 'shutdown' );
			unset( WC()->cart );
		} else {
			// Restores cart, may lead to race conditions.
			if ( null !== $this->real_cart ) {
				WC()->cart = $this->real_cart;
			}
		}

		unset( $this->cart );
	}

}
