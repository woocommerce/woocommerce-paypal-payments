<?php
/**
 * Mock PayPal Express Checkout class.
 *
 * @package WooCommerce\PayPalCommerce\Compat\PPEC
 */

namespace WooCommerce\PayPalCommerce\Compat\PPEC;

/**
 * Mocks the PayPal Express Checkout gateway.
 */
class MockGateway extends \WC_Payment_Gateway {

	/**
	 * Constructor.
	 *
	 * @param string $title Gateway title.
	 */
	public function __construct( $title ) {
		$this->id           = PPECHelper::PPEC_GATEWAY_ID;
		$this->title        = $title;
		$this->method_title = $this->title;
		$this->description  = '';
		$this->supports     = array(
			'subscriptions',
			'subscription_cancellation',
			'subscription_suspension',
			'subscription_reactivation',
			'subscription_amount_changes',
			'subscription_date_changes',
			'subscription_payment_method_change',
			'subscription_payment_method_change_customer',
			'subscription_payment_method_change_admin',
			'multiple_subscriptions',
		);
	}

	/**
	 * Check if the gateway is available for use.
	 *
	 * @return bool
	 */
	public function is_available() {
		// Hide mock gateway, except on admin.
		return is_admin();
	}

}
