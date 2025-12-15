<?php
/**
 * Coupon Validator for Agentic Commerce.
 *
 * Validates coupon codes using WooCommerce's WC_Discounts validation.
 * Uses a two-tier error mapping approach:
 * 1. Numeric error codes from WP_Error data (when available)
 * 2. Message pattern matching as fallback
 *
 * @package WooCommerce\PayPalCommerce\AgenticCommerce\CartValidation\CouponValidator
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\AgenticCommerce\CartValidation\CouponValidator;

use WC_Coupon;
use WC_Discounts;
use WP_Error;
use WooCommerce\PayPalCommerce\AgenticCommerce\CartValidation\ValidatorInterface;
use WooCommerce\PayPalCommerce\AgenticCommerce\Helper\CartHelper;
use WooCommerce\PayPalCommerce\AgenticCommerce\Schema\Coupon;
use WooCommerce\PayPalCommerce\AgenticCommerce\Schema\PayPalCart;
use WooCommerce\PayPalCommerce\AgenticCommerce\Validation\CouponInvalid;

/**
 * Validates coupons for Agentic Commerce using WooCommerce's native validation.
 */
class CouponValidator implements ValidatorInterface {

	/**
	 * Context builder for building validation context data.
	 *
	 * @var ContextBuilder
	 */
	private ContextBuilder $context_builder;

	/**
	 * Discount calculator for coupon discount amounts.
	 *
	 * @var DiscountCalculator
	 */
	private DiscountCalculator $discount_calculator;

	/**
	 * Resolution builder for building resolution options.
	 *
	 * @var ResolutionBuilder
	 */
	private ResolutionBuilder $resolution_builder;

	/**
	 * Error message patterns for mapping WC errors to PayPal issue types.
	 *
	 * WooCommerce's WC_Discounts always returns WP_Error with code 'invalid_coupon'.
	 * The actual error type is determined by the error message content.
	 * Patterns are checked in order - more specific patterns should come first.
	 */
	private const MESSAGE_PATTERNS = array(
		// Existence checks.
		'does not exist'                              => 'COUPON_NOT_EXIST',
		'cannot be applied because it does not exist' => 'COUPON_NOT_EXIST',

		// Expiration.
		'has expired'                                 => 'COUPON_EXPIRED',

		// Usage limits.
		'usage limit'                                 => 'USAGE_LIMIT_EXCEEDED',
		'reached its usage limit'                     => 'USAGE_LIMIT_EXCEEDED',

		// Spend requirements.
		'minimum spend'                               => 'MINIMUM_ORDER_NOT_MET',
		'maximum spend'                               => 'MAXIMUM_ORDER_EXCEEDED',

		// Product/category restrictions - check specific patterns first.
		'not applicable to the products:'             => 'COUPON_NOT_APPLICABLE',
		'not applicable to the categories:'           => 'COUPON_NOT_APPLICABLE',
		'not applicable to selected products'         => 'COUPON_NOT_APPLICABLE',
		'not valid for sale items'                    => 'COUPON_NOT_APPLICABLE',
		'not applicable'                              => 'COUPON_NOT_APPLICABLE',

		// Email restrictions.
		'not yours'                                   => 'COUPON_EMAIL_RESTRICTED',
		'does not belong'                             => 'COUPON_EMAIL_RESTRICTED',

		// Already applied.
		'already applied'                             => 'COUPON_ALREADY_APPLIED',
		'already been applied'                        => 'COUPON_ALREADY_APPLIED',
	);

