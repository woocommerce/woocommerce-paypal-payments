<?php
/**
 * Main cart validation orchestrator.
 *
 * @package WooCommerce\PayPalCommerce\AgenticCommerce\CartValidation
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\CartValidation;

use WooCommerce\PayPalCommerce\AgenticCommerce\Schema\PayPalCart;

class CartValidationProcessor {

	/**
	 * @var ValidatorInterface[]
	 */
	private array $validators = array();

	private bool $did_register_validators = false;

	/**
	 * @return PayPalCart Cart with accumulated validation issues.
	 */
	public function validate_cart( PayPalCart $cart ): PayPalCart {
		$validators   = $this->get_validators();
		$current_cart = $cart;

		foreach ( $validators as $validator ) {
			$issues = $validator->validate( $current_cart );

			if ( ! empty( $issues ) ) {
				$current_cart = $current_cart->with_validation_issues( ...$issues );
			}
		}

		return $current_cart;
	}

	/**
	 * @return ValidatorInterface[] List of cart validators.
	 */
	private function get_validators(): array {
		if ( ! $this->did_register_validators ) {
			$this->did_register_validators = true;
			/**
			 * Fires before cart validation starts, allows third party code to register cart validators.
			 *
			 * @param CartValidationProcessor $processor The cart validation processor; exposes `register_validator()`.
			 */
			do_action( 'woocommerce_paypal_payments_agentic_commerce_validators', $this );
		}

		return $this->validators;
	}

	public function register_validator( ValidatorInterface $validator ): void {
		$this->validators[ get_class( $validator ) ] = $validator;
	}
}
