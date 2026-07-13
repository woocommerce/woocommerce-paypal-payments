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
	 */
	'button.smart-button' => static function ( SmartButtonInterface $smart_button, ContainerInterface $container ): SmartButtonInterface {
		$manager = $container->get( 'sdk-v6.manager' );
		assert( $manager instanceof SdkV6Manager );

		if ( $manager->should_load_on_current_page() ) {
			return new DisabledSmartButton();
		}

		return $smart_button;
	},
);