	/**
	 * Issue configuration.
	 *
	 * Each issue type declares:
	 * - message: Internal message
	 * - user_message: Customer-facing message template (%s = coupon code)
	 * - resolutions: Array of resolution keys
	 * - context_builders: Array of context builder method names to call
	 */
	private const ISSUE_CONFIG = array(
		'COUPON_NOT_EXIST'            => array(
			'message'          => 'Coupon does not exist',
			'user_message'     => "The coupon code '%s' is not valid. Please check the code and try again.",
			'resolutions'      => array( 'remove' ),
			'context_builders' => array(),
		),
		'COUPON_EXPIRED'              => array(
			'message'          => 'Coupon has expired',
			'user_message'     => "The coupon code '%s' has expired.",
			'resolutions'      => array( 'remove' ),
			'context_builders' => array( 'expiration' ),
		),
		'USAGE_LIMIT_EXCEEDED'        => array(
			'message'          => 'Coupon usage limit reached',
			'user_message'     => "The coupon code '%s' has reached its usage limit.",
			'resolutions'      => array( 'remove' ),
			'context_builders' => array( 'usage_limits' ),
		),
		'MINIMUM_ORDER_NOT_MET'       => array(
			'message'          => 'Minimum order amount not met',
			'user_message'     => "The coupon '%s' requires a minimum order of %s. Your current order is %s.",
			'resolutions'      => array( 'add_items_to_minimum', 'continue_without' ),
			'context_builders' => array( 'minimum_spend', 'eligible_items' ),
		),
		'MAXIMUM_ORDER_EXCEEDED'      => array(
			'message'          => 'Maximum order amount exceeded',
			'user_message'     => "The coupon '%s' cannot be applied to orders above %s.",
			'resolutions'      => array( 'modify_cart', 'remove' ),
			'context_builders' => array( 'maximum_spend' ),
		),
		'COUPON_NOT_APPLICABLE'       => array(
			'message'          => 'Coupon not applicable to cart items',
			'user_message'     => "The coupon '%s' is not applicable to the items in your cart.",
			'resolutions'      => array( 'modify_cart', 'remove' ),
			'context_builders' => array( 'eligible_items' ),
		),
		'COUPON_STACKING_NOT_ALLOWED' => array(
			'message'          => 'Coupon cannot be combined with other coupons',
			'user_message'     => "The coupon '%s' cannot be combined with other coupons.",
			'resolutions'      => array(), // Built dynamically with savings comparison.
			'context_builders' => array( 'stacking' ),
		),
		'COUPON_ALREADY_APPLIED'      => array(
			'message'          => 'Coupon already applied',
			'user_message'     => "The coupon '%s' has already been applied to this cart.",
			'resolutions'      => array( 'remove' ),
			'context_builders' => array(),
		),
		'COUPON_EMAIL_RESTRICTED'     => array(
			'message'          => 'Coupon restricted to specific email addresses',
			'user_message'     => "The coupon '%s' is restricted to specific customers.",
			'resolutions'      => array( 'remove' ),
			'context_builders' => array( 'email_restriction' ),
		),
		'COUPON_INVALID'              => array(
			'message'          => 'Coupon is not valid',
			'user_message'     => "The coupon '%s' is not valid.",
			'resolutions'      => array( 'remove' ),
			'context_builders' => array(),
		),
		'COUPON_NOT_SUPPORTED'        => array(
			'message'          => 'Coupons are not enabled',
			'user_message'     => 'This store does not accept coupon codes at this time.',
			'resolutions'      => array( 'remove' ),
			'context_builders' => array(),
		),
	);

	/**
	 * Constructor.
	 *
	 * @param ContextBuilder     $context_builder Context builder instance.
	 * @param DiscountCalculator $discount_calculator Discount calculator instance.
	 * @param ResolutionBuilder  $resolution_builder Resolution builder instance.
	 */
	public function __construct(
		ContextBuilder $context_builder,
		DiscountCalculator $discount_calculator,
		ResolutionBuilder $resolution_builder
	) {
		$this->context_builder     = $context_builder;
		$this->discount_calculator = $discount_calculator;
		$this->resolution_builder  = $resolution_builder;
	}

