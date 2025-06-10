<?php
/**
 * PayPal Commerce eligibility service for WooCommerce.
 *
 * This file contains the FeaturesEligibilityService class which manages eligibility checks
 * for various PayPal Commerce features including saving PayPal and Venmo, advanced credit and
 * debit cards, alternative payment methods, Google Pay, Apple Pay, and Pay Later.
 *
 * @package WooCommerce\PayPalCommerce\Settings\Service
 */

declare( strict_types = 1 );

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
	private bool $is_save_paypal_eligible;

	/**
	 * Whether advanced credit and debit cards are eligible.
	 *
	 * @var callable
	 */
	private $check_acdc_eligible;

	/**
	 * Whether alternative payment methods are eligible.
	 *
	 * @var bool
	 */
	private bool $is_apm_eligible;

	/**
	 * Whether Google Pay is eligible.
	 *
	 * @var callable
	 */
	private $check_google_pay_eligible;

	/**
	 * Whether Apple Pay is eligible.
	 *
	 * @var callable
	 */
	private $check_apple_pay_eligible;

	/**
	 * Whether Pay Later is eligible.
	 *
	 * @var bool
	 */
	private bool $is_pay_later_eligible;

	/**
	 * Whether Installments is eligible.
	 *
	 * @var bool
	 */
	private bool $is_installments_eligible;

	/**
	 * Constructor.
	 *
	 * @param bool     $is_save_paypal_eligible   If saving PayPal and Venmo is eligible.
	 * @param callable $check_acdc_eligible       If advanced credit and debit cards are eligible.
	 * @param bool     $is_apm_eligible           If alternative payment methods are eligible.
	 * @param callable $check_google_pay_eligible If Google Pay is eligible.
	 * @param callable $check_apple_pay_eligible  If Apple Pay is eligible.
	 * @param bool     $is_pay_later_eligible     If Pay Later is eligible.
	 * @param bool     $is_installments_eligible   If Installments is eligible.
	 */
	public function __construct(
		bool $is_save_paypal_eligible,
		callable $check_acdc_eligible,
		bool $is_apm_eligible,
		callable $check_google_pay_eligible,
		callable $check_apple_pay_eligible,
		bool $is_pay_later_eligible,
		bool $is_installments_eligible
	) {
		$this->is_save_paypal_eligible   = $is_save_paypal_eligible;
		$this->check_acdc_eligible       = $check_acdc_eligible;
		$this->is_apm_eligible           = $is_apm_eligible;
		$this->check_google_pay_eligible = $check_google_pay_eligible;
		$this->check_apple_pay_eligible  = $check_apple_pay_eligible;
		$this->is_pay_later_eligible     = $is_pay_later_eligible;
		$this->is_installments_eligible  = $is_installments_eligible;
	}

	/**
	 * Returns all eligibility checks as callables.
	 *
	 * @return array<string, callable>
	 */
	public function get_eligibility_checks() : array {
		return array(
			'save_paypal_and_venmo'           => fn() => $this->is_save_paypal_eligible,
			'advanced_credit_and_debit_cards' => $this->check_acdc_eligible,
			'alternative_payment_methods'     => fn() => $this->is_apm_eligible,
			'google_pay'                      => $this->check_google_pay_eligible,
			'apple_pay'                       => $this->check_apple_pay_eligible,
			'pay_later'                       => fn() => $this->is_pay_later_eligible,
			'installments'                    => fn() => $this->is_installments_eligible,
		);
	}
}
