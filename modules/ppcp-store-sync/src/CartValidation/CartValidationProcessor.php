<?php
/**
 * Main cart validation orchestrator.
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\CartValidation
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\CartValidation;

use Throwable;
use Psr\Log\LoggerInterface;

use WooCommerce\PayPalCommerce\StoreSync\Schema\PayPalCart;
use WooCommerce\PayPalCommerce\StoreSync\Validation\StoreValidation;
use WooCommerce\PayPalCommerce\StoreSync\Validation\ValidationIssue;

class CartValidationProcessor {

	private LoggerInterface $logger;

	/**
	 * @var ValidatorInterface[]
	 */
	private array $validators = array();

	private bool $did_register_validators = false;

	public function __construct( LoggerInterface $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Runs all registered validators and adds any found issues to $validation.
	 */
	public function validate_cart( PayPalCart $cart, StoreValidation $validation ): void {
		$validators = $this->get_validators();

		foreach ( $validators as $validator ) {
			try {
				$issues = $validator->validate( $cart, $validation );
			} catch ( Throwable $error ) {
				/*
				 * Internal validators do not throw anything.
				 * This protects us against third party validators blocking the REST endpoint by
				 * throwing an unexpected exception.
				 */

				$this->logger->error(
					sprintf(
						'[VALIDATE] Unexpected cart validation error by "%s": %s',
						get_class( $validator ),
						$error->getMessage()
					)
				);

				continue;
			}

			if ( empty( $issues ) ) {
				continue;
			}

			if ( ! is_array( $issues ) ) {
				$issues = array( $issues );
			}

			$issues = array_filter( $issues, static fn( $issue ) => $issue instanceof ValidationIssue );

			foreach ( $issues as $issue ) {
				$validation->add( $issue );
			}
		}
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
			do_action( 'woocommerce_paypal_payments_store_sync_validators', $this );
		}

		return $this->validators;
	}

	public function register_validator( ValidatorInterface $validator ): void {
		$this->validators[ get_class( $validator ) ] = $validator;
	}
}