	/**
	 * Validates coupons in the cart.
	 *
	 * @param PayPalCart $cart The cart to validate.
	 * @return CouponInvalid[]|null Array of validation issues or null if valid.
	 */
	public function validate( PayPalCart $cart ): ?array {
		$coupons_to_apply = $this->get_coupons_to_apply( $cart );

		if ( empty( $coupons_to_apply ) ) {
			return null;
		}

		if ( ! $this->is_wc_available() ) {
			return null;
		}

		if ( ! wc_coupons_enabled() ) {
			return array( $this->create_issue( 'COUPON_NOT_SUPPORTED', $coupons_to_apply[0]->code(), 'coupons', $cart, null ) );
		}

		// Check stacking first (multiple coupons with individual_use).
		$stacking_issue = $this->check_stacking_conflicts( $coupons_to_apply, $cart );
		if ( $stacking_issue ) {
			return array( $stacking_issue );
		}

		// Validate each coupon.
		$discounts = $this->discount_calculator->create_discounts_instance( $cart );
		$issues    = array();

		foreach ( $coupons_to_apply as $index => $coupon ) {
			$issue = $this->validate_single_coupon( $coupon, $cart, $index, $discounts );
			if ( $issue ) {
				$issues[] = $issue;
			}
		}

		return $issues ?: null;
	}

	/**
	 * Filters coupons with APPLY action.
	 *
	 * @param PayPalCart $cart The cart.
	 * @return Coupon[] Array of coupons to apply.
	 */
	private function get_coupons_to_apply( PayPalCart $cart ): array {
		$coupons = $cart->coupons();

		if ( ! $coupons || ! is_array( $coupons ) ) {
			return array();
		}

		return array_values(
			array_filter(
				$coupons,
				static fn( Coupon $c ): bool => $c->action() === 'APPLY'
			)
		);
	}

	/**
	 * Checks if WooCommerce coupon classes are available.
	 *
	 * @return bool True if WC classes are available.
	 */
	private function is_wc_available(): bool {
		return class_exists( 'WC_Coupon' ) && class_exists( 'WC_Discounts' );
	}

	/**
	 * Checks for stacking conflicts when multiple coupons are applied.
	 *
	 * WooCommerce default behavior: By default, multiple coupons CAN be stacked
	 * unless a coupon has the "Individual use only" checkbox enabled.
	 * When individual_use is true, that coupon cannot be combined with ANY other coupons.
	 *
	 * @param Coupon[]   $coupons The coupons to check.
	 * @param PayPalCart $cart The cart context.
	 * @return CouponInvalid|null Validation issue or null if no conflicts.
	 */
	private function check_stacking_conflicts( array $coupons, PayPalCart $cart ): ?CouponInvalid {
		if ( count( $coupons ) < 2 ) {
			return null;
		}

		// Load all valid WC coupons.
		$wc_coupons = array();
		foreach ( $coupons as $coupon ) {
			// Normalize coupon code to match WooCommerce's case-insensitive behavior.
			$normalized_code = wc_sanitize_coupon_code( $coupon->code() );

			$wc_coupon = new WC_Coupon( $normalized_code );

			if ( ! $wc_coupon->get_id() ) {
				continue;
			}

			$wc_coupons[] = array(
				'coupon'    => $coupon,
				'wc_coupon' => $wc_coupon,
			);
		}

		// Check if any coupon has individual_use enabled.
		foreach ( $wc_coupons as $index => $data ) {
			if ( $data['wc_coupon']->get_individual_use() ) {
				// Build list of OTHER coupon codes (exclude current one).
				$other_codes = array();
				foreach ( $wc_coupons as $other_index => $other_data ) {
					if ( $index !== $other_index ) {
						$other_codes[] = $other_data['coupon']->code();
					}
				}

				return $this->create_issue(
					'COUPON_STACKING_NOT_ALLOWED',
					$data['coupon']->code(),
					'coupons',
					$cart,
					$data['wc_coupon'],
					array( 'other_codes' => $other_codes )
				);
			}
		}

		return null;
	}

