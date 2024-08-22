<?php
/**
 * Status of the ApplePay merchant connection.
 *
 * @package WooCommerce\PayPalCommerce\Applepay\Assets
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Applepay\Assets;

use Throwable;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PartnersEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\SellerStatusCapability;
use WooCommerce\PayPalCommerce\ApiClient\Helper\FailureRegistry;
use WooCommerce\PayPalCommerce\Onboarding\State;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;

/**
 * Class AppleProductStatus
 */
class AppleProductStatus {
	const CAPABILITY_NAME = 'APPLE_PAY';
	const SETTINGS_KEY    = 'products_apple_enabled';

	const SETTINGS_VALUE_ENABLED   = 'yes';
	const SETTINGS_VALUE_DISABLED  = 'no';
	const SETTINGS_VALUE_UNDEFINED = '';

	/**
	 * The current status stored in memory.
	 *
	 * @var bool|null
	 */
	private $current_status = null;

	/**
	 * If there was a request failure.
	 *
	 * @var bool
	 */
	private $has_request_failure = false;

	/**
	 * The settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * The partners endpoint.
	 *
	 * @var PartnersEndpoint
	 */
	private $partners_endpoint;

	/**
	 * The onboarding status
	 *
	 * @var State
	 */
	private $onboarding_state;

	/**
	 * The API failure registry
	 *
	 * @var FailureRegistry
	 */
	private $api_failure_registry;

	/**
	 * AppleProductStatus constructor.
	 *
	 * @param Settings         $settings The Settings.
	 * @param PartnersEndpoint $partners_endpoint The Partner Endpoint.
	 * @param State            $onboarding_state The onboarding state.
	 * @param FailureRegistry  $api_failure_registry The API failure registry.
	 */
	public function __construct(
		Settings $settings,
		PartnersEndpoint $partners_endpoint,
		State $onboarding_state,
		FailureRegistry $api_failure_registry
	) {
		$this->settings             = $settings;
		$this->partners_endpoint    = $partners_endpoint;
		$this->onboarding_state     = $onboarding_state;
		$this->api_failure_registry = $api_failure_registry;
	}

	/**
	 * Whether the active/subscribed products support Applepay.
	 *
	 * @return bool
	 */
	public function is_active() : bool {

		// If not onboarded then makes no sense to check status.
		if ( ! $this->is_onboarded() ) {
			return false;
		}

		$status_override = apply_filters( 'woocommerce_paypal_payments_apple_pay_product_status', null );
		if ( null !== $status_override ) {
			return $status_override;
		}

		// If status was already checked on this request return the same result.
		if ( null !== $this->current_status ) {
			return $this->current_status;
		}

		// Check if status was checked on previous requests.
		if ( $this->settings->has( self::SETTINGS_KEY ) && ( $this->settings->get( self::SETTINGS_KEY ) ) ) {
			$this->current_status = wc_string_to_bool( $this->settings->get( self::SETTINGS_KEY ) );
			return $this->current_status;
		}

		// Check API failure registry to prevent multiple failed API requests.
		if ( $this->api_failure_registry->has_failure_in_timeframe( FailureRegistry::SELLER_STATUS_KEY, HOUR_IN_SECONDS ) ) {
			$this->has_request_failure = true;
			$this->current_status      = false;
			return $this->current_status;
		}

		// Request seller status via PayPal API.
		try {
			$seller_status = $this->partners_endpoint->seller_status();
		} catch ( Throwable $error ) {
			$this->has_request_failure = true;
			$this->current_status      = false;
			return $this->current_status;
		}

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

		foreach ( $seller_status->capabilities() as $capability ) {
			if ( $capability->name() === self::CAPABILITY_NAME && $capability->status() === SellerStatusCapability::STATUS_ACTIVE ) {
				$has_capability = true;
			}
		}

		if ( $has_capability ) {
			// Capability found, persist status and return true.
			$this->settings->set( self::SETTINGS_KEY, self::SETTINGS_VALUE_ENABLED );
			$this->settings->persist();

			$this->current_status = true;
			return $this->current_status;
		}

		// Capability not found, persist status and return false.
		$this->settings->set( self::SETTINGS_KEY, self::SETTINGS_VALUE_DISABLED );
		$this->settings->persist();

		$this->current_status = false;
		return $this->current_status;
	}

	/**
	 * Returns if the seller is onboarded.
	 *
	 * @return bool
	 */
	public function is_onboarded(): bool {
		return $this->onboarding_state->current_state() >= State::STATE_ONBOARDED;
	}

	/**
	 * Returns if there was a request failure.
	 *
	 * @return bool
	 */
	public function has_request_failure(): bool {
		return $this->has_request_failure;
	}

	/**
	 * Clears the persisted result to force a recheck.
	 *
	 * @param Settings|null $settings The settings object.
	 * We accept a Settings object to don't override other sequential settings that are being updated elsewhere.
	 * @return void
	 */
	public function clear( Settings $settings = null ): void {
		if ( null === $settings ) {
			$settings = $this->settings;
		}

		$this->current_status = null;

		if ( $settings->has( self::SETTINGS_KEY ) ) {
			$settings->set( self::SETTINGS_KEY, self::SETTINGS_VALUE_UNDEFINED );
			$settings->persist();
		}
	}

}
