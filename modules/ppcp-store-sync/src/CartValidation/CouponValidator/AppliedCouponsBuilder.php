<?php
/**
 * Applied Coupons Builder.
 *
 * Builds the applied_coupons array for successful cart responses.
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\CartValidation\CouponValidator
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\CartValidation\CouponValidator;

use WC_Coupon;
use WooCommerce\PayPalCommerce\StoreSync\Config\StoreCurrencyValue;
use WooCommerce\PayPalCommerce\StoreSync\Helper\CartHelper;
use WooCommerce\PayPalCommerce\StoreSync\Schema\PayPalCart;

/**
 * Builds applied coupons data for API responses.
 */
class AppliedCouponsBuilder {

	private DiscountCalculator $discount_calculator;

	private StoreCurrencyValue $store_currency;

	/**
	 * Constructor.
	 *
	 * @param DiscountCalculator $discount_calculator Discount calculator instance.
	 * @param StoreCurrencyValue $store_currency      Store currency resolver.
	 */
	public function __construct( DiscountCalculator $discount_calculator, StoreCurrencyValue $store_currency ) {
		$this->discount_calculator = $discount_calculator;
		$this->store_currency      = $store_currency;
	}

	/**
	 * Build applied_coupons array for successfully applied coupons.
	 *
	 * Only returns coupons when:
	 * - Cart validation status is VALID
	 * - Coupons have APPLY action
	 * - WooCommerce classes are available
	 *
	 * @param PayPalCart $cart              The PayPal cart.
	 * @param string     $validation_status The cart validation status.
	 * @return array Array of applied coupon data.
	 */
	public function build_applied_coupons_array( PayPalCart $cart, string $validation_status ): array {
		if ( $validation_status !== 'VALID' ) {
			return array();
		}

		if ( ! class_exists( 'WC_Coupon' ) ) {
			return array();
		}

		$coupons = $cart->coupons();

		if ( ! $coupons ) {
			return array();
		}

		// Only include coupons with APPLY action.
		$apply_coupons = array_filter(
			$coupons,
			static fn( $coupon ) => $coupon->action() === 'APPLY'
		);

		if ( empty( $apply_coupons ) ) {
			return array();
		}

		$applied       = array();
		$currency_code = CartHelper::currency( $cart, $this->store_currency->value() );

		foreach ( $apply_coupons as $coupon ) {
			$code = $coupon->code();

			// Normalize coupon code to match WooCommerce's case-insensitive behavior.
			$normalized_code = wc_sanitize_coupon_code( $code );

			$wc_coupon = new WC_Coupon( $normalized_code );

			if ( ! $wc_coupon->get_id() ) {
				continue;
			}

			// Calculate discount amount.
			$discount_amount = $this->discount_calculator->calculate_discount_amount(
				$wc_coupon,
				$cart
			);

			$applied[] = array(
				'code'            => $code,
				'description'     => $wc_coupon->get_description() ?: $wc_coupon->get_discount_type() . ' discount',
				'discount_amount' => array(
					'currency_code' => $currency_code,
					'value'         => $discount_amount,
				),
			);
		}

		return $applied;
	}

	/**
	 * Calculate the total discount amount from applied coupons.
	 *
	 * Used when updating PayPal orders to include the discount in the breakdown.
	 *
	 * @param PayPalCart $cart The cart object.
	 * @return float Total discount amount.
	 */
	public function calculate_total_discount( PayPalCart $cart ): float {
		$validation_status = $cart->issues() ? 'INVALID' : 'VALID';
		$applied_coupons   = $this->build_applied_coupons_array( $cart, $validation_status );

		return array_reduce(
			$applied_coupons,
			static function ( float $total, array $coupon ): float {
				$value = $coupon['discount_amount']['value'] ?? 0;

				return $total + (float) $value;
			},
			0.0
		);
	}
}
