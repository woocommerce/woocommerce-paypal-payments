<?php

namespace WooCommerce\PayPalCommerce\AgenticCommerce;

class ProductsPayload {
	/**
	 * @var int[]
	 */
	private array $product_ids;

	public function __construct( array $product_ids ) {

		$this->product_ids = $product_ids;
	}

	public function get_array(): array {
		return $this->transform_products( $this->product_ids );
	}

	private function transform_products( array $product_ids ) {
		$api_products = array();

		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				continue;
			}

			// Skip variations - handle them separately.
			if ( $product->get_type() === 'variation' ) {
				continue;
			}

			$api_product = array(
				'id'               => (string) $product->get_id(),
				'title'            => $product->get_name(),
				'link'             => $product->get_permalink(),
				'image_link'       => wp_get_attachment_image_url( $product->get_image_id(), 'full' ) ?: '',
				'description'      => $product->get_description() ?: $product->get_short_description(),
				'price'            => $this->format_price( $product->get_price() ),
				'availability'     => $this->map_stock_status( $product->get_stock_status() ),
				'merchantStoreUrl' => home_url(),
			);

			// Add optional fields
			if ( $product->get_sku() ) {
				$api_product['mpn'] = $product->get_sku();
			}

			if ( $product->get_sale_price() ) {
				$api_product['sale_price'] = $this->format_price( $product->get_sale_price() );
			}

			// Add product categories using WooCommerce functions.
			$categories = wc_get_product_category_list( $product_id );
			if ( $categories ) {
				$api_product['product_type'] = wp_strip_all_tags( $categories );
			}

			// Handle variable products by adding variants
			if ( $product->is_type( 'variable' ) ) {
				$variants = $this->get_product_variants( $product );
				if ( $variants ) {
					$api_product['item_group_id'] = (string) $product->get_id();
					// Add main product
					$api_products[] = $api_product;
					// Add variants
					$api_products = array_merge( $api_products, $variants );
					continue;
				}
			}

			$api_products[] = $api_product;
		}

		return $api_products;
	}

	private function get_product_variants( \WC_Product $variable_product ): array {
		$variants      = array();
		$variation_ids = $variable_product->get_children();

		foreach ( $variation_ids as $variation_id ) {
			$variation = wc_get_product( $variation_id );
			if ( ! $variation || ! $variation->is_purchasable() ) {
				continue;
			}

			$variant = array(
				'id'               => (string) $variation->get_id(),
				'item_group_id'    => (string) $variable_product->get_id(),
				'title'            => $variation->get_name(),
				'link'             => $variation->get_permalink(),
				'image_link'       => wp_get_attachment_image_url( $variation->get_image_id(), 'full' )
					?: wp_get_attachment_image_url( (int) $variable_product->get_image_id(), 'full' )
						?: '',
				'description'      => $variation->get_description() ?: $variable_product->get_description(),
				'price'            => $this->format_price( $variation->get_price() ),
				'availability'     => $this->map_stock_status( $variation->get_stock_status() ),
				'merchantStoreUrl' => home_url(),
			);

			// Add variant attributes using WooCommerce methods.
			$attributes = $variation->get_variation_attributes();
			foreach ( $attributes as $attribute => $value ) {
				$clean_attr = str_replace( 'attribute_pa_', '', $attribute );
				$clean_attr = str_replace( 'attribute_', '', $clean_attr );

				if ( in_array( $clean_attr, array( 'color', 'size', 'gender' ) ) ) {
					$variant[ $clean_attr ] = $value;
				}
			}

			if ( $variation->get_sku() ) {
				$variant['mpn'] = $variation->get_sku();
			}

			if ( $variation->get_sale_price() ) {
				$variant['sale_price'] = $this->format_price( $variation->get_sale_price() );
			}

			$variants[] = $variant;
		}

		return $variants;
	}

	private function format_price( $price ): string {
		if ( ! $price ) {
			return '';
		}

		return number_format( (float) $price, 2, '.', '' ) . ' ' . get_woocommerce_currency();
	}

	private function map_stock_status( $stock_status ): string {
		$mapping = array(
			'instock'     => 'in stock',
			'outofstock'  => 'out of stock',
			'onbackorder' => 'backorder',
		);

		return $mapping[ $stock_status ] ?? 'out of stock';
	}
}