	/**
	 * Validates a single coupon using WC_Discounts.
	 *
	 * @param Coupon       $coupon The coupon to validate.
	 * @param PayPalCart   $cart The cart context.
	 * @param int          $index The coupon index.
	 * @param WC_Discounts $discounts The WC discounts instance.
	 * @return CouponInvalid|null Validation issue or null if valid.
	 */
	private function validate_single_coupon( Coupon $coupon, PayPalCart $cart, int $index, WC_Discounts $discounts ): ?CouponInvalid {
		$code  = $coupon->code();
		$field = $index > 0 ? "coupons[$index]" : 'coupons';

		// Normalize coupon code to match WooCommerce's case-insensitive behavior.
		$normalized_code = wc_sanitize_coupon_code( $code );

		$wc_coupon = new WC_Coupon( $normalized_code );

		if ( ! $wc_coupon->get_id() ) {
			return $this->create_issue( 'COUPON_NOT_EXIST', $code, $field, $cart, null );
		}

		// Run WC validation via WC_Discounts.
		$result = $discounts->is_coupon_valid( $wc_coupon );

		if ( is_wp_error( $result ) ) {
			$issue_type = $this->map_wc_error_to_issue_type( $result );
			return $this->create_issue( $issue_type, $code, $field, $cart, $wc_coupon );
		}

		return null;
	}

	/**
	 * Maps WC_Error to issue type using message pattern matching.
	 *
	 * WooCommerce's WC_Discounts returns errors with code 'invalid_coupon',
	 * so we determine the specific issue type by analyzing the error message.
	 *
	 * @param WP_Error $error The WP_Error from WC validation.
	 * @return string The mapped issue type.
	 */
	private function map_wc_error_to_issue_type( WP_Error $error ): string {
		$error_code    = $error->get_error_code();
		$error_message = strtolower( $error->get_error_message() );

		// Parse the error message to determine the specific issue type.
		if ( $error_code === 'invalid_coupon' ) {
			foreach ( self::MESSAGE_PATTERNS as $pattern => $issue_type ) {
				if ( strpos( $error_message, $pattern ) !== false ) {
					return $issue_type;
				}
			}
		}

		// Fallback for unrecognized errors.
		return 'COUPON_INVALID';
	}

	/**
	 * Creates a CouponInvalid issue - the single point of issue creation.
	 *
	 * @param string         $issue_type The issue type.
	 * @param string         $code The coupon code.
	 * @param string         $field The field identifier.
	 * @param PayPalCart     $cart The cart context.
	 * @param WC_Coupon|null $wc_coupon The WC coupon object.
	 * @param array          $extra_context Additional context data.
	 * @return CouponInvalid The validation issue.
	 */
	private function create_issue(
		string $issue_type,
		string $code,
		string $field,
		PayPalCart $cart,
		?WC_Coupon $wc_coupon,
		array $extra_context = array()
	): CouponInvalid {
		$config = self::ISSUE_CONFIG[ $issue_type ] ?? self::ISSUE_CONFIG['COUPON_INVALID'];

		$context = $this->context_builder->build(
			$issue_type,
			$code,
			$cart,
			$wc_coupon,
			$config['context_builders'],
			$extra_context
		);

		// Build user message with context interpolation.
		$user_message = $this->build_user_message( $config['user_message'], $code, $context, $cart );

		$resolutions = $this->resolution_builder->build(
			$issue_type,
			$config['resolutions'],
			$code,
			$context,
			$cart,
			$wc_coupon
		);

		$resolutions  = $this->apply_resolutions_filter( $resolutions, $issue_type, $code, $wc_coupon, $cart, $context );
		$user_message = $this->apply_user_message_filter( $user_message, $issue_type, $code, $wc_coupon, $cart, $context );

		return new CouponInvalid( $config['message'], $user_message, $field, $context, $resolutions );
	}

