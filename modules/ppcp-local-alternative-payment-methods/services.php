<?php
/**
 * The local alternative payment methods module services.
 *
 * @package WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods;

use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

return array(
	'ppcp-local-apms.bancontact.wc-gateway' =>  static function ( ContainerInterface $container ): BancontactGateway {
		return new BancontactGateway();
	},
);
