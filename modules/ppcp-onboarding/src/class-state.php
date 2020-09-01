<?php
/**
 * Used to determine the current state of onboarding.
 *
 * @package Inpsyde\PayPalCommerce\Onboarding
 */

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Onboarding;

use Psr\Container\ContainerInterface;

/**
 * Class State
 */
class State {

	public const STATE_START       = 0;
	public const STATE_PROGRESSIVE = 4;
	public const STATE_ONBOARDED   = 8;

	/**
	 * The Environment.
	 *
	 * @var Environment
	 */
	private $environment;

	/**
	 * The Settings.
	 *
	 * @var ContainerInterface
	 */
	private $settings;

	/**
	 * State constructor.
	 *
	 * @param Environment        $environment The Environment.
	 * @param ContainerInterface $settings The Settings.
	 */
	public function __construct(
		Environment $environment,
		ContainerInterface $settings
	) {

		$this->environment = $environment;
		$this->settings    = $settings;
	}

	/**
	 * Returns the current onboarding state.
	 *
	 * @return int
	 */
	public function current_state(): int {
		$value = self::STATE_START;
		/**
		 * Having provided the merchant email means, we are at least
		 * in the progressive phase of our onboarding.
		 */
		if (
			$this->settings->has( 'merchant_email' )
			&& is_email( $this->settings->get( 'merchant_email' ) )
		) {
			$value = self::STATE_PROGRESSIVE;
		}

		/**
		 * Once we can fetch credentials we are completely onboarded.
		 */
		if ( $this->settings->has( 'client_id' ) && $this->settings->get( 'client_id' ) ) {
			$value = self::STATE_ONBOARDED;
		}
		return $value;
	}
}
