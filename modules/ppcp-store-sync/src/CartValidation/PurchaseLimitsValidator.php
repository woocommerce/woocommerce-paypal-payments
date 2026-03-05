<?php
/**
 * Purchase Limits Validator for Agentic Commerce.
 *
 * Validates product purchase quantity limits including WooCommerce's native
 * "Sold individually" setting and third-party plugin limits via filter.
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\CartValidation
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\CartValidation;

use WooCommerce\PayPalCommerce\StoreSync\Enums\BusinessRuleIssue;
use WooCommerce\PayPalCommerce\StoreSync\Enums\Priority;
use WooCommerce\PayPalCommerce\StoreSync\Helper\ProductManager;
use WooCommerce\PayPalCommerce\StoreSync\Schema\PayPalCart;
use WooCommerce\PayPalCommerce\StoreSync\Schema\CartItem;
use WooCommerce\PayPalCommerce\StoreSync\Schema\ResolutionOption;
use WooCommerce\PayPalCommerce\StoreSync\Validation\BusinessRuleViolation;
use WooCommerce\PayPalCommerce\StoreSync\Validation\ValidationIssue;
use WC_Product;

/**
 * Validates purchase quantity limits for cart items.
 *
 * Checks:
 * 1. WooCommerce's native "Sold individually" setting
 * 2. Third-party plugin limits via filter (min, max, step, per-customer)
 */
class PurchaseLimitsValidator implements ValidatorInterface {

	/**
	 * Product manager for resolving cart items.
	 *
	 * @var ProductManager
	 */
	private ProductManager $product_manager;

	/**
	 * Constructor.
	 *
	 * @param ProductManager $product_manager Product manager instance.
	 */
	public function __construct( ProductManager $product_manager ) {
		$this->product_manager = $product_manager;
	}

	/**
	 * Validates cart against purchase limit rules.
	 *
	 * @param PayPalCart $cart The cart to validate.
	 * @return ValidationIssue[]|null Array of issues or null if valid.
	 */
	public function validate( PayPalCart $cart ): ?array {
		$issues = array();

		foreach ( $cart->items() as $key => $item ) {
			$item_issues = $this->validate_item( $key, $item, $cart );

			if ( $item_issues ) {
				$issues = array_merge( $issues, $item_issues );
			}
		}

		return $issues ?: null;
	}

	/**
	 * Validates a single cart item against purchase limits.
	 *
	 * @param int        $key  The item index in the cart.
	 * @param CartItem   $item The cart item.
	 * @param PayPalCart $cart The full cart context.
	 * @return ValidationIssue[]|null Array of issues or null if valid.
	 */
	private function validate_item( int $key, CartItem $item, PayPalCart $cart ): ?array {
		$product = $this->product_manager->find_product( $item );

		if ( ! $product ) {
			return null;
		}

		$issues   = array();
		$field    = "items[{$key}]";
		$quantity = $item->quantity();

		// Check WooCommerce's native "Sold individually" setting.
		$sold_individually_issue = $this->check_sold_individually( $product, $quantity, $field );
		if ( $sold_individually_issue ) {
			$issues[] = $sold_individually_issue;
		}

		// Check third-party plugin limits via filter.
		$plugin_limits = $this->get_plugin_limits( $product, $cart );
		if ( $plugin_limits ) {
			$plugin_issues = $this->check_plugin_limits( $plugin_limits, $product, $quantity, $field );
			$issues        = array_merge( $issues, $plugin_issues );
		}

		return $issues ?: null;
	}

	/**
	 * Checks WooCommerce's native "Sold individually" setting.
	 *
	 * @param WC_Product $product  The product.
	 * @param int        $quantity The requested quantity.
	 * @param string     $field    The field identifier.
	 * @return ValidationIssue|null Issue if limit exceeded, null otherwise.
	 */
	private function check_sold_individually( WC_Product $product, int $quantity, string $field ): ?ValidationIssue {
		if ( ! $product->is_sold_individually() ) {
			return null;
		}

		if ( $quantity <= 1 ) {
			return null;
		}

		return $this->create_max_quantity_issue(
			$product,
			$quantity,
			1,
			$field,
			'This product can only be purchased one at a time.'
		);
	}

