<?php
/**
 * Describes the current API environment (production or sandbox).
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Helper
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\WcGateway\Helper;

use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

/**
 * Class Environment
 */
class Environment {

	/**
	 * Name of the production environment.
	 */
	public const PRODUCTION = 'production';

	/**
	 * Name of the sandbox environment.
	 */
	public const SANDBOX = 'sandbox';

	/**
	 * The Settings.
	 *
	 * @var ContainerInterface
	 */
	private ContainerInterface $settings;

	/**
	 * Environment constructor.
	 *
	 * @param ContainerInterface $settings The settings.
	 */
	public function __construct( ContainerInterface $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Returns the current environment.
	 *
	 * @return string
	 */
	public function current_environment() : string {
		return (
			$this->settings->has( 'sandbox_on' ) && $this->settings->get( 'sandbox_on' )
		) ? self::SANDBOX : self::PRODUCTION;
	}

	/**
	 * Detect whether the current environment equals $environment
	 *
	 * @deprecated Use the is_sandbox() and is_production() methods instead.
	 *             These methods provide better encapsulation, are less error-prone,
	 *             and improve code readability by removing the need to pass environment constants.
	 * @param string $environment The value to check against.
	 *
	 * @return bool
	 */
	public function current_environment_is( string $environment ) : bool {
		return $this->current_environment() === $environment;
	}

	/**
	 * Returns whether the current environment is sandbox.
	 *
	 * @return bool
	 */
	public function is_sandbox() : bool {
		return $this->current_environment() === self::SANDBOX;
	}

	/**
	 * Returns whether the current environment is production.
	 *
	 * @return bool
	 */
	public function is_production() : bool {
		return $this->current_environment() === self::PRODUCTION;
	}
}
