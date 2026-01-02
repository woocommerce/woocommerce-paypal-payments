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
use WooCommerce\PayPalCommerce\ApiClient\Helper\FailureRegistry;
use WooCommerce\PayPalCommerce\Settings\Data\PaymentSettings;
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

	/**
	 * The settings.
	 *
	 * @var PaymentSettings
	 */
	private PaymentSettings $settings;

	/**
	 * AppleProductStatus constructor.
	 *
	 * @param PaymentSettings  $settings             The Payment Settings.
	 * @param PartnersEndpoint $partners_endpoint    The Partner Endpoint.
	 * @param bool             $is_connected         The onboarding state.
	 * @param FailureRegistry  $api_failure_registry The API failure registry.
	 */
	public function __construct(
		PaymentSettings $settings,
		PartnersEndpoint $partners_endpoint,
		bool $is_connected,
		FailureRegistry $api_failure_registry
	) {
		parent::__construct( $is_connected, $partners_endpoint, $api_failure_registry );

		$this->settings = $settings;
	}

	/** {@inheritDoc} */
	protected function check_local_state(): ?bool {
		$status_override = apply_filters( 'woocommerce_paypal_payments_apple_pay_product_status', null );
		if ( null !== $status_override ) {
			return $status_override;
		}

		$status = $this->settings->get_products_apple_enabled();
		if ( $status !== '' ) {
			return wc_string_to_bool( $status );
		}

		return null;
	}

	/** {@inheritDoc} */
	protected function check_active_state( SellerStatus $seller_status ): bool {
		// Check the seller status for the intended capability.
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

		if ( $has_capability ) {
			$this->settings->set_products_apple_enabled( self::SETTINGS_VALUE_ENABLED );
		} else {
			$this->settings->set_products_apple_enabled( self::SETTINGS_VALUE_DISABLED );
		}
		$this->settings->save();

		return $has_capability;
	}

	/** {@inheritDoc} */
	protected function clear_state( ?PaymentSettings $settings = null ): void {
		if ( null === $settings ) {
			$settings = $this->settings;
		}

		$settings->set_products_apple_enabled( self::SETTINGS_VALUE_UNDEFINED );
		$settings->save();
	}
}
