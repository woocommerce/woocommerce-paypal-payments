<?php

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Ingestion;

use WC_Product;
use WC_Product_Variation;

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
			$image_url   = $this->get_product_image( $product );
			$description = $this->get_product_description( $product );

			// Skip products without required fields.
			if ( ! $image_url || ! $description ) {
				continue;
			}

			$api_product = array(
				'id'               => (string) $product->get_id(),
				'title'            => $product->get_name(),
				'link'             => $product->get_permalink(),
				'image_link'       => $image_url,
				'description'      => $description,
				'price'            => $this->format_price( $product->get_price() ),
				'availability'     => $this->map_stock_status( $product->get_stock_status() ),
				'merchantStoreUrl' => $this->merchant_store_url,
			);

			// Add optional fields.
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

			$api_products[] = $api_product;
		}

		return $api_products;
	}

	private function get_product_variants( WC_Product $variable_product ): array {
		$variants      = array();
		$variation_ids = $variable_product->get_children();

		// Get parent product categories for variations.
		$parent_categories = wc_get_product_category_list( $variable_product->get_id() );
		$product_type      = $parent_categories ? wp_strip_all_tags( $parent_categories ) : '';

		foreach ( $variation_ids as $variation_id ) {
			$variation = wc_get_product( $variation_id );
			if ( ! $variation instanceof WC_Product_Variation || ! $variation->is_purchasable() ) {
				continue;
			}

			$image_url   = $this->get_product_image( $variation, $variable_product );
			$description = $this->get_product_description( $variation, $variable_product );

			// Skip variations without required fields.
			if ( ! $image_url || ! $description ) {
				continue;
			}

			$variant = array(
				'id'               => (string) $variation->get_id(),
				'item_group_id'    => (string) $variable_product->get_id(),
				'title'            => $variation->get_name(),
				'link'             => $variation->get_permalink(),
				'image_link'       => $image_url,
				'description'      => $description,
				'price'            => $this->format_price( $variation->get_price() ),
				'availability'     => $this->map_stock_status( $variation->get_stock_status() ),
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
	 * Get product image URL with optional fallback.
	 *
	 * @param WC_Product      $product  The product to get image from.
	 * @param WC_Product|null $fallback Optional fallback product for image.
	 * @return string|false Image URL or false if not found.
	 */
	private function get_product_image( $product, $fallback = null ) {
		$image_url = wp_get_attachment_image_url( (int) $product->get_image_id(), 'full' );

		if ( ! $image_url && $fallback ) {
			$image_url = wp_get_attachment_image_url( (int) $fallback->get_image_id(), 'full' );
		}

		return $image_url;
	}

	/**
	 * Get product description with optional fallback.
	 *
	 * @param WC_Product      $product  The product to get description from.
	 * @param WC_Product|null $fallback Optional fallback product for description.
	 * @return string Product description.
	 */
	private function get_product_description( $product, $fallback = null ): string {
		$description = $product->get_description();

		if ( ! $description && $fallback ) {
			$description = $fallback->get_description();
		} elseif ( ! $description ) {
			$description = $product->get_short_description();
		}

		return $description;
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

		return number_format( (float) $price, 2, '.', '' ) . ' ' . get_woocommerce_currency();
	}

	private function map_stock_status( string $stock_status ): string {
		$mapping = array(
			'instock'     => 'in stock',
			'outofstock'  => 'out of stock',
			'onbackorder' => 'backorder',
		);

		return $mapping[ $stock_status ] ?? 'out of stock';
	}
}
