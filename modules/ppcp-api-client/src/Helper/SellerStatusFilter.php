<?php
/**
 * Filters and transforms SellerStatus responses.
 *
 * Provides the minimal plumbing for overriding, injecting, and removing
 * products and capabilities from a SellerStatus object. Also supports
 * providing a complete fallback SellerStatus for environments where the
 * merchant-integrations API is unavailable (e.g., PayPal Stage).
 *
 * All operations are additive at the product-capability level, so multiple
 * callers can register modifications without conflicting with each other.
 *
 * Extension point: {@see 'woocommerce_paypal_payments_seller_status_filter_init'}
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Helper
 */

declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\ApiClient\Helper;

use WooCommerce\PayPalCommerce\ApiClient\Entity\SellerStatus;
use WooCommerce\PayPalCommerce\ApiClient\Entity\SellerStatusCapability;
use WooCommerce\PayPalCommerce\ApiClient\Entity\SellerStatusProduct;

class SellerStatusFilter {

	/**
	 * @var SellerStatus|null
	 */
	private ?SellerStatus $fallback = null;

	/**
	 * Capabilities to add to specific products, keyed by product name.
	 *
	 * @var array<string, array<string, true>>
	 */
	private array $product_capability_additions = array();

	/**
	 * Capabilities to remove from specific products, keyed by product name.
	 *
	 * @var array<string, array<string, true>>
	 */
	private array $product_capability_removals = array();

	/**
	 * Product names to remove entirely.
	 *
	 * @var array<string, true>
	 */
	private array $removed_products = array();

	/**
	 * Top-level capabilities to ensure (inject or override to given status).
	 *
	 * @var array<string, SellerStatusCapability>
	 */
	private array $ensured_capabilities = array();

	/**
	 * Top-level capability names to remove.
	 *
	 * @var array<string, true>
	 */
	private array $removed_capabilities = array();

	/**
	 * @var string|null
	 */
	private ?string $country_override = null;

	// ------------------------------------------------------------------
	// Fallback
	// ------------------------------------------------------------------

	/**
	 * Sets a complete fallback SellerStatus for when the API fails.
	 */
	public function set_fallback( SellerStatus $fallback ): self {
		$this->fallback = $fallback;
		return $this;
	}

	public function get_fallback(): ?SellerStatus {
		return $this->fallback;
	}

	public function has_fallback(): bool {
		return null !== $this->fallback;
	}

	// ------------------------------------------------------------------
	// Product-level operations
	// ------------------------------------------------------------------

	/**
	 * Adds a capability to a product. Auto-creates the product if it
	 * does not exist in the response (with 'SUBSCRIBED' vetting status).
	 *
	 * Multiple callers can add capabilities to the same product without
	 * conflict — additions are merged, not replaced.
	 */
	public function add_product_capability( string $product_name, string $capability ): self {
		if ( ! isset( $this->product_capability_additions[ $product_name ] ) ) {
			$this->product_capability_additions[ $product_name ] = array();
		}
		$this->product_capability_additions[ $product_name ][ $capability ] = true;
		return $this;
	}

	/**
	 * Removes a single capability from a product.
	 */
	public function remove_product_capability( string $product_name, string $capability ): self {
		if ( ! isset( $this->product_capability_removals[ $product_name ] ) ) {
			$this->product_capability_removals[ $product_name ] = array();
		}
		$this->product_capability_removals[ $product_name ][ $capability ] = true;
		return $this;
	}

	/**
	 * Removes a product entirely by name.
	 */
	public function remove_product( string $name ): self {
		$this->removed_products[ $name ] = true;
		return $this;
	}

	// ------------------------------------------------------------------
	// Top-level capability operations
	// ------------------------------------------------------------------

	/**
	 * Ensures a top-level capability exists with the given status.
	 *
	 * If the capability already exists, its status is overridden.
	 * If it does not exist, it is injected.
	 */
	public function ensure_capability( string $name, string $status = SellerStatusCapability::STATUS_ACTIVE ): self {
		$this->ensured_capabilities[ $name ] = new SellerStatusCapability( $name, $status );
		return $this;
	}

	/**
	 * Removes a top-level capability by name.
	 */
	public function remove_capability( string $name ): self {
		$this->removed_capabilities[ $name ] = true;
		return $this;
	}