	/**
	 * Gets purchase limits from third-party plugins via filter.
	 *
	 * @param WC_Product $product The product.
	 * @param PayPalCart $cart    The cart context.
	 * @return array|null Limits array or null if no limits defined.
	 */
	private function get_plugin_limits( WC_Product $product, PayPalCart $cart ): ?array {
		/**
		 * Filters product purchase limits for Agentic Commerce.
		 *
		 * Allows third-party plugins to define custom purchase limits including
		 * minimum quantities, maximum quantities, quantity steps, and per-customer
		 * lifetime limits.
		 *
		 * @param array|null $limits  The limits array or null if no limits.
		 *                            Expected keys:
		 *                            - 'min_quantity' (int): Minimum quantity required.
		 *                            - 'max_quantity' (int): Maximum quantity allowed per order.
		 *                            - 'quantity_step' (int): Must buy in multiples of this number.
		 *                            - 'per_customer_limit' (int): Lifetime limit per customer.
		 *                            - 'customer_purchased' (int): How many the customer already bought.
		 * @param WC_Product $product The WooCommerce product.
		 * @param PayPalCart $cart    The cart context (includes customer info if available).
		 *
		 * @return array|null
		 *
		 * @since 1.0.0
		 *
		 * @example
		 * add_filter(
		 *     'woocommerce_paypal_payments_agentic_commerce_product_purchase_limits',
		 *     function( $limits, $product, $cart ) {
		 *         // Example: Limit product ID 123 to max 5 per order.
		 *         if ( $product->get_id() === 123 ) {
		 *             return array(
		 *                 'max_quantity' => 5,
		 *             );
		 *         }
		 *         return $limits;
		 *     },
		 *     10,
		 *     3
		 * );
		 */
		$limits = apply_filters(
			'woocommerce_paypal_payments_agentic_commerce_product_purchase_limits',
			null,
			$product,
			$cart
		);

		if ( ! is_array( $limits ) || empty( $limits ) ) {
			return null;
		}

		return $limits;
	}

	/**
	 * Checks plugin-defined purchase limits.
	 *
	 * @param array      $limits   The limits from the filter.
	 * @param WC_Product $product  The product.
	 * @param int        $quantity The requested quantity.
	 * @param string     $field    The field identifier.
	 * @return ValidationIssue[] Array of issues found.
	 */
	private function check_plugin_limits( array $limits, WC_Product $product, int $quantity, string $field ): array {
		$issues = array();

		// Check minimum quantity.
		if ( isset( $limits['min_quantity'] ) && is_numeric( $limits['min_quantity'] ) ) {
			$min_quantity = (int) $limits['min_quantity'];
			if ( $quantity < $min_quantity ) {
				$issues[] = $this->create_min_quantity_issue( $product, $quantity, $min_quantity, $field );
			}
		}

		// Check maximum quantity.
		if ( isset( $limits['max_quantity'] ) && is_numeric( $limits['max_quantity'] ) ) {
			$max_quantity = (int) $limits['max_quantity'];
			if ( $quantity > $max_quantity ) {
				$issues[] = $this->create_max_quantity_issue(
					$product,
					$quantity,
					$max_quantity,
					$field,
					sprintf( 'Maximum %d allowed per order.', $max_quantity )
				);
			}
		}

		// Check quantity step (must buy in multiples).
		if ( isset( $limits['quantity_step'] ) && is_numeric( $limits['quantity_step'] ) ) {
			$step = (int) $limits['quantity_step'];
			if ( $step > 1 && ( $quantity % $step ) !== 0 ) {
				$issues[] = $this->create_step_issue( $product, $quantity, $step, $field );
			}
		}

		// Check per-customer lifetime limit.
		if ( isset( $limits['per_customer_limit'] ) && is_numeric( $limits['per_customer_limit'] ) ) {
			$per_customer_limit  = (int) $limits['per_customer_limit'];
			$customer_purchased  = isset( $limits['customer_purchased'] ) ? (int) $limits['customer_purchased'] : 0;
			$remaining_allowance = $per_customer_limit - $customer_purchased;

			if ( $quantity > $remaining_allowance ) {
				$issues[] = $this->create_per_customer_limit_issue(
					$product,
					$quantity,
					$per_customer_limit,
					$customer_purchased,
					$field
				);
			}
		}

		return $issues;
	}

