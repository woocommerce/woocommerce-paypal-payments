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

	const STATE_START     = 0;
	const STATE_ONBOARDED = 8;

	/**
	 * The Settings.
	 *
	 * @var ContainerInterface
	 */
	private $settings;

	/**
	 * State constructor.
	 *
	 * @param ContainerInterface $settings The Settings.
	 */
	public function __construct(
		ContainerInterface $settings
	) {

		$this->settings = $settings;
	}

	/**
	 * Returns the state of the specified environment (or the active environment if null).
	 *
	 * @param string|null $environment 'sandbox', 'production'.
	 * @return int
	 */
	public function environment_state( ?string $environment = null ): int {
		switch ( $environment ) {
			case Environment::PRODUCTION:
				return $this->production_state();
			case Environment::SANDBOX:
				return $this->sandbox_state();
		}
		return $this->current_state();
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
				'merchant_id_production',
				'client_id_production',
				'client_secret_production',
			)
		);
	}

	/**
	 * Translates an onboarding state to a string.
	 *
	 * @param int $state An onboarding state to translate.
	 * @return string A string representing the state: "start" or "onboarded".
	 */
	public static function get_state_name( int $state ) : string {
		switch ( $state ) {
			case self::STATE_START:
				return 'start';
			case self::STATE_ONBOARDED:
				return 'onboarded';
			default:
				return 'unknown';
		}
	}

	/**
	 * Returns the state based on onboarding settings values.
	 *
	 * @param array $onboarded_keys The keys which need to be present to be in onboarded state.
	 *
	 * @return int
	 */
	private function state_by_keys( array $onboarded_keys ) : int {
		foreach ( $onboarded_keys as $key ) {
			if ( ! $this->settings->has( $key ) || ! $this->settings->get( $key ) ) {
				return self::STATE_START;
			}
		}

		return self::STATE_ONBOARDED;
	}
}
