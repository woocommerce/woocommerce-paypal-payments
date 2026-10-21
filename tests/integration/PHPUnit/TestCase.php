<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Tests\Integration;

use LogicException;
use Mockery;
use WooCommerce\PayPalCommerce\PPCP;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WC_Cart;
use WC_Customer;
use WC_Session;

class TestCase extends \PHPUnit\Framework\TestCase
{
	protected $container;

	protected function getContainer(): ContainerInterface {
		return PPCP::container();
	}

	protected function cart(): WC_Cart {
		return WC()->cart;
	}

	protected function customer(): WC_Customer {
		return WC()->customer;
	}

	protected function session(): WC_Session {
		return WC()->session;
	}

	/** @var array<string, \WP_Hook> */
	private $saved_hooks = array();

	/** @var ContainerInterface|null */
	private $saved_container = null;

	protected function setUp(): void {
		parent::setUp();

		try {
			$this->saved_container = PPCP::container();
		} catch ( LogicException $exception ) {
			$this->saved_container = null;
		}

		$this->saved_hooks = array();
		foreach ( $GLOBALS['wp_filter'] as $name => $hook ) {
			$this->saved_hooks[ $name ] = clone $hook;
		}
	}

	public function tearDown(): void {
		// Clean up mocks
		Mockery::close();

		$GLOBALS['wp_filter'] = $this->saved_hooks;
		if ( $this->saved_container ) {
			PPCP::init( $this->saved_container );
		}
		wp_set_current_user( 0 );

		parent::tearDown();
	}
}
