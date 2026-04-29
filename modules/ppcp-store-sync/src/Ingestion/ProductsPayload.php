<?php

namespace WooCommerce\PayPalCommerce\StoreSync\Ingestion;

use WC_Product;
use WC_Product_Variation;
use WooCommerce\PayPalCommerce\StoreSync\Helper\CartHelper;

class ProductsPayload {
	private string $merchant_store_url;

	/**
	 * @var int[]
	 */
	private array $product_ids;

	public function __construct( string $merchant_store_url, array $product_ids ) {
		$this->merchant_store_url = $merchant_store_url;
		$this->product_ids        = $product_ids;
	}

	public function get_array(): array {
		return $this->transform_products( $this->product_ids );
	}

	private function transform_products( array $product_ids ): array {
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

			// Handle variable products by only adding their variants.
			if ( $product->is_type( 'variable' ) ) {
				$variants = $this->get_product_variants( $product );
				if ( $variants ) {
					// Only add variants, not the parent variable product.
					$api_products = array_merge( $api_products, $variants );
				}
				continue;
			}

			// For all other product types (simple, grouped, etc.).
			$api_product = array(
				'id'               => (string) $product->get_id(),
				'title'            => $this->get_product_title( $product ),
				'link'             => $this->get_product_link( $product ),
				'image_link'       => $this->get_product_image( $product ),
				'description'      => $this->get_product_description( $product, $product->get_short_description() ),
				'price'            => $this->format_price( $product->get_price() ),
				'availability'     => $this->get_product_availability( $product ),
				'merchantStoreUrl' => $this->merchant_store_url,
			);

			// Add optional fields.
			if ( $product->get_sku() ) {
				$api_product['mpn'] = $product->get_sku();
			}

			if ( $product->get_sale_price() ) {
				$api_product['sale_price'] = $this->format_price( $product->get_sale_price() );
			}

			$product_type = $this->get_product_type( $product );
			if ( $product_type ) {
				$api_product['product_type'] = $product_type;
			}

			$api_products[] = $api_product;
		}

