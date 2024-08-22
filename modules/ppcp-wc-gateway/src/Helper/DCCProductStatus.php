<?php
/**
 * Manage the Seller status.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Helper
 */

declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\WcGateway\Helper;

use Throwable;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PartnersEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\SellerStatusProduct;
use WooCommerce\PayPalCommerce\ApiClient\Helper\Cache;
use WooCommerce\PayPalCommerce\ApiClient\Helper\DccApplies;
use WooCommerce\PayPalCommerce\ApiClient\Helper\FailureRegistry;
use WooCommerce\PayPalCommerce\Onboarding\State;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;

/**
 * Class DccProductStatus
 */
class DCCProductStatus {

	const DCC_STATUS_CACHE_KEY = 'dcc_status_cache';

	/**
	 * The Cache.
	 *
	 * @var Cache
	 */
	protected $cache;

	/**
	 * Caches the status for the current load.
	 *
	 * @var bool|null
	 */
	private $current_status_cache;

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
	 * The dcc applies helper.
	 *
	 * @var DccApplies
	 */
	protected $dcc_applies;

	/**
	 * The onboarding state.
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
	 * DccProductStatus constructor.
	 *
	 * @param Settings         $settings The Settings.
	 * @param PartnersEndpoint $partners_endpoint The Partner Endpoint.
	 * @param Cache            $cache The cache.
	 * @param DccApplies       $dcc_applies The dcc applies helper.
	 * @param State            $onboarding_state The onboarding state.
	 * @param FailureRegistry  $api_failure_registry The API failure registry.
	 */
	public function __construct(
		Settings $settings,
		PartnersEndpoint $partners_endpoint,
		Cache $cache,
		DccApplies $dcc_applies,
		State $onboarding_state,
		FailureRegistry $api_failure_registry
	) {
		$this->settings             = $settings;
		$this->partners_endpoint    = $partners_endpoint;
		$this->cache                = $cache;
		$this->dcc_applies          = $dcc_applies;
		$this->onboarding_state     = $onboarding_state;
		$this->api_failure_registry = $api_failure_registry;
	}

	/**
	 * Whether the active/subscribed products support DCC.
	 *
	 * @return bool
	 */
	public function dcc_is_active() : bool {
		if ( $this->onboarding_state->current_state() < State::STATE_ONBOARDED ) {
			return false;
		}

		if ( $this->cache->has( self::DCC_STATUS_CACHE_KEY ) ) {
			return $this->cache->get( self::DCC_STATUS_CACHE_KEY ) === 'true';
		}

		if ( $this->current_status_cache === true ) {
			return $this->current_status_cache;
		}

		if ( $this->settings->has( 'products_dcc_enabled' ) && $this->settings->get( 'products_dcc_enabled' ) === true ) {
			$this->current_status_cache = true;
			return true;
		}

		// Check API failure registry to prevent multiple failed API requests.
		if ( $this->api_failure_registry->has_failure_in_timeframe( FailureRegistry::SELLER_STATUS_KEY, HOUR_IN_SECONDS ) ) {
			$this->has_request_failure  = true;
			$this->current_status_cache = false;
			return $this->current_status_cache;
		}

		try {
			$seller_status = $this->partners_endpoint->seller_status();
		} catch ( Throwable $error ) {
			$this->has_request_failure  = true;
			$this->current_status_cache = false;
			return false;
		}

		foreach ( $seller_status->products() as $product ) {
			if ( ! in_array(
				$product->vetting_status(),
				array(
					SellerStatusProduct::VETTING_STATUS_APPROVED,
					SellerStatusProduct::VETTING_STATUS_SUBSCRIBED,
				),
				true
			)
			) {
				continue;
			}

			if ( in_array( 'CUSTOM_CARD_PROCESSING', $product->capabilities(), true ) ) {
				$this->settings->set( 'products_dcc_enabled', true );
				$this->settings->persist();
				$this->current_status_cache = true;
				$this->cache->set( self::DCC_STATUS_CACHE_KEY, 'true', MONTH_IN_SECONDS );
				return true;
			}
		}

		$expiration = MONTH_IN_SECONDS;
		if ( $this->dcc_applies->for_country_currency() ) {
			$expiration = 3 * HOUR_IN_SECONDS;
		}
		$this->cache->set( self::DCC_STATUS_CACHE_KEY, 'false', $expiration );

		$this->current_status_cache = false;
		return false;
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

		// Unset check stored in memory.
		$this->current_status_cache = null;

		// Unset settings flag.
		$settings_key = 'products_dcc_enabled';
		if ( $settings->has( $settings_key ) ) {
			$settings->set( $settings_key, false );
			$settings->persist();
		}

		// Delete cached value.
		$this->cache->delete( self::DCC_STATUS_CACHE_KEY );
	}

}
