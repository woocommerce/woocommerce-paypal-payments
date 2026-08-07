<?php
/**
 * The SDK v6 module extensions.
 *
 * @package WooCommerce\PayPalCommerce\SdkV6
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\SdkV6;

use WooCommerce\PayPalCommerce\Button\Assets\DisabledSmartButton;
use WooCommerce\PayPalCommerce\Button\Assets\SmartButtonInterface;
use WooCommerce\PayPalCommerce\SdkV6\Assets\AddPaymentMethodManager;
use WooCommerce\PayPalCommerce\SdkV6\Assets\SdkV6Manager;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

return array(
	/**
	 * Both SDKs claim the window.paypal global, so the v5 smart-button
	 * script must not load on pages where the v6 SDK loads. On all other
	 * pages (block cart/checkout, pay-now — out of this module's scope)
	 * the v5 stack keeps running: the blocks, applepay, googlepay and
	 * axo modules consume this service's script_data() and would break
	 * under a blanket disable.
	 *
	 * MIGRATION-PHASE ONLY: this per-page handoff exists so the site and
	 * the test suites stay fully functional while the v6 migration
	 * stories (PCP-5781–5788) land one surface at a time. Once every
	 * surface is v6-owned and the epic is release-ready, replace this
	 * with an unconditional swap (or stop loading the v5 modules
	 * entirely) — the final state is a whole-flow flag flip, not
	 * per-page coexistence. Do not extend this mechanism with further
	 * per-surface toggles or compatibility shims.
	 */
	'button.smart-button' => static function ( SmartButtonInterface $smart_button, ContainerInterface $container ): SmartButtonInterface {
		$manager = $container->get( 'sdk-v6.manager' );
		assert( $manager instanceof SdkV6Manager );

		if ( $manager->should_load_on_current_page() ) {
			return new DisabledSmartButton();
		}

		// The Add Payment Method page is not one of the SdkV6Manager's
		// button locations, but the v6 vault button loads there and would
		// collide with the v5 SDK on the window.paypal global. Disable the
		// v5 smart button there too when the v6 save button loads. The v5
		// add-payment-method script still runs for the (deferred) card
		// fields; it loads under its own data-namespace and does not touch
		// window.paypal.
		$add_payment_method_manager = $container->get( 'sdk-v6.add-payment-method-manager' );
		assert( $add_payment_method_manager instanceof AddPaymentMethodManager );

		if ( $add_payment_method_manager->should_load_on_current_page() ) {
			return new DisabledSmartButton();
		}

		return $smart_button;
	},
);
