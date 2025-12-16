<?php
/**
 * Coupon Resolution Builder for Agentic Commerce.
 *
 * Builds resolution options for coupon validation issues.
 *
 * @package WooCommerce\PayPalCommerce\AgenticCommerce\CartValidation\CouponValidator
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\CartValidation\CouponValidator;

use WC_Coupon;
use WooCommerce\PayPalCommerce\AgenticCommerce\Helper\CartHelper;
use WooCommerce\PayPalCommerce\AgenticCommerce\Schema\PayPalCart;

/**
 * Builds resolution options for coupon validation issues.
 */
class ResolutionBuilder {

	/**
	 * Resolution templates.
	 *
	 * @var array
	 */
	private const RESOLUTIONS = array(
		'try_different'        => array(
			'action'   => 'RETRY_REQUEST',
			'label'    => 'Try a different coupon code',
			'metadata' => array( 'priority' => 'high' ),
		),
		'remove'               => array(
			'action'   => 'REMOVE_COUPON',
			'label'    => 'Continue without coupon',
			'metadata' => array( 'priority' => 'medium' ),
		),
		'modify_cart'          => array(
			'action'   => 'MODIFY_CART',
			'label'    => 'Add eligible items to use this coupon',
			'metadata' => array( 'priority' => 'high' ),
		),
		'view_available'       => array(
			'action'   => 'VIEW_AVAILABLE_COUPONS',
			'label'    => 'View available offers',
			'metadata' => array( 'priority' => 'low' ),
		),
		'suggest_alternative'  => array(
			'action'   => 'SUGGEST_ALTERNATIVE_COUPON',
			'label'    => 'Try a different coupon',
			'metadata' => array( 'priority' => 'medium' ),
		),
		'add_items_to_minimum' => array(
			'action'   => 'ADD_ITEMS_TO_REACH_MINIMUM',
			'label'    => 'Add %s more to qualify',
			'metadata' => array( 'priority' => 'high' ),
		),
		'continue_without'     => array(
			'action'   => 'CONTINUE_WITHOUT_COUPON',
			'label'    => 'Continue without coupon',
			'metadata' => array( 'priority' => 'low' ),
		),
	);

	/**
	 * Builds resolutions from config keys, with special handling for stacking.
	 *
	 * @param string         $issue_type The issue type.
	 * @param array          $keys Resolution keys from config.
	 * @param string         $code The coupon code.
	 * @param array          $context The context data.
	 * @param PayPalCart     $cart The cart context.
	 * @param WC_Coupon|null $wc_coupon The WC coupon object.
	 * @return array The resolution options.
	 */
	public function build_resolution_options(
		string $issue_type,
		array $keys,
		string $code,
		array $context,
		PayPalCart $cart,
		?WC_Coupon $wc_coupon
	): array {
		// Special case: stacking needs dynamic resolutions with savings.
		if ( $issue_type === 'COUPON_STACKING_NOT_ALLOWED' ) {
			return $this->build_stacking_resolutions( $code, $context, $cart );
		}

		$resolutions = array();

		foreach ( $keys as $key ) {
			if ( ! isset( self::RESOLUTIONS[ $key ] ) ) {
				continue;
			}

			$resolution = self::RESOLUTIONS[ $key ];

			if ( $key === 'add_items_to_minimum' && isset( $context['shortage_amount'] ) ) {
				$formatted_amount                        = CartHelper::format_price( $context['shortage_amount'], $cart );
				$resolution['label']                     = sprintf( $resolution['label'], $formatted_amount );
				$resolution['metadata']['amount_needed'] = $formatted_amount;
			}

			$resolutions[] = $resolution;
		}

		return $resolutions;
	}

	/**
	 * Builds stacking-specific resolutions with savings comparison.
	 *
	 * @param string     $code The coupon code.
	 * @param array      $context The context data.
	 * @param PayPalCart $cart The cart context.
	 * @return array The resolution options.
	 */
	private function build_stacking_resolutions( string $code, array $context, PayPalCart $cart ): array {
		$current_discount   = $context['current_discount'] ?? '0.00';
		$attempted_discount = $context['attempted_discount'] ?? '0.00';
		$attempted_coupon   = $context['attempted_coupon'] ?? 'other';

		$formatted_current   = CartHelper::format_price( $current_discount, $cart );
		$formatted_attempted = CartHelper::format_price( $attempted_discount, $cart );

		return array(
			array(
				'action'   => 'KEEP_CURRENT_COUPON',
				'label'    => sprintf( 'Keep %s (saves %s)', $code, $formatted_current ),
				'metadata' => array(
					'priority' => 'high',
					'savings'  => $formatted_current,
				),
			),
			array(
				'action'   => 'RETRY_REQUEST',
				'label'    => sprintf( 'Switch to %s (saves %s)', $attempted_coupon, $formatted_attempted ),
				'metadata' => array(
					'priority' => 'low',
					'savings'  => $formatted_attempted,
				),
			),
		);
	}
}
