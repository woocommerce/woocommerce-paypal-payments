<?php

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Ingestion;

/**
 * Factory for creating ProductsPayload instances.
 */
class ProductsPayloadFactory {
	/**
	 * Create a ProductsPayload instance with the given product IDs.
	 *
	 * @param array $product_ids The product IDs to include in the payload.
	 * @return ProductsPayload The created ProductsPayload instance.
	 */
	public function create( array $product_ids ): ProductsPayload {
		return new ProductsPayload( $product_ids );
	}
}