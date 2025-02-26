<?php
/**
 * PayPal Commerce eligibility service for WooCommerce.
 *
 * This file contains the FeaturesEligibilityService class which manages eligibility checks
 * for various PayPal Commerce features including saving PayPal and Venmo, advanced credit and debit cards,
 * alternative payment methods, Google Pay, Apple Pay, and Pay Later.
 *
 * @package WooCommerce\PayPalCommerce\Settings\Service
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Settings\Service;

/**
 * Manages eligibility checks for various PayPal Commerce features.
 */
class FeaturesEligibilityService {
	/**
	 * Whether saving PayPal and Venmo is eligible.
	 *
	 * @var bool
	 */
	private bool $is_save_paypal_and_venmo_eligible;

	/**
	 * Whether advanced credit and debit cards are eligible.
	 *
	 * @var bool
	 */
	private bool $is_advanced_credit_and_debit_cards_eligible;

	/**
	 * Whether alternative payment methods are eligible.
	 *
	 * @var bool
	 */
	private bool $is_alternative_payment_methods_eligible;

	/**
	 * Whether Google Pay is eligible.
	 *
	 * @var bool
	 */
	private bool $is_google_pay_eligible;

	/**
	 * Whether Apple Pay is eligible.
	 *
	 * @var bool
	 */
	private bool $is_apple_pay_eligible;

	/**
	 * Whether Pay Later is eligible.
	 *
	 * @var bool
	 */
	private bool $is_pay_later_eligible;

	/**
	 * Constructor.
	 *
	 * @param bool $is_save_paypal_and_venmo_eligible            Whether saving PayPal and Venmo is eligible.
	 * @param bool $is_advanced_credit_and_debit_cards_eligible  Whether advanced credit and debit cards are eligible.
	 * @param bool $is_alternative_payment_methods_eligible      Whether alternative payment methods are eligible.
	 * @param bool $is_google_pay_eligible                       Whether Google Pay is eligible.
	 * @param bool $is_apple_pay_eligible                        Whether Apple Pay is eligible.
	 * @param bool $is_pay_later_eligible                        Whether Pay Later is eligible.
	 */
	public function __construct(
		bool $is_save_paypal_and_venmo_eligible,
		bool $is_advanced_credit_and_debit_cards_eligible,
		bool $is_alternative_payment_methods_eligible,
		bool $is_google_pay_eligible,
		bool $is_apple_pay_eligible,
		bool $is_pay_later_eligible
	) {
		$this->is_save_paypal_and_venmo_eligible           = $is_save_paypal_and_venmo_eligible;
		$this->is_advanced_credit_and_debit_cards_eligible = $is_advanced_credit_and_debit_cards_eligible;
		$this->is_alternative_payment_methods_eligible     = $is_alternative_payment_methods_eligible;
		$this->is_google_pay_eligible                      = $is_google_pay_eligible;
		$this->is_apple_pay_eligible                       = $is_apple_pay_eligible;
		$this->is_pay_later_eligible                       = $is_pay_later_eligible;
	}

	/**
	 * Returns all eligibility checks as callables.
	 *
	 * @return array<string, callable>
	 */
	public function get_eligibility_checks(): array {
		return array(
			'save_paypal_and_venmo'           => fn() => $this->is_save_paypal_and_venmo_eligible,
			'advanced_credit_and_debit_cards' => fn() => $this->is_advanced_credit_and_debit_cards_eligible,
			'alternative_payment_methods'     => fn() => $this->is_alternative_payment_methods_eligible,
			'google_pay'                      => fn() => $this->is_google_pay_eligible,
			'apple_pay'                       => fn() => $this->is_apple_pay_eligible,
			'pay_later'                       => fn() => $this->is_pay_later_eligible,
		);
	}
}
