<?php
/**
 * Manage the Seller status.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Helper
 */

declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\WcGateway\Helper;

use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PartnersEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\SellerStatusProduct;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
use WooCommerce\PayPalCommerce\WcGateway\Exception\NotFoundException;

/**
 * Class PayUponInvoiceProductStatus
 */
class PayUponInvoiceProductStatus {

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
	 * PayUponInvoiceProductStatus constructor.
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
	 * Whether the active/subscribed products support PUI.
	 *
	 * @return bool
	 */
	public function pui_is_active() : bool {
		if ( is_bool( $this->current_status_cache ) ) {
			return $this->current_status_cache;
		}
		if ( $this->settings->has( 'products_pui_enabled' ) && $this->settings->get( 'products_pui_enabled' ) ) {
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
				return true;
			}
		}

		$this->current_status_cache = false;
		return false;
	}
}
