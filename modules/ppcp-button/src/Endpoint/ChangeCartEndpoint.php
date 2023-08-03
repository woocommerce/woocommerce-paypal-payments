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
	 * @param \WC_Data_Store      $product_data_store The data store for products.
	 * @param LoggerInterface     $logger The logger.
	 */
	public function __construct(
		\WC_Cart $cart,
		\WC_Shipping $shipping,
		RequestData $request_data,
		PurchaseUnitFactory $purchase_unit_factory,
		\WC_Data_Store $product_data_store,
		LoggerInterface $logger
	) {

		$this->cart                  = $cart;
		$this->shipping              = $shipping;
		$this->request_data          = $request_data;
		$this->purchase_unit_factory = $purchase_unit_factory;
		$this->product_data_store    = $product_data_store;
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
		$products = $this->products_from_request();

		if ( ! $products ) {
			return false;
		}

		$this->shipping->reset_shipping();

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
