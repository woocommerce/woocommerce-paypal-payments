<?php

namespace WooCommerce\PayPalCommerce\SavedPaymentChecker\src;

use WooCommerce\PayPalCommerce\Vendor\Dhii\Container\ServiceProvider;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Modular\Module\Exception\ModuleExceptionInterface;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Modular\Module\ModuleInterface;
use WooCommerce\PayPalCommerce\Vendor\Interop\Container\ServiceProviderInterface;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

class SavedPaymentCheckerModule implements ModuleInterface
{

	public function setup(): ServiceProviderInterface
	{
		return new ServiceProvider(
			require __DIR__ . '/../services.php',
			require __DIR__ . '/../extensions.php'
		);
	}

	public function run(ContainerInterface $c): void
	{
		add_action('admin_notices', function() {
			echo 'Its working';
		});
	}
}
