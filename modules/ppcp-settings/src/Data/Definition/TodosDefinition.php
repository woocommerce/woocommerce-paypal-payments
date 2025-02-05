<?php
/**
 * PayPal Commerce Todos Definitions
 *
 * @package WooCommerce\PayPalCommerce\Settings\Data\Definition
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Settings\Data\Definition;

use WooCommerce\PayPalCommerce\Settings\Service\TodosEligibilityService;
use WooCommerce\PayPalCommerce\Settings\Data\GeneralSettings;

/**
 * Class TodosDefinition
 *
 * Provides the definitions for all available todos in the system.
 * Each todo has a title, description, eligibility condition, and associated action.
 */
class TodosDefinition {

	/**
	 * The todos eligibility service.
	 *
	 * @var TodosEligibilityService
	 */
	protected TodosEligibilityService $eligibilities;

	/**
	 * The general settings service.
	 *
	 * @var GeneralSettings
	 */
	protected GeneralSettings $settings;

	/**
	 * Constructor.
	 *
	 * @param TodosEligibilityService $eligibilities The todos eligibility service.
	 * @param GeneralSettings         $settings The general settings service.
	 */
	public function __construct(
		TodosEligibilityService $eligibilities,
		GeneralSettings $settings
	) {
		$this->eligibilities = $eligibilities;
		$this->settings      = $settings;
	}

