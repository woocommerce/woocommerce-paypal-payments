<?php
/**
 * Endpoint to update the cart.
 *
 * @package WooCommerce\PayPalCommerce\Button\Endpoint
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Button\Endpoint;

use Exception;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PurchaseUnitFactory;
use WooCommerce\PayPalCommerce\Button\Helper\CartProductsHelper;

/**
 * Class ChangeCartEndpoint
 */
class ChangeCartEndpoint extends AbstractCartEndpoint {

	const ENDPOINT = 'ppc-change-cart';

	/**
	 * The current shipping object.
	 *
	 * @var \WC_Shipping
	 */
	private $shipping;

	/**
	 * The PurchaseUnit factory.
	 *
	 * @var PurchaseUnitFactory
	 */
	private $purchase_unit_factory;

	/**
	 * ChangeCartEndpoint constructor.
	 *
	 * @param \WC_Cart            $cart The current WC cart object.
	 * @param \WC_Shipping        $shipping The current WC shipping object.
	 * @param RequestData         $request_data The request data helper.
	 * @param PurchaseUnitFactory $purchase_unit_factory The PurchaseUnit factory.
	 * @param CartProductsHelper  $cart_products The cart products helper.
	 * @param LoggerInterface     $logger The logger.
	 */
	public function __construct(
		\WC_Cart $cart,
		\WC_Shipping $shipping,
		RequestData $request_data,
		PurchaseUnitFactory $purchase_unit_factory,
		CartProductsHelper $cart_products,
		LoggerInterface $logger
	) {

		$this->cart                  = $cart;
		$this->shipping              = $shipping;
		$this->request_data          = $request_data;
		$this->purchase_unit_factory = $purchase_unit_factory;
		$this->cart_products         = $cart_products;
		$this->logger                = $logger;

		$this->logger_tag = 'updating';
	}

	/**
	 * Handles the request data.
	 *
	 * @return bool
	 * @throws Exception On error.
	 */
	protected function handle_data(): bool {
		$data = $this->request_data->read_request( $this->nonce() );

		$this->cart_products->set_cart( $this->cart );

		$products = $this->products_from_request();

		if ( ! $products ) {
			return false;
		}

		if ( ! ( $data['keepShipping'] ?? false ) ) {
			$this->shipping->reset_shipping();
		}

		if ( ! $this->add_products( $products ) ) {
			return false;
		}

		wp_send_json_success( $this->generate_purchase_units() );
		return true;
	}

	/**
	 * Based on the cart contents, the purchase units are created.
	 *
	 * @return array
	 */
	private function generate_purchase_units(): array {
		$pu = $this->purchase_unit_factory->from_wc_cart();
		return array( $pu->to_array() );
	}
}
