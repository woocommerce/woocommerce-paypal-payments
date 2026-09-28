<?php

/**
 * Eligibility service for Todos.
 *
 * This file contains the TodosEligibilityService class which manages eligibility checks
 * for various features including Fastlane, card payments, Pay Later messaging,
 * subscriptions, Apple Pay, Google Pay, and other digital wallet features.
 *
 * @package WooCommerce\PayPalCommerce\Settings\Service
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Settings\Service;

/**
 * Manages eligibility checks for various PayPal Commerce features.
 */
class TodosEligibilityService
{
    /**
     * Whether Fastlane is eligible.
     *
     * @var bool
     */
    private bool $is_fastlane_eligible;
    /**
     * Whether Pay Later messaging is eligible.
     *
     * @var bool
     */
    private bool $is_pay_later_messaging_eligible;
    /**
     * Whether Pay Later messaging for product page is eligible.
     *
     * @var bool
     */
    private bool $is_pay_later_messaging_product_eligible;
    /**
     * Whether Pay Later messaging for cart is eligible.
     *
     * @var bool
     */
    private bool $is_pay_later_messaging_cart_eligible;
    /**
     * Whether Pay Later messaging for checkout is eligible.
     *
     * @var bool
     */
    private bool $is_pay_later_messaging_checkout_eligible;
    /**
     * Whether subscriptions are eligible.
     *
     * @var bool
     */
    private bool $is_subscription_eligible;
    /**
     * Whether PayPal buttons for cart are eligible.
     *
     * @var bool
     */
    private bool $is_paypal_buttons_cart_eligible;
    /**
     * Whether PayPal buttons for block checkout are eligible.
     *
     * @var bool
     */
    private bool $is_paypal_buttons_block_checkout_eligible;
    /**
     * Whether PayPal buttons for product page are eligible.
     *
     * @var bool
     */
    private bool $is_paypal_buttons_product_eligible;
    /**
     * Whether Apple Pay domain registration is eligible.
     *
     * @var bool
     */
    private bool $is_apple_pay_domain_eligible;
    /**
     * Whether digital wallet features are eligible.
     *
     * @var bool
     */
    private bool $is_digital_wallet_eligible;
    /**
     * Whether Apple Pay is eligible.
     *
     * @var bool
     */
    private bool $is_apple_pay_eligible;
    /**
     * Whether Google Pay is eligible.
     *
     * @var bool
     */
    private bool $is_google_pay_eligible;
    /**
     * Whether enabling Apple Pay is eligible.
     *
     * @var bool
     */
    private bool $is_enable_apple_pay_eligible;
    /**
     * Whether enabling Google Pay is eligible.
     *
     * @var bool
     */
    private bool $is_enable_google_pay_eligible;
    /**
     * Whether enabling Installments is eligible.
     *
     * @var bool
     */
    private bool $is_enable_installments_eligible;
    /**
     * Whether enabling Working Capital is eligible.
     *
     * @var bool
     */
    private bool $is_working_capital_eligible;
    /**
     * Whether enabling Pay with Crypto is eligible.
     *
     * @var bool
     */
    private bool $is_enable_pwc_eligible;
    /**
     * Whether applying for Pay with Crypto is eligible.
     *
     * @var bool
     */
    private bool $is_apply_for_pwc;
    /**
     * Whether enabling reCAPTCHA protection is eligible.
     *
     * @var bool
     */
    private bool $is_recaptcha_protection_eligible;
    /**
     * Constructor.
     *
     * @param bool $is_fastlane_eligible                Whether Fastlane is eligible.
     * @param bool $is_pay_later_messaging_eligible     Whether Pay Later messaging is eligible.
     * @param bool $is_pay_later_messaging_product_eligible Whether Pay Later messaging for product page is eligible.
     * @param bool $is_pay_later_messaging_cart_eligible Whether Pay Later messaging for cart is eligible.
     * @param bool $is_pay_later_messaging_checkout_eligible Whether Pay Later messaging for checkout is eligible.
     * @param bool $is_subscription_eligible            Whether subscriptions are eligible.
     * @param bool $is_paypal_buttons_cart_eligible     Whether PayPal buttons for cart are eligible.
     * @param bool $is_paypal_buttons_block_checkout_eligible Whether PayPal buttons for block checkout are eligible.
     * @param bool $is_paypal_buttons_product_eligible  Whether PayPal buttons for product page are eligible.
     * @param bool $is_apple_pay_domain_eligible        Whether Apple Pay domain registration is eligible.
     * @param bool $is_digital_wallet_eligible          Whether digital wallet features are eligible.
     * @param bool $is_apple_pay_eligible               Whether Apple Pay is eligible.
     * @param bool $is_google_pay_eligible              Whether Google Pay is eligible.
     * @param bool $is_enable_apple_pay_eligible        Whether enabling Apple Pay is eligible.
     * @param bool $is_enable_google_pay_eligible       Whether enabling Google Pay is eligible.
     * @param bool $is_enable_installments_eligible     Whether enabling Installments is eligible.
     * @param bool $is_working_capital_eligible         Whether applying for Working Capital is eligible.
     * @param bool $is_enable_pwc_eligible              Whether enabling Pay with Crypto is eligible.
     * @param bool $is_apply_for_pwc                    Whether applying for Pay with Crypto is eligible.
     * @param bool $is_recaptcha_protection_eligible    Whether enabling reCAPTCHA protection is eligible.
     */
    public function __construct(bool $is_fastlane_eligible, bool $is_pay_later_messaging_eligible, bool $is_pay_later_messaging_product_eligible, bool $is_pay_later_messaging_cart_eligible, bool $is_pay_later_messaging_checkout_eligible, bool $is_subscription_eligible, bool $is_paypal_buttons_cart_eligible, bool $is_paypal_buttons_block_checkout_eligible, bool $is_paypal_buttons_product_eligible, bool $is_apple_pay_domain_eligible, bool $is_digital_wallet_eligible, bool $is_apple_pay_eligible, bool $is_google_pay_eligible, bool $is_enable_apple_pay_eligible, bool $is_enable_google_pay_eligible, bool $is_enable_installments_eligible, bool $is_working_capital_eligible, bool $is_enable_pwc_eligible, bool $is_apply_for_pwc, bool $is_recaptcha_protection_eligible)
    {
        $this->is_fastlane_eligible = $is_fastlane_eligible;
        $this->is_pay_later_messaging_eligible = $is_pay_later_messaging_eligible;
        $this->is_pay_later_messaging_product_eligible = $is_pay_later_messaging_product_eligible;
        $this->is_pay_later_messaging_cart_eligible = $is_pay_later_messaging_cart_eligible;
        $this->is_pay_later_messaging_checkout_eligible = $is_pay_later_messaging_checkout_eligible;
        $this->is_subscription_eligible = $is_subscription_eligible;
        $this->is_paypal_buttons_cart_eligible = $is_paypal_buttons_cart_eligible;
        $this->is_paypal_buttons_block_checkout_eligible = $is_paypal_buttons_block_checkout_eligible;
        $this->is_paypal_buttons_product_eligible = $is_paypal_buttons_product_eligible;
        $this->is_apple_pay_domain_eligible = $is_apple_pay_domain_eligible;
        $this->is_digital_wallet_eligible = $is_digital_wallet_eligible;
        $this->is_apple_pay_eligible = $is_apple_pay_eligible;
        $this->is_google_pay_eligible = $is_google_pay_eligible;
        $this->is_enable_apple_pay_eligible = $is_enable_apple_pay_eligible;
        $this->is_enable_google_pay_eligible = $is_enable_google_pay_eligible;
        $this->is_enable_installments_eligible = $is_enable_installments_eligible;
        $this->is_working_capital_eligible = $is_working_capital_eligible;
        $this->is_enable_pwc_eligible = $is_enable_pwc_eligible;
        $this->is_apply_for_pwc = $is_apply_for_pwc;
        $this->is_recaptcha_protection_eligible = $is_recaptcha_protection_eligible;
    }
    /**
     * Returns all eligibility checks as callables.
     *
     * @return array<string, callable>
     */
    public function get_eligibility_checks(): array
    {
        return array('enable_fastlane' => fn() => $this->is_fastlane_eligible, 'enable_pay_later_messaging' => fn() => $this->is_pay_later_messaging_eligible, 'add_pay_later_messaging_product_page' => fn() => $this->is_pay_later_messaging_product_eligible, 'add_pay_later_messaging_cart' => fn() => $this->is_pay_later_messaging_cart_eligible, 'add_pay_later_messaging_checkout' => fn() => $this->is_pay_later_messaging_checkout_eligible, 'configure_paypal_subscription' => fn() => $this->is_subscription_eligible, 'add_paypal_buttons_cart' => fn() => $this->is_paypal_buttons_cart_eligible, 'add_paypal_buttons_block_checkout' => fn() => $this->is_paypal_buttons_block_checkout_eligible, 'add_paypal_buttons_product' => fn() => $this->is_paypal_buttons_product_eligible, 'register_domain_apple_pay' => fn() => $this->is_apple_pay_domain_eligible, 'add_digital_wallets' => fn() => $this->is_digital_wallet_eligible, 'add_apple_pay' => fn() => $this->is_apple_pay_eligible, 'add_google_pay' => fn() => $this->is_google_pay_eligible, 'enable_apple_pay' => fn() => $this->is_enable_apple_pay_eligible, 'enable_google_pay' => fn() => $this->is_enable_google_pay_eligible, 'enable_installments' => fn() => $this->is_enable_installments_eligible, 'apply_for_working_capital' => fn() => $this->is_working_capital_eligible, 'enable_pwc' => fn() => $this->is_enable_pwc_eligible, 'apply_for_pwc' => fn() => $this->is_apply_for_pwc, 'enable_recaptcha_protection' => fn() => $this->is_recaptcha_protection_eligible);
    }
}
