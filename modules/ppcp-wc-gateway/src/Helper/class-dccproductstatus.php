<?php
/**
 * Manage the Seller status.
 *
 * @package Woocommerce\PayPalCommerce\WcGateway\Helper
 */

declare( strict_types=1 );

namespace Woocommerce\PayPalCommerce\WcGateway\Helper;

use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PartnersEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\SellerStatusProduct;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;

/**
 * Class DccProductStatus
 */
class DccProductStatus {

	/**
	 * Caches the status for the current load.
	 *
	 * @var string|null
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
	 */
	public function __construct(
		Settings $settings,
		PartnersEndpoint $partners_endpoint
	) {
		$this->settings          = $settings;
		$this->partners_endpoint = $partners_endpoint;
	}

	/**
	 * Whether the active/subscribed products support DCC.
	 *
	 * @return bool
	 * @throws \WooCommerce\PayPalCommerce\WcGateway\Exception\NotFoundException Should a setting not be found.
	 */
	public function dcc_is_active() : bool {
		if ( is_bool( $this->current_status_cache ) ) {
			return $this->current_status_cache;
		}
		if ( $this->settings->has( 'products_dcc_enabled' ) && $this->settings->get( 'products_dcc_enabled' ) ) {
			$this->current_status_cache = true;
			return true;
		}

		try {
			$seller_status = $this->partners_endpoint->seller_status();
		} catch ( RuntimeException $error ) {
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
				return true;
			}
		}

		$this->current_status_cache = false;
		return false;
	}
}
