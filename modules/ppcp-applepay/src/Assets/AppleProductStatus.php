<?php
/**
 * Status of the ApplePay merchant connection.
 *
 * @package WooCommerce\PayPalCommerce\Applepay\Assets
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Applepay\Assets;

use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PartnersEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\SellerStatusCapability;
use WooCommerce\PayPalCommerce\ApiClient\Helper\Cache;
use WooCommerce\PayPalCommerce\ApiClient\Helper\FailureRegistry;
use WooCommerce\PayPalCommerce\ApiClient\Helper\ProductStatus;
use WooCommerce\PayPalCommerce\ApiClient\Entity\SellerStatus;

/**
 * Class AppleProductStatus
 */
class AppleProductStatus extends ProductStatus {
	public const CAPABILITY_NAME = 'APPLE_PAY';
	public const SETTINGS_KEY    = 'products_apple_enabled';

	public const SETTINGS_VALUE_ENABLED   = 'yes';
	public const SETTINGS_VALUE_DISABLED  = 'no';
	public const SETTINGS_VALUE_UNDEFINED = '';

	private Cache $cache;

	public function __construct(
		Cache $cache,
		PartnersEndpoint $partners_endpoint,
		bool $is_connected,
		FailureRegistry $api_failure_registry
	) {
		parent::__construct( $is_connected, $partners_endpoint, $api_failure_registry );

		$this->cache = $cache;
	}

	/** {@inheritDoc} */
	protected function check_local_state(): ?bool {
		$status_override = apply_filters( 'woocommerce_paypal_payments_apple_pay_product_status', null );
		if ( null !== $status_override ) {
			return $status_override;
		}

		if ( $this->cache->has( self::SETTINGS_KEY ) ) {
			return wc_string_to_bool( $this->cache->get( self::SETTINGS_KEY ) );
		}

		return null;
	}

	/** {@inheritDoc} */
	protected function check_active_state( SellerStatus $seller_status ): bool {
		$has_capability = false;
		foreach ( $seller_status->products() as $product ) {
			if ( $product->name() !== 'PAYMENT_METHODS' ) {
				continue;
			}

			if ( in_array( self::CAPABILITY_NAME, $product->capabilities(), true ) ) {
				$has_capability = true;
			}
		}

		if ( ! $has_capability ) {
			foreach ( $seller_status->capabilities() as $capability ) {
				if ( $capability->name() === self::CAPABILITY_NAME && $capability->status() === SellerStatusCapability::STATUS_ACTIVE ) {
					$has_capability = true;
				}
			}
		}

		$this->cache->set(
			self::SETTINGS_KEY,
			$has_capability ? self::SETTINGS_VALUE_ENABLED : self::SETTINGS_VALUE_DISABLED,
			MONTH_IN_SECONDS
		);

		return $has_capability;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Cache|null $cache The cache instance.
	 * @psalm-suppress MethodSignatureMismatch
	 * @psalm-suppress ImplementedParamTypeMismatch
	 */
	protected function clear_state( ?Cache $cache = null ): void {
		$cache = $cache ?? $this->cache;
		$cache->delete( self::SETTINGS_KEY );
	}
}
