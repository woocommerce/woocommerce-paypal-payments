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
use WooCommerce\PayPalCommerce\Onboarding\State;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;

/**
 * Class PayUponInvoiceProductStatus
 */
class PayUponInvoiceProductStatus {

	const PUI_STATUS_CACHE_KEY = 'pui_status_cache';

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
	 * PayUponInvoiceProductStatus constructor.
	 *
	 * @param Settings         $settings The Settings.
	 * @param PartnersEndpoint $partners_endpoint The Partner Endpoint.
	 * @param Cache            $cache The cache.
	 * @param State            $onboarding_state The onboarding state.
	 */
	public function __construct(
		Settings $settings,
		PartnersEndpoint $partners_endpoint,
		Cache $cache,
		State $onboarding_state
	) {
		$this->settings          = $settings;
		$this->partners_endpoint = $partners_endpoint;
		$this->cache             = $cache;
		$this->onboarding_state  = $onboarding_state;
	}

	/**
	 * Whether the active/subscribed products support PUI.
	 *
	 * @return bool
	 */
	public function pui_is_active() : bool {
		if ( $this->onboarding_state->current_state() < State::STATE_ONBOARDED ) {
			return false;
		}

		if ( $this->cache->has( self::PUI_STATUS_CACHE_KEY ) ) {
			return $this->cache->get( self::PUI_STATUS_CACHE_KEY ) === 'true';
		}

		if ( $this->current_status_cache === true ) {
			return $this->current_status_cache;
		}
		if ( $this->settings->has( 'products_pui_enabled' ) && $this->settings->get( 'products_pui_enabled' ) === true ) {
			$this->current_status_cache = true;
			return true;
		}

		try {
			$seller_status = $this->partners_endpoint->seller_status();
		} catch ( Throwable $error ) {
			$this->current_status_cache = false;
			return false;
		}

		foreach ( $seller_status->products() as $product ) {
			if ( $product->name() !== 'PAYMENT_METHODS' ) {
				continue;
			}

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

			if ( in_array( 'PAY_UPON_INVOICE', $product->capabilities(), true ) ) {
				$this->settings->set( 'products_pui_enabled', true );
				$this->settings->persist();
				$this->current_status_cache = true;
				$this->cache->set( self::PUI_STATUS_CACHE_KEY, 'true', 3 * MONTH_IN_SECONDS );
				return true;
			}
		}
		$this->cache->set( self::PUI_STATUS_CACHE_KEY, 'false', 3 * MONTH_IN_SECONDS );

		$this->current_status_cache = false;
		return false;
	}
}
