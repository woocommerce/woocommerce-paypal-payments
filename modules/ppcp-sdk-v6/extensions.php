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
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

return array(
	/**
	 * The v6 Web Components replace the v5 smart buttons: both SDKs claim
	 * the window.paypal global, so the v5 script must not load while the
	 * sdk-v6 module is active (this module only loads behind the
	 * sdk_v6_enabled feature flag).
	 */
	'button.smart-button' => static function ( SmartButtonInterface $smart_button, ContainerInterface $container ): SmartButtonInterface {
		return new DisabledSmartButton();
	},
);
