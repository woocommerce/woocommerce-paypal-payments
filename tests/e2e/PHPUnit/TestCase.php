<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Tests\E2e;

use PPCP_E2E;
use Psr\Container\ContainerInterface;

class TestCase extends \PHPUnit\Framework\TestCase
{
	protected $container;

	protected function getContainer(): ContainerInterface {
		return PPCP_E2E::$container;
	}
}
