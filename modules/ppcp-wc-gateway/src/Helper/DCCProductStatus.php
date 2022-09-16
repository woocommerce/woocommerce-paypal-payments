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
	 * DccProductStatus constructor.
	 *
	 * @param Settings         $settings The Settings.
	 * @param PartnersEndpoint $partners_endpoint The Partner Endpoint.
	 * @param Cache            $cache The cache.
	 */
	public function __construct(
		Settings $settings,
		PartnersEndpoint $partners_endpoint,
		Cache $cache
	) {
		$this->settings          = $settings;
		$this->partners_endpoint = $partners_endpoint;
		$this->cache             = $cache;
	}

	/**
	 * Whether the active/subscribed products support DCC.
	 *
	 * @return bool
	 */
	public function dcc_is_active() : bool {
		if ( $this->cache->has( self::DCC_STATUS_CACHE_KEY ) ) {
			return (bool) $this->cache->get( self::DCC_STATUS_CACHE_KEY );
		}

		if ( is_bool( $this->current_status_cache ) ) {
			return $this->current_status_cache;
		}

		if ( $this->settings->has( 'products_dcc_enabled' ) && $this->settings->get( 'products_dcc_enabled' ) ) {
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
				$this->cache->set( self::DCC_STATUS_CACHE_KEY, true, 3 * MONTH_IN_SECONDS );
				return true;
			}
		}
		$this->cache->set( self::DCC_STATUS_CACHE_KEY, false, 3 * MONTH_IN_SECONDS );

		$this->current_status_cache = false;
		return false;
	}
}
