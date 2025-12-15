<?php
/**
 * Coupon Context Builder for Agentic Commerce.
 *
 * Builds enhanced context data for coupon validation issues.
 *
 * @package WooCommerce\PayPalCommerce\AgenticCommerce\CartValidation\CouponValidator
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\CartValidation\CouponValidator;

use WC_Coupon;
use WooCommerce\PayPalCommerce\AgenticCommerce\Helper\CartHelper;
use WooCommerce\PayPalCommerce\AgenticCommerce\Helper\ProductManager;
use WooCommerce\PayPalCommerce\AgenticCommerce\Schema\PayPalCart;

/**
 * Builds context data for coupon validation issues.
 */
class ContextBuilder {

	/**
	 * Product manager for resolving cart items.
	 *
	 * @var ProductManager
	 */
	private ProductManager $product_manager;

	/**
	 * Discount calculator for coupon amounts.
	 *
	 * @var DiscountCalculator
	 */
	private DiscountCalculator $discount_calculator;

	public function __construct( ProductManager $product_manager, DiscountCalculator $discount_calculator ) {
		$this->product_manager     = $product_manager;
		$this->discount_calculator = $discount_calculator;
	}

	/**
	 * Builds context by calling declared context builders.
	 *
	 * @param string         $issue_type The issue type.
	 * @param string         $code The coupon code.
	 * @param PayPalCart     $cart The cart context.
	 * @param WC_Coupon|null $wc_coupon The WC coupon object.
	 * @param array          $builders Array of builder names to call.
	 * @param array          $extra Extra context data.
	 * @return array The built context.
	 */
	public function build(
		string $issue_type,
		string $code,
		PayPalCart $cart,
		?WC_Coupon $wc_coupon,
		array $builders,
		array $extra = array()
	): array {
		$context = array(
			'specific_issue' => $issue_type,
			'coupon_code'    => $code,
		);

		foreach ( $builders as $builder ) {
			$method = "build_{$builder}";
			if ( method_exists( $this, $method ) ) {
				$context = array_merge( $context, $this->$method( $code, $cart, $wc_coupon, array_merge( $context, $extra ) ) );
			}
		}

		return array_merge( $context, $extra );
	}

	/**
	 * Builds alternative coupons context.
	 *
	 * @param string         $code The coupon code.
	 * @param PayPalCart     $cart The cart context.
	 * @param WC_Coupon|null $wc_coupon The WC coupon object.
	 * @param array          $extra Extra context data.
	 * @return array The context data.
	 */
	private function build_alternatives( string $code, PayPalCart $cart, ?WC_Coupon $wc_coupon, array $extra ): array {
		$alternatives = $this->get_alternative_coupons( $code, $extra['specific_issue'] ?? 'COUPON_INVALID', $cart );

		if ( empty( $alternatives ) ) {
			return array();
		}

		return array(
			'suggested_alternatives' => $alternatives,
			'available_coupons'      => true,
		);
	}

	/**
	 * Builds expiration context.
	 *
	 * @param string         $code The coupon code.
	 * @param PayPalCart     $cart The cart context.
	 * @param WC_Coupon|null $wc_coupon The WC coupon object.
	 * @param array          $extra Extra context data.
	 * @return array The context data.
	 */
	private function build_expiration( string $code, PayPalCart $cart, ?WC_Coupon $wc_coupon, array $extra ): array {
		if ( ! $wc_coupon || ! $wc_coupon->get_date_expires() ) {
			return array();
		}

		return array(
			'expiration_date' => $wc_coupon->get_date_expires()->format( 'c' ),
		);
	}

	/**
	 * Builds usage limits context.
	 *
	 * @param string         $code The coupon code.
	 * @param PayPalCart     $cart The cart context.
	 * @param WC_Coupon|null $wc_coupon The WC coupon object.
	 * @param array          $extra Extra context data.
	 * @return array The context data.
	 */
	private function build_usage_limits( string $code, PayPalCart $cart, ?WC_Coupon $wc_coupon, array $extra ): array {
		if ( ! $wc_coupon ) {
			return array();
		}

		return array(
			'usage_limit'   => $wc_coupon->get_usage_limit(),
			'current_usage' => $wc_coupon->get_usage_count(),
		);
	}

	/**
	 * Builds minimum spend context.
	 *
	 * @param string         $code The coupon code.
	 * @param PayPalCart     $cart The cart context.
	 * @param WC_Coupon|null $wc_coupon The WC coupon object.
	 * @param array          $extra Extra context data.
	 * @return array The context data.
	 */
	private function build_minimum_spend( string $code, PayPalCart $cart, ?WC_Coupon $wc_coupon, array $extra ): array {
		if ( ! $wc_coupon ) {
			return array();
		}

		$subtotal = CartHelper::cart_item_total( $cart );
		$minimum  = (float) $wc_coupon->get_minimum_amount();
		$shortage = max( 0, $minimum - $subtotal );
		$currency = CartHelper::currency( $cart, get_woocommerce_currency() );

		return array(
			'minimum_required' => wc_format_decimal( $minimum, 2 ),
			'current_subtotal' => wc_format_decimal( $subtotal, 2 ),
			'shortage_amount'  => wc_format_decimal( $shortage, 2 ),
			'currency_code'    => $currency,
		);
	}