	/**
	 * Creates a minimum quantity not met issue.
	 *
	 * @param WC_Product $product      The product.
	 * @param int        $quantity     The requested quantity.
	 * @param int        $min_quantity The minimum required.
	 * @param string     $field        The field identifier.
	 * @return ValidationIssue The validation issue.
	 */
	private function create_min_quantity_issue(
		WC_Product $product,
		int $quantity,
		int $min_quantity,
		string $field
	): ValidationIssue {
		$product_name = $product->get_name();

		return new BusinessRuleViolation(
			sprintf( 'Minimum quantity not met for %s', $product_name ),
			sprintf(
				'%s requires a minimum quantity of %d. You requested %d.',
				$product_name,
				$min_quantity,
				$quantity
			),
			$field,
			'',
			array(
				'specific_issue'     => BusinessRuleIssue::MINIMUM_QUANTITY_NOT_MET,
				'product_id'         => $product->get_id(),
				'product_name'       => $product_name,
				'requested_quantity' => $quantity,
				'minimum_quantity'   => $min_quantity,
			),
			array(
				ResolutionOption::modify_cart(
					sprintf( 'Increase quantity to %d', $min_quantity ),
					array(
						'priority'     => Priority::HIGH,
						'min_quantity' => $min_quantity,
					)
				),
				ResolutionOption::remove_item( Priority::LOW ),
			)
		);
	}

	/**
	 * Creates a maximum quantity exceeded issue.
	 *
	 * @param WC_Product $product      The product.
	 * @param int        $quantity     The requested quantity.
	 * @param int        $max_quantity The maximum allowed.
	 * @param string     $field        The field identifier.
	 * @param string     $reason       Additional context for the limit.
	 * @return ValidationIssue The validation issue.
	 */
	private function create_max_quantity_issue(
		WC_Product $product,
		int $quantity,
		int $max_quantity,
		string $field,
		string $reason = ''
	): ValidationIssue {
		$product_name = $product->get_name();
		$user_message = sprintf(
			'%s is limited to %d per order. You requested %d.',
			$product_name,
			$max_quantity,
			$quantity
		);

		if ( $reason ) {
			$user_message .= ' ' . $reason;
		}

		return new BusinessRuleViolation(
			sprintf( 'Maximum quantity exceeded for %s', $product_name ),
			$user_message,
			$field,
			'',
			array(
				'specific_issue'     => BusinessRuleIssue::MAXIMUM_QUANTITY_EXCEEDED,
				'product_id'         => $product->get_id(),
				'product_name'       => $product_name,
				'requested_quantity' => $quantity,
				'maximum_quantity'   => $max_quantity,
			),
			array(
				ResolutionOption::modify_cart(
					sprintf( 'Reduce quantity to %d', $max_quantity ),
					array(
						'priority'     => Priority::HIGH,
						'max_quantity' => $max_quantity,
					)
				),
				ResolutionOption::remove_item( Priority::LOW ),
			)
		);
	}