	// ------------------------------------------------------------------
	// Country
	// ------------------------------------------------------------------

	/**
	 * Overrides the country value in the SellerStatus.
	 */
	public function override_country( string $country ): self {
		$this->country_override = $country;
		return $this;
	}

	// ------------------------------------------------------------------
	// Application
	// ------------------------------------------------------------------

	/**
	 * Whether any transformations have been registered.
	 */
	public function has_modifications(): bool {
		return $this->fallback !== null
			|| ! empty( $this->product_capability_additions )
			|| ! empty( $this->product_capability_removals )
			|| ! empty( $this->removed_products )
			|| ! empty( $this->ensured_capabilities )
			|| ! empty( $this->removed_capabilities )
			|| $this->country_override !== null;
	}

	/**
	 * Applies all registered transformations to a SellerStatus object.
	 */
	public function apply( SellerStatus $status ): SellerStatus {
		if ( ! $this->has_modifications() ) {
			return $status;
		}

		$products     = $this->apply_product_transformations( $status->products() );
		$capabilities = $this->apply_capability_transformations( $status->capabilities() );
		$country      = $this->country_override ?? $status->country();

		return new SellerStatus( $products, $capabilities, $country );
	}

	/**
	 * @param SellerStatusProduct[] $products
	 * @return SellerStatusProduct[]
	 */
	private function apply_product_transformations( array $products ): array {
		// 1. Remove entire products.
		$products = array_filter(
			$products,
			function ( SellerStatusProduct $product ): bool {
				return ! isset( $this->removed_products[ $product->name() ] );
			}
		);

		// 2. Add/remove individual capabilities on existing products.
		$products = array_map(
			function ( SellerStatusProduct $product ): SellerStatusProduct {
				$name         = $product->name();
				$capabilities = $product->capabilities();

				$removals = $this->product_capability_removals[ $name ] ?? array();
				if ( ! empty( $removals ) ) {
					$capabilities = array_values(
						array_filter(
							$capabilities,
							static function ( string $cap ) use ( $removals ): bool {
								return ! isset( $removals[ $cap ] );
							}
						)
					);
				}

				$additions = $this->product_capability_additions[ $name ] ?? array();
				if ( ! empty( $additions ) ) {
					$existing = array_flip( $capabilities );
					foreach ( array_keys( $additions ) as $cap ) {
						if ( ! isset( $existing[ $cap ] ) ) {
							$capabilities[] = $cap;
						}
					}
				}

				if ( $capabilities === $product->capabilities() ) {
					return $product;
				}

				return new SellerStatusProduct( $name, $product->vetting_status(), $capabilities );
			},
			$products
		);

		// 3. Auto-create products for additions targeting non-existing products.
		$existing_names = array();
		foreach ( $products as $product ) {
			$existing_names[ $product->name() ] = true;
		}

		foreach ( $this->product_capability_additions as $product_name => $caps ) {
			if ( isset( $existing_names[ $product_name ] ) ) {
				continue;
			}

			$products[]                      = new SellerStatusProduct( $product_name, 'SUBSCRIBED', array_keys( $caps ) );
			$existing_names[ $product_name ] = true;
		}

		return array_values( $products );
	}

	/**
	 * @param SellerStatusCapability[] $capabilities
	 * @return SellerStatusCapability[]
	 */
	private function apply_capability_transformations( array $capabilities ): array {
		// 1. Remove.
		$capabilities = array_filter(
			$capabilities,
			function ( SellerStatusCapability $capability ): bool {
				return ! isset( $this->removed_capabilities[ $capability->name() ] );
			}
		);

		// 2. Apply ensured capabilities (override existing or append new).
		$existing_names = array();
		foreach ( $capabilities as $capability ) {
			$existing_names[ $capability->name() ] = true;
		}

		$ensured      = $this->ensured_capabilities;
		$capabilities = array_map(
			static function ( SellerStatusCapability $capability ) use ( $ensured ): SellerStatusCapability {
				return $ensured[ $capability->name() ] ?? $capability;
			},
			$capabilities
		);

		foreach ( $ensured as $name => $ensured_cap ) {
			if ( ! isset( $existing_names[ $name ] ) ) {
				$capabilities[] = $ensured_cap;
			}
		}

		return array_values( $capabilities );
	}
}