	/**
	 * Builds maximum spend context.
	 *
	 * @param string         $code The coupon code.
	 * @param PayPalCart     $cart The cart context.
	 * @param WC_Coupon|null $wc_coupon The WC coupon object.
	 * @param array          $extra Extra context data.
	 * @return array The context data.
	 */
	private function build_maximum_spend( string $code, PayPalCart $cart, ?WC_Coupon $wc_coupon, array $extra ): array {
		if ( ! $wc_coupon ) {
			return array();
		}

		$subtotal = CartHelper::cart_item_total( $cart );
		$maximum  = (float) $wc_coupon->get_maximum_amount();
		$currency = CartHelper::currency( $cart, get_woocommerce_currency() );

		return array(
			'maximum_allowed'  => wc_format_decimal( $maximum, 2 ),
			'current_subtotal' => wc_format_decimal( $subtotal, 2 ),
			'currency_code'    => $currency,
		);
	}

	/**
	 * Builds eligible items context.
	 *
	 * @param string         $code The coupon code.
	 * @param PayPalCart     $cart The cart context.
	 * @param WC_Coupon|null $wc_coupon The WC coupon object.
	 * @param array          $extra Extra context data.
	 * @return array The context data.
	 */
	private function build_eligible_items( string $code, PayPalCart $cart, ?WC_Coupon $wc_coupon, array $extra ): array {
		if ( ! $wc_coupon ) {
			return array();
		}

		$eligible = $this->get_eligible_items( $wc_coupon, $cart );

		if ( empty( $eligible ) ) {
			return array();
		}

		return array( 'eligible_items' => $eligible );
	}

	/**
	 * Builds stacking conflict context.
	 *
	 * @param string         $code The coupon code.
	 * @param PayPalCart     $cart The cart context.
	 * @param WC_Coupon|null $wc_coupon The WC coupon object.
	 * @param array          $extra Extra context data.
	 * @return array The context data.
	 */
	private function build_stacking( string $code, PayPalCart $cart, ?WC_Coupon $wc_coupon, array $extra ): array {
		$other_codes = $extra['other_codes'] ?? array();

		if ( empty( $other_codes ) ) {
			return array();
		}

		$current_discount   = $wc_coupon ? $this->discount_calculator->calculate( $wc_coupon, $cart ) : '0.00';
		$attempted_discount = '0.00';

		// Normalize coupon code to match WooCommerce's case-insensitive behavior.
		$normalized_other_code = wc_sanitize_coupon_code( $other_codes[0] );

		$other_coupon = new WC_Coupon( $normalized_other_code );
		if ( $other_coupon->get_id() ) {
			$attempted_discount = $this->discount_calculator->calculate( $other_coupon, $cart );
		}

		return array(
			'current_coupon'     => $code,
			'attempted_coupon'   => $other_codes[0],
			'attempted_coupons'  => $other_codes,
			'current_discount'   => $current_discount,
			'attempted_discount' => $attempted_discount,
		);
	}

	/**
	 * Builds email restriction context.
	 *
	 * @param string         $code The coupon code.
	 * @param PayPalCart     $cart The cart context.
	 * @param WC_Coupon|null $wc_coupon The WC coupon object.
	 * @param array          $extra Extra context data.
	 * @return array The context data.
	 */
	private function build_email_restriction( string $code, PayPalCart $cart, ?WC_Coupon $wc_coupon, array $extra ): array {
		if ( ! $wc_coupon ) {
			return array();
		}

		$restrictions = $wc_coupon->get_email_restrictions();

		if ( empty( $restrictions ) ) {
			return array();
		}

		return array( 'email_restricted' => true );
	}

	/**
	 * Gets alternative coupon suggestions via filter.
	 *
	 * @param string     $failed_code The coupon code that failed.
	 * @param string     $reason The failure reason.
	 * @param PayPalCart $cart The cart context.
	 * @return array Array of alternative coupon codes.
	 */
	private function get_alternative_coupons( string $failed_code, string $reason, PayPalCart $cart ): array {
		return apply_filters(
			'woocommerce_paypal_payments_agentic_commerce_suggested_alternative_coupons',
			array(),
			$failed_code,
			$reason,
			$cart
		);
	}

	/**
	 * Gets eligible items for a coupon via filter.
	 *
	 * Uses WooCommerce's native is_valid_for_product() method to check eligibility,
	 * which handles all coupon restrictions including product IDs, categories,
	 * excluded items, sale items, and third-party plugin logic.
	 *
	 * @param WC_Coupon  $wc_coupon The WC coupon.
	 * @param PayPalCart $cart The cart context.
	 * @return array Array of eligible variant IDs.
	 */
	private function get_eligible_items( WC_Coupon $wc_coupon, PayPalCart $cart ): array {
		$eligible = array();

		foreach ( $cart->items() as $item ) {
			$product = $this->product_manager->find_product( $item );

			if ( ! $product ) {
				continue;
			}

			// Use WooCommerce's native validation which handles all restrictions
			// including products, categories, exclusions, sale items, and plugin extensions.
			if ( $wc_coupon->is_valid_for_product( $product, array( 'data' => $product ) ) ) {
				$eligible[] = $item->variant_id();
			}
		}

		return $eligible;
	}
}
