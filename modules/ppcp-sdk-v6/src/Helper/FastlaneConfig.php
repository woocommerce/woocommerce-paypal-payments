<?php
/**
 * Decides whether Fastlane runs on the current page under the v6 SDK.
 *
 * Narrower than the wallet configs on purpose: v6 does not re-implement the
 * Fastlane UI. The ppcp-axo modules keep rendering it and only swap how the SDK
 * object is obtained (see Connection/Fastlane.js), so all this class answers is
 * "should the fastlane component be requested here", which gates both the
 * `fastlane` script-data subtree and the SDK component list.
 *
 * @package WooCommerce\PayPalCommerce\SdkV6\Helper
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\SdkV6\Helper;

use WooCommerce\PayPalCommerce\WcGateway\Helper\CardPaymentsConfiguration;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\SubscriptionHelper;

class FastlaneConfig {

	/**
	 * The contexts Fastlane runs in. v5 offers it on the checkout only, and
	 * excludes the order-pay and order-received endpoints (AxoApplies).
	 */
	private const SUPPORTED_CONTEXTS = array( 'checkout', 'checkout-block' );

	private CardPaymentsConfiguration $card_payments_configuration;

	private SubscriptionHelper $subscription_helper;

	/**
	 * Whether the merchant is eligible at all: country/currency, the merchant
	 * filter and the gateway being enabled. Composed in services.php from the
	 * ppcp-axo services, which only exist while that module is loaded.
	 *
	 * @var callable(): bool
	 */
	private $is_eligible;

	public function __construct(
		CardPaymentsConfiguration $card_payments_configuration,
		SubscriptionHelper $subscription_helper,
		callable $is_eligible
	) {
		$this->card_payments_configuration = $card_payments_configuration;
		$this->subscription_helper         = $subscription_helper;
		$this->is_eligible                 = $is_eligible;
	}

	/**
	 * Whether Fastlane should run in the given page context.
	 *
	 * Mirrors AxoApplies::should_render_fastlane() minus its
	 * CartCheckoutDetector::has_classic_checkout() clause, which would refuse
	 * the block checkout that v6 also serves.
	 *
	 * @param string $context The page context, as get_page_context() reports it.
	 */
	public function should_render( string $context ): bool {
		// The subscription check reads the cart, which WooCommerce loads on
		// wp_loaded. Called earlier it would silently see an empty cart and
		// report Fastlane as available, so refuse rather than guess.
		if ( ! did_action( 'wp_loaded' ) ) {
			_doing_it_wrong(
				__METHOD__,
				esc_html__(
					'Fastlane availability cannot be determined before the wp_loaded action has run.',
					'woocommerce-paypal-payments'
				),
				'4.1.3'
			);

			return false;
		}

		if ( ! in_array( $context, self::SUPPORTED_CONTEXTS, true ) ) {
			return false;
		}

		// Fastlane recognises returning guests; a logged-in customer already
		// has their details on file.
		if ( is_user_logged_in() ) {
			return false;
		}

		// use_fastlane() is ACDC enabled AND the Fastlane method enabled.
		if ( ! $this->card_payments_configuration->use_fastlane() ) {
			return false;
		}

		// A subscription needs a vaulted payment method, which this flow does
		// not produce.
		if ( $this->subscription_helper->cart_contains_subscription() ) {
			return false;
		}

		return ( $this->is_eligible )();
	}
}
