<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce;

use Dhii\Container\ServiceProvider;
use Dhii\Modular\Module\ModuleInterface;
use Interop\Container\ServiceProviderInterface;
use Psr\Container\ContainerInterface;

class PluginModule implements ModuleInterface {


	public function setup(): ServiceProviderInterface {
		return new ServiceProvider( array(), array() );
	}

	public function run( ContainerInterface $container ) {
		// TODO: Implement run() method.
	}
}
