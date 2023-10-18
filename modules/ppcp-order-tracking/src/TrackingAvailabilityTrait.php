<?php
/**
 * The order tracking module.
 *
 * @package WooCommerce\PayPalCommerce\OrderTracking
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\OrderTracking;

use WooCommerce\PayPalCommerce\ApiClient\Authentication\Bearer;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\Compat\AdminContextTrait;

trait TrackingAvailabilityTrait {

	use AdminContextTrait;

	/**
	 * Checks if tracking is enabled.
	 *
	 * @param Bearer $bearer The Bearer.
	 * @return bool
	 */
	protected function is_tracking_enabled( Bearer $bearer ): bool {
		try {
			$token = $bearer->bearer();
			return $token->is_tracking_available()
				&& $this->is_paypal_order_edit_page()
				&& apply_filters( 'woocommerce_paypal_payments_shipment_tracking_enabled', true );
		} catch ( RuntimeException $exception ) {
			return false;
		}
	}
}