	/**
	 * Builds user message with context interpolation.
	 *
	 * @param string     $template The message template.
	 * @param string     $code The coupon code.
	 * @param array      $context The context data.
	 * @param PayPalCart $cart The cart context.
	 * @return string The formatted message.
	 */
	private function build_user_message( string $template, string $code, array $context, PayPalCart $cart ): string {
		$placeholder_count = substr_count( $template, '%s' );

		if ( $placeholder_count === 1 ) {
			return sprintf( $template, $code );
		}

		if ( $placeholder_count === 2 ) {
			$second = isset( $context['minimum_required'] )
				? CartHelper::format_price( $context['minimum_required'], $cart )
				: ( isset( $context['maximum_allowed'] ) ? CartHelper::format_price( $context['maximum_allowed'], $cart ) : '' );

			return sprintf( $template, $code, $second );
		}

		if ( $placeholder_count === 3 ) {
			return sprintf(
				$template,
				$code,
				CartHelper::format_price( $context['minimum_required'] ?? '0.00', $cart ),
				CartHelper::format_price( $context['current_subtotal'] ?? '0.00', $cart )
			);
		}

		return sprintf( $template, $code );
	}


	/**
	 * Applies resolutions enrichment filter.
	 *
	 * @param array          $resolutions The resolution options.
	 * @param string         $issue_type The issue type.
	 * @param string         $code The coupon code.
	 * @param WC_Coupon|null $wc_coupon The WC coupon object.
	 * @param PayPalCart     $cart The cart context.
	 * @param array          $context The context data.
	 * @return array The filtered resolutions.
	 */
	private function apply_resolutions_filter( array $resolutions, string $issue_type, string $code, ?WC_Coupon $wc_coupon, PayPalCart $cart, array $context ): array {
		/**
		 * Filters the resolution options for a coupon issue.
		 *
		 * Allows coupon plugins to add or modify resolution options for the
		 * AI agent.
		 *
		 * @param array $resolutions The resolution options array.
		 * @param string $issue_type The issue type (e.g., 'COUPON_EXPIRED').
		 * @param string $code The coupon code.
		 * @param WC_Coupon|null $wc_coupon The WC_Coupon object (null if doesn't exist).
		 * @param PayPalCart $cart The cart context.
		 * @param array $context The validation context data.
		 *
		 * @return array Modified resolution options array.
		 * @since 1.0.0
		 */
		return apply_filters(
			'woocommerce_paypal_payments_agentic_commerce_coupon_validation_resolutions',
			$resolutions,
			$issue_type,
			$code,
			$wc_coupon,
			$cart,
			$context
		);
	}

	/**
	 * Applies user message filter.
	 *
	 * @param string         $message The user message.
	 * @param string         $issue_type The issue type.
	 * @param string         $code The coupon code.
	 * @param WC_Coupon|null $wc_coupon The WC coupon object.
	 * @param PayPalCart     $cart The cart context.
	 * @param array          $context The context data.
	 * @return string The filtered message.
	 */
	private function apply_user_message_filter( string $message, string $issue_type, string $code, ?WC_Coupon $wc_coupon, PayPalCart $cart, array $context ): string {
		/**
		 * Filters the user-facing message for a coupon issue.
		 *
		 * Allows coupon plugins to customize the user message for the AI agent.
		 *
		 * @param string $message The user message.
		 * @param string $issue_type The issue type (e.g., 'COUPON_EXPIRED').
		 * @param string $code The coupon code.
		 * @param WC_Coupon|null $wc_coupon The WC_Coupon object (null if doesn't exist).
		 * @param PayPalCart $cart The cart context.
		 * @param array $context The validation context data.
		 *
		 * @return string Modified user message.
		 * @since 1.0.0
		 */
		return apply_filters(
			'woocommerce_paypal_payments_agentic_commerce_coupon_validation_user_message',
			$message,
			$issue_type,
			$code,
			$wc_coupon,
			$cart,
			$context
		);
	}
}
