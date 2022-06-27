<?php
/**
 * Plugin Name: WooCommerce PayPal Payments e2e
 * Description: PPCP e2e
 * Version:     1.0.0
 * Author:      Inpsyde
 * License:     GPL-2.0
 */

declare(strict_types=1);

class PPCP_E2E
{
	public static $container;
}

add_filter('woocommerce_paypal_payments_built_container', function($app_container): void {
	PPCP_E2E::$container = $app_container;
});