	/**
	 * Creates a quantity step issue (must buy in multiples).
	 *
	 * @param WC_Product $product  The product.
	 * @param int        $quantity The requested quantity.
	 * @param int        $step     The required step/multiple.
	 * @param string     $field    The field identifier.
	 * @return ValidationIssue The validation issue.
	 */
	private function create_step_issue(
		WC_Product $product,
		int $quantity,
		int $step,
		string $field
	): ValidationIssue {
		$product_name     = $product->get_name();
		$nearest_valid    = (int) ( ceil( $quantity / $step ) * $step );
		$nearest_valid_lo = (int) ( floor( $quantity / $step ) * $step );

		// Pick the closest valid quantity.
		if ( $nearest_valid_lo > 0 && ( $quantity - $nearest_valid_lo ) < ( $nearest_valid - $quantity ) ) {
			$suggested = $nearest_valid_lo;
		} else {
			$suggested = $nearest_valid;
		}

		return new BusinessRuleViolation(
			sprintf( 'Quantity must be in multiples of %d for %s', $step, $product_name ),
			sprintf(
				'%s must be purchased in multiples of %d. You requested %d.',
				$product_name,
				$step,
				$quantity
			),
			$field,
			'',
			array(
				'specific_issue'     => BusinessRuleIssue::MINIMUM_QUANTITY_NOT_MET,
				'product_id'         => $product->get_id(),
				'product_name'       => $product_name,
				'requested_quantity' => $quantity,
				'quantity_step'      => $step,
				'suggested_quantity' => $suggested,
			),
			array(
				ResolutionOption::modify_cart(
					sprintf( 'Change quantity to %d', $suggested ),
					array(
						'priority'           => Priority::HIGH,
						'suggested_quantity' => $suggested,
						'quantity_step'      => $step,
					)
				),
				ResolutionOption::remove_item( Priority::LOW ),
			)
		);
	}

	/**
	 * Creates a per-customer lifetime limit exceeded issue.
	 *
	 * @param WC_Product $product            The product.
	 * @param int        $quantity           The requested quantity.
	 * @param int        $per_customer_limit The lifetime limit per customer.
	 * @param int        $customer_purchased How many the customer already purchased.
	 * @param string     $field              The field identifier.
	 * @return ValidationIssue The validation issue.
	 */
	private function create_per_customer_limit_issue(
		WC_Product $product,
		int $quantity,
		int $per_customer_limit,
		int $customer_purchased,
		string $field
	): ValidationIssue {
		$product_name = $product->get_name();
		$remaining    = max( 0, $per_customer_limit - $customer_purchased );

		$user_message = sprintf(
			'%s is limited to %d per customer.',
			$product_name,
			$per_customer_limit
		);

		if ( $customer_purchased > 0 ) {
			$user_message .= sprintf( ' You have already purchased %d.', $customer_purchased );
		}

		if ( $remaining > 0 ) {
			$user_message .= sprintf( ' You can purchase up to %d more.', $remaining );
		} else {
			$user_message .= ' You have reached your purchase limit for this product.';
		}

		$resolutions = array();
		if ( $remaining > 0 ) {
			$resolutions[] = ResolutionOption::modify_cart(
				sprintf( 'Reduce quantity to %d', $remaining ),
				array(
					'priority'     => Priority::HIGH,
					'max_quantity' => $remaining,
				)
			);
		}
		$resolutions[] = ResolutionOption::remove_item( $remaining > 0 ? Priority::LOW : Priority::HIGH );

		return new BusinessRuleViolation(
			sprintf( 'Per-customer purchase limit exceeded for %s', $product_name ),
			$user_message,
			$field,
			'',
			array(
				'specific_issue'      => BusinessRuleIssue::PURCHASE_LIMIT_EXCEEDED,
				'product_id'          => $product->get_id(),
				'product_name'        => $product_name,
				'requested_quantity'  => $quantity,
				'per_customer_limit'  => $per_customer_limit,
				'customer_purchased'  => $customer_purchased,
				'remaining_allowance' => $remaining,
			),
			$resolutions
		);
	}
}
