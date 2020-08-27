<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Button\Endpoint;

use Inpsyde\PayPalCommerce\ApiClient\Entity\Item;
use Inpsyde\PayPalCommerce\ApiClient\Entity\PurchaseUnit;
use Inpsyde\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use Inpsyde\PayPalCommerce\ApiClient\Repository\CartRepository;
use Inpsyde\PayPalCommerce\Button\Exception\RuntimeException;

class ChangeCartEndpoint implements EndpointInterface {


	public const ENDPOINT = 'ppc-change-cart';

	private $cart;
	private $shipping;
	private $requestData;
	private $repository;
	private $productDataStore;
	public function __construct(
		\WC_Cart $cart,
		\WC_Shipping $shipping,
		RequestData $requestData,
		CartRepository $repository,
		\WC_Data_Store $productDataStore
	) {

		$this->cart             = $cart;
		$this->shipping         = $shipping;
		$this->requestData      = $requestData;
		$this->repository       = $repository;
		$this->productDataStore = $productDataStore;
	}

	public static function nonce(): string {
		return self::ENDPOINT;
	}

	public function handleRequest(): bool {
		try {
			return $this->handleData();
		} catch ( RuntimeException $error ) {
			wp_send_json_error(
				array(
					'name'    => is_a( $error, PayPalApiException::class ) ? $error->name() : '',
					'message' => $error->getMessage(),
					'code'    => $error->getCode(),
					'details' => is_a( $error, PayPalApiException::class ) ? $error->details() : array(),
				)
			);
			return false;
		}
	}

	private function handleData(): bool {
		$data     = $this->requestData->readRequest( $this->nonce() );
		$products = $this->productsFromData( $data );
		if ( ! $products ) {
			wp_send_json_error(
				array(
					'name'    => '',
					'message' => __(
						'Necessary fields not defined. Action aborted.',
						'woocommerce-paypal-commerce-gateway'
					),
					'code'    => 0,
					'details' => array(),
				)
			);
			return false;
		}

		$this->shipping->reset_shipping();
		$this->cart->empty_cart( false );
		$success = true;
		foreach ( $products as $product ) {
			$success = $success && ( ! $product['product']->is_type( 'variable' ) ) ?
				$this->addProduct( $product['product'], $product['quantity'] )
				: $this->addVariableProduct(
					$product['product'],
					$product['quantity'],
					$product['variations']
				);
		}
		if ( ! $success ) {
			$this->handleError();
			return $success;
		}

		wp_send_json_success( $this->generatePurchaseUnits() );
		return $success;
	}

	private function handleError(): bool {

		$message = __(
			'Something went wrong. Action aborted',
			'woocommerce-paypal-commerce-gateway'
		);
		$errors  = wc_get_notices( 'error' );
		if ( count( $errors ) ) {
			$message = array_reduce(
				$errors,
				static function ( string $add, array $error ): string {
					return $add . $error['notice'] . ' ';
				},
				''
			);
			wc_clear_notices();
		}

		wp_send_json_error(
			array(
				'name'    => '',
				'message' => $message,
				'code'    => 0,
				'details' => array(),
			)
		);
		return true;
	}

	private function productsFromData( array $data ): ?array {

		$products = array();

		if (
			! isset( $data['products'] )
			|| ! is_array( $data['products'] )
		) {
			return null;
		}
		foreach ( $data['products'] as $product ) {
			if ( ! isset( $product['quantity'] ) || ! isset( $product['id'] ) ) {
				return null;
			}

			$wcProduct = wc_get_product( (int) $product['id'] );

			if ( ! $wcProduct ) {
				return null;
			}
			$products[] = array(
				'product'    => $wcProduct,
				'quantity'   => (int) $product['quantity'],
				'variations' => isset( $product['variations'] ) ? $product['variations'] : null,
			);
		}
		return $products;
	}

	private function addProduct( \WC_Product $product, int $quantity ): bool {
		return false !== $this->cart->add_to_cart( $product->get_id(), $quantity );
	}

	private function addVariableProduct(
		\WC_Product $product,
		int $quantity,
		array $postVariations
	): bool {

		$variations = array();
		foreach ( $postVariations as $key => $value ) {
			$variations[ $value['name'] ] = $value['value'];
		}

		$variationId = $this->productDataStore->find_matching_product_variation( $product, $variations );

		// ToDo: Check stock status for variation.
		return false !== $this->cart->add_to_cart(
			$product->get_id(),
			$quantity,
			$variationId,
			$variations
		);
	}

	private function generatePurchaseUnits(): array {
		return array_map(
			static function ( PurchaseUnit $lineItem ): array {
				return $lineItem->toArray();
			},
			$this->repository->all()
		);
	}
}