		return $api_products;
	}

	private function get_product_variants( WC_Product $variable_product ): array {
		$variants      = array();
		$variation_ids = $variable_product->get_children();

		$product_type = $this->get_product_type( $variable_product );

		foreach ( $variation_ids as $variation_id ) {
			$variation = wc_get_product( $variation_id );
			if ( ! $variation instanceof WC_Product_Variation || ! $variation->is_purchasable() ) {
				continue;
			}

			$variant = array(
				'id'               => (string) $variation->get_id(),
				'item_group_id'    => (string) $variable_product->get_id(),
				'title'            => $this->get_product_title( $variation ),
				'link'             => $this->get_product_link( $variation ),
				'image_link'       => $this->get_product_image(
					$variation,
					wp_get_attachment_image_url( (int) $variable_product->get_image_id(), 'full' ) ?: ''
				),
				'description'      => $this->get_product_description( $variation, $variable_product->get_description() ),
				'price'            => $this->format_price( $variation->get_price() ),
				'availability'     => $this->get_product_availability( $variation ),
				'merchantStoreUrl' => $this->merchant_store_url,
			);

			// Add variant attributes using WooCommerce methods.
			$attributes = $variation->get_variation_attributes();
			foreach ( $attributes as $attribute => $value ) {
				$clean_attr = str_replace(
					array( 'attribute_pa_', 'attribute_' ),
					'',
					$attribute
				);

				if ( in_array( $clean_attr, array( 'color', 'size', 'gender' ), true ) ) {
					$variant[ $clean_attr ] = $value;
				}
			}

			if ( $variation->get_sku() ) {
				$variant['mpn'] = $variation->get_sku();
			}

			if ( $variation->get_sale_price() ) {
				$variant['sale_price'] = $this->format_price( $variation->get_sale_price() );
			}

			// Add the parent product.
			if ( $product_type ) {
				$variant['product_type'] = $product_type;
			}

			$variants[] = $variant;
		}

		return $variants;
	}

	/**
	 * @param WC_Product $product  The WooCommerce product object.
	 * @param string     $fallback Fallback description (e.g. short description for simple products,
	 *                             parent description for variations).
	 * @return string Plain-text description, passed through the filter hook.
	 */
	private function get_product_description( WC_Product $product, string $fallback = '' ): string {
		$description = $product->get_description() ?: $fallback;
		$plain_text  = wp_strip_all_tags( $description );
		$plain_text  = html_entity_decode( $plain_text, ENT_QUOTES, 'UTF-8' );
		$plain_text  = trim( preg_replace( '/\s+/', ' ', $plain_text ) ?? '' );

		/**
		 * Filters the product description for PayPal Agentic Commerce ingestion.
		 *
		 * @since 3.4.0
		 *
		 * @param string     $plain_text The plain text description.
		 * @param WC_Product $product    The WooCommerce product object.
		 */
		return apply_filters(
			'woocommerce_paypal_payments_agentic_commerce_item_description',
			$plain_text,
			$product
		);
	}

	/**
	 * @param WC_Product $product The WooCommerce product object.
	 * @return string The filtered product title.
	 */
	private function get_product_title( WC_Product $product ): string {
		/**
		 * Filters the product title for PayPal Agentic Commerce ingestion.
		 *
		 * @since 3.4.0
		 *
		 * @param string     $title   The product title.
		 * @param WC_Product $product The WooCommerce product object.
		 */
		return apply_filters(
			'woocommerce_paypal_payments_agentic_commerce_item_title',
			$product->get_name(),
			$product
		);
	}

	/**
	 * @param WC_Product $product The WooCommerce product object.
	 * @return string The filtered product permalink.
	 */
	private function get_product_link( WC_Product $product ): string {
		/**
		 * Filters the product link for PayPal Agentic Commerce ingestion.
		 *
		 * @since 3.4.0
		 *
		 * @param string     $link    The product permalink.
		 * @param WC_Product $product The WooCommerce product object.
		 */
		return apply_filters(
			'woocommerce_paypal_payments_agentic_commerce_item_link',
			$product->get_permalink(),
			$product
		);
	}

	/**
	 * @param WC_Product $product  The WooCommerce product object.
	 * @param string     $fallback Fallback image URL (e.g. parent product image for variations).
	 * @return string The filtered image URL.
	 */
	private function get_product_image( WC_Product $product, string $fallback = '' ): string {
		$image_url =
			wp_get_attachment_image_url( (int) $product->get_image_id(), 'full' ) ?: $fallback;

		/**
		 * Filters the product image URL for PayPal Agentic Commerce ingestion.
		 *
		 * @since 3.4.0
		 *
		 * @param string     $image_url The product image URL.
		 * @param WC_Product $product   The WooCommerce product object.
		 */
		return apply_filters(
			'woocommerce_paypal_payments_agentic_commerce_item_image',
			$image_url,
			$product
		);
	}

	/**
	 * @param WC_Product $product The WooCommerce product object.
	 * @return string The filtered availability status.
	 */
	private function get_product_availability( WC_Product $product ): string {
		$mapping = array(
			'instock'     => 'in stock',
			'outofstock'  => 'out of stock',
			'onbackorder' => 'backorder',
		);

		$availability = $mapping[ $product->get_stock_status() ] ?? 'out of stock';

		/**
		 * Filters the product availability for PayPal Agentic Commerce ingestion.
		 *
		 * @since 3.4.0
		 *
		 * @param string     $availability The mapped availability status.
		 * @param WC_Product $product      The WooCommerce product object.
		 */
		return apply_filters(
			'woocommerce_paypal_payments_agentic_commerce_item_availability',
			$availability,
			$product
		);
	}

	/**
	 * @param WC_Product $product The WooCommerce product object.
	 * @return string Plain-text category list, passed through the filter hook, or empty string.
	 */
	private function get_product_type( WC_Product $product ): string {
		$categories = wc_get_product_category_list( $product->get_id() );
		$plain_text = wp_strip_all_tags( $categories );
		$plain_text = html_entity_decode( $plain_text, ENT_QUOTES, 'UTF-8' );
		$plain_text = trim( preg_replace( '/\s+/', ' ', $plain_text ) ?? '' );

		/**
		 * Filters the product type for PayPal Agentic Commerce ingestion.
		 *
		 * @since 3.4.0
		 *
		 * @param string     $plain_text The plain text category list.
		 * @param WC_Product $product    The WooCommerce product object.
		 */
		return apply_filters(
			'woocommerce_paypal_payments_agentic_commerce_item_product_type',
			$plain_text,
			$product
		);
	}

	/**
	 * @param string|mixed $price WooCommerce uses strings, but any numeric value is accepted.
	 *                            Defends the method against plugins or future changes that use
	 *                            a different data type.
	 * @return string
	 */
	private function format_price( $price ): string {
		if ( ! $price || ! is_numeric( $price ) ) {
			return '';
		}

		return CartHelper::format_decimal( $price ) . ' ' . get_woocommerce_currency();
	}
}
