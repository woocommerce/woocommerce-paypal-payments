<?php
/**
 * Used to detect the current environment.
 *
 * @package WooCommerce\PayPalCommerce\Onboarding
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Onboarding;

use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

/**
 * Class Environment
 */
class Environment {


	const PRODUCTION = 'production';
	const SANDBOX    = 'sandbox';

	/**
	 * The Settings.
	 *
	 * @var ContainerInterface
	 */
	private $settings;

	/**
	 * Environment constructor.
	 *
	 * @param ContainerInterface $settings The Settings.
	 */
	public function __construct( ContainerInterface $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Returns the current environment.
	 *
	 * @return string
	 */
	public function current_environment(): string {
		return (
			$this->settings->has( 'sandbox_on' ) && $this->settings->get( 'sandbox_on' )
		) ? self::SANDBOX : self::PRODUCTION;
	}

	/**
	 * Detect whether the current environment equals $environment
	 *
	 * @param string $environment The value to check against.
	 *
	 * @return bool
	 */
	public function current_environment_is( string $environment ): bool {
		return $this->current_environment() === $environment;
	}
}