	/**
	 * Returns the full list of todo definitions with their eligibility conditions.
	 *
	 * @return array The array of todo definitions.
	 */
	public function get(): array {
		$eligibility_checks = $this->eligibilities->get_eligibility_checks();

		return array(
			'enable_fastlane'               => array(
				'title'       => __( 'Enable Fastlane', 'woocommerce-paypal-payments' ),
				'description' => __( 'Accelerate your guest checkout with Fastlane by PayPal', 'woocommerce-paypal-payments' ),
				'isEligible'  => $eligibility_checks['enable_fastlane'],
				'action'      => array(
					'type'      => 'tab',
					'tab'       => 'payment_methods',
					'section'   => 'ppcp-fastlane',
					'modal'     => 'ppcp-fastlane-gateway',
					'highlight' => 'ppcp-fastlane-gateway',
				),
			),
			'enable_credit_debit_cards'     => array(
				'title'       => __( 'Enable Credit and Debit Cards on your checkout', 'woocommerce-paypal-payments' ),
				'description' => __( 'Credit and Debit Cards is now available for Blocks checkout pages', 'woocommerce-paypal-payments' ),
				'isEligible'  => $eligibility_checks['enable_credit_debit_cards'],
				'action'      => array(
					'type'      => 'tab',
					'tab'       => 'payment_methods',
					'section'   => 'ppcp-card-button-gateway',
					'highlight' => 'ppcp-card-button-gateway',
				),
			),
			'enable_pay_later_messaging'    => array(
				'title'       => __( 'Enable Pay Later messaging', 'woocommerce-paypal-payments' ),
				'description' => __( 'Show Pay Later messaging to boost conversion rate and increase cart size.', 'woocommerce-paypal-payments' ),
				'isEligible'  => $eligibility_checks['enable_pay_later_messaging'],
				'action'      => array(
					'type'    => 'tab',
					'tab'     => 'overview',
					'section' => 'pay_later_messaging',
				),
			),
			'add_pay_later_messaging'       => array(
				'title'       => __( 'Add Pay Later messaging', 'woocommerce-paypal-payments' ),
				'description' => __( 'Present Pay Later messaging on your <x> page to boost conversion rate and increase cart size.', 'woocommerce-paypal-payments' ),
				'isEligible'  => $eligibility_checks['add_pay_later_messaging'],
				'action'      => array(
					'type'    => 'tab',
					'tab'     => 'overview',
					'section' => 'pay_later_messaging',
				),
			),
			'configure_paypal_subscription' => array(
				'title'       => __( 'Configure a PayPal Subscription', 'woocommerce-paypal-payments' ),
				'description' => __( 'Connect a subscriptions-type product from WooCommerce with PayPal', 'woocommerce-paypal-payments' ),
				'isEligible'  => $eligibility_checks['configure_paypal_subscription'],
				'action'      => array(
					'type' => 'external',
					'url'  => admin_url( 'edit.php?post_type=product&product_type=subscription' ),
				),
			),
			'add_paypal_buttons'            => array(
				'title'       => __( 'Add PayPal buttons', 'woocommerce-paypal-payments' ),
				'description' => __( 'Allow customers to check out quickly and securely from the <x> page. Customers save time and get through checkout in fewer clicks.', 'woocommerce-paypal-payments' ),
				'isEligible'  => $eligibility_checks['add_paypal_buttons'],
				'action'      => array(
					'type' => 'tab',
					'tab'  => 'styling',
				),
			),
			'register_domain_apple_pay'     => array(
				'title'       => __( 'Register Domain for Apple Pay', 'woocommerce-paypal-payments' ),
				'description' => __( 'To enable Apple Pay, you must register your domain with PayPal', 'woocommerce-paypal-payments' ),
				'isEligible'  => $eligibility_checks['register_domain_apple_pay'],
				'action'      => array(
					'type'            => 'external',
					'url'             => $this->settings->is_sandbox_merchant()
						? 'https://www.sandbox.paypal.com/uccservicing/apm/applepay'
						: 'https://www.paypal.com/uccservicing/apm/applepay',
					'completeOnClick' => true,
				),
			),
			'add_digital_wallets'           => array(
				'title'       => __( 'Add digital wallets to your account', 'woocommerce-paypal-payments' ),
				'description' => __( 'Add the ability to accept Apple Pay & Google Pay to your PayPal account', 'woocommerce-paypal-payments' ),
				'isEligible'  => $eligibility_checks['add_digital_wallets'],
				'action'      => array(
					'type' => 'external',
					'url'  => 'https://www.paypal.com/businessmanage/account/settings',
				),
			),
			'add_apple_pay'                 => array(
				'title'       => __( 'Add Apple Pay to your account', 'woocommerce-paypal-payments' ),
				'description' => __( 'Add the ability to accept Apple Pay to your PayPal account', 'woocommerce-paypal-payments' ),
				'isEligible'  => $eligibility_checks['add_apple_pay'],
				'action'      => array(
					'type' => 'external',
					'url'  => 'https://www.paypal.com/businessmanage/account/settings',
				),
			),
			'add_google_pay'                => array(
				'title'       => __( 'Add Google Pay to your account', 'woocommerce-paypal-payments' ),
				'description' => __( 'Add the ability to accept Google Pay to your PayPal account', 'woocommerce-paypal-payments' ),
				'isEligible'  => $eligibility_checks['add_google_pay'],
				'action'      => array(
					'type' => 'external',
					'url'  => 'https://www.paypal.com/businessmanage/account/settings',
				),
			),
			'enable_apple_pay'              => array(
				'title'       => __( 'Enable Apple Pay', 'woocommerce-paypal-payments' ),
				'description' => __( 'Allow your buyers to check out via Apple Pay', 'woocommerce-paypal-payments' ),
				'isEligible'  => $eligibility_checks['enable_apple_pay'],
				'action'      => array(
					'type'      => 'tab',
					'tab'       => 'payment_methods',
					'section'   => 'ppcp-applepay',
					'highlight' => 'ppcp-applepay',
				),
			),
			'enable_google_pay'             => array(
				'title'       => __( 'Enable Google Pay', 'woocommerce-paypal-payments' ),
				'description' => __( 'Allow your buyers to check out via Google Pay', 'woocommerce-paypal-payments' ),
				'isEligible'  => $eligibility_checks['enable_google_pay'],
				'action'      => array(
					'type'      => 'tab',
					'tab'       => 'payment_methods',
					'section'   => 'ppcp-googlepay',
					'highlight' => 'ppcp-googlepay',
				),
			),
		);
	}
}
