<?php
/**
 * Used to determine the current state of onboarding.
 *
 * @package WooCommerce\PayPalCommerce\Onboarding
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Onboarding;

use Psr\Container\ContainerInterface;

/**
 * Class State
 */
class State {

	const STATE_START       = 0;
	const STATE_PROGRESSIVE = 4;
	const STATE_ONBOARDED   = 8;

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
	 * Returns the current active onboarding state.
	 *
	 * @return int
	 */
	public function current_state(): int {

		return $this->state_by_keys(
			array(
				'merchant_email',
			),
			array(
				'merchant_email',
				'merchant_id',
				'client_id',
				'client_secret',
			)
		);
	}

	/**
	 * Returns the onboarding state of the sandbox.
	 *
	 * @return int
	 */
	public function sandbox_state() : int {

		return $this->state_by_keys(
			array(
				'merchant_email_sandbox',
			),
			array(
				'merchant_email_sandbox',
				'merchant_id_sandbox',
				'client_id_sandbox',
				'client_secret_sandbox',
			)
		);
	}

	/**
	 * Returns the onboarding state of the production mode.
	 *
	 * @return int
	 */
	public function production_state() : int {

		return $this->state_by_keys(
			array(
				'merchant_email_production',
			),
			array(
				'merchant_email_production',
				'merchant_id_production',
				'client_id_production',
				'client_secret_production',
			)
		);
	}

	/**
	 * Returns the state based on progressive and onboarded values being looked up in the settings.
	 *
	 * @param array $progressive_keys The keys which need to be present to be at least in progressive state.
	 * @param array $onboarded_keys The keys which need to be present to be in onboarded state.
	 *
	 * @return int
	 */
	private function state_by_keys( array $progressive_keys, array $onboarded_keys ) : int {
		$state          = self::STATE_START;
		$is_progressive = true;
		foreach ( $progressive_keys as $key ) {
			if ( ! $this->settings->has( $key ) || ! $this->settings->get( $key ) ) {
				$is_progressive = false;
			}
		}
		if ( $is_progressive ) {
			$state = self::STATE_PROGRESSIVE;
		}

		$is_onboarded = true;
		foreach ( $onboarded_keys as $key ) {
			if ( ! $this->settings->has( $key ) || ! $this->settings->get( $key ) ) {
				$is_onboarded = false;
			}
		}

		if ( $is_onboarded ) {
			$state = self::STATE_ONBOARDED;
		}

		return $state;
	}
}
