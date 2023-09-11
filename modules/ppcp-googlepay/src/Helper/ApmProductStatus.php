<?php
/**
 * Status of the GooglePay merchant connection.
 *
 * @package WooCommerce\PayPalCommerce\Googlepay\Helper
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Googlepay\Helper;

use Throwable;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PartnersEndpoint;
use WooCommerce\PayPalCommerce\Onboarding\State;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;

/**
 * Class ApmProductStatus
 */
class ApmProductStatus {
	const CAPABILITY_NAME = 'GOOGLE_PAY';
	const SETTINGS_KEY    = 'products_googlepay_enabled';

	const SETTINGS_VALUE_ENABLED   = 'yes';
	const SETTINGS_VALUE_DISABLED  = 'no';
	const SETTINGS_VALUE_UNDEFINED = '';

	/**
	 * The current status stored in memory.
	 *
	 * @var bool|null
	 */
	private $current_status = null;

	/**
	 * The settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * The partners endpoint.
	 *
	 * @var PartnersEndpoint
	 */
	private $partners_endpoint;

	/**
	 * The onboarding status
	 *
	 * @var State
	 */
	private $onboarding_state;

	/**
	 * ApmProductStatus constructor.
	 *
	 * @param Settings         $settings The Settings.
	 * @param PartnersEndpoint $partners_endpoint The Partner Endpoint.
	 * @param State            $onboarding_state The onboarding state.
	 */
	public function __construct(
		Settings $settings,
		PartnersEndpoint $partners_endpoint,
		State $onboarding_state
	) {
		$this->settings          = $settings;
		$this->partners_endpoint = $partners_endpoint;
		$this->onboarding_state  = $onboarding_state;
	}

	/**
	 * Whether the active/subscribed products support Googlepay.
	 *
	 * @return bool
	 */
	public function is_active() : bool {
		if ( $this->onboarding_state->current_state() < State::STATE_ONBOARDED ) {
			return false;
		}

		if ( null !== $this->current_status ) {
			return $this->current_status;
		}

		if ( $this->settings->has( self::SETTINGS_KEY ) && ( $this->settings->get( self::SETTINGS_KEY ) ) ) {
			$this->current_status = wc_string_to_bool( $this->settings->get( self::SETTINGS_KEY ) );
			return $this->current_status;
		}

		try {
			$seller_status = $this->partners_endpoint->seller_status();
		} catch ( Throwable $error ) {
			// It may be a transitory error, don't persist the status.
			$this->current_status = false;
			return $this->current_status;
		}

		foreach ( $seller_status->products() as $product ) {
			if ( $product->name() !== 'PAYMENT_METHODS' ) {
				continue;
			}

			if ( in_array( self::CAPABILITY_NAME, $product->capabilities(), true ) ) {
				$this->settings->set( self::SETTINGS_KEY, self::SETTINGS_VALUE_ENABLED );
				$this->settings->persist();

				$this->current_status = true;
				return $this->current_status;
			}
		}

		$this->settings->set( self::SETTINGS_KEY, self::SETTINGS_VALUE_DISABLED );
		$this->settings->persist();

		$this->current_status = false;
		return $this->current_status;
	}

	/**
	 * Clears the persisted result to force a recheck.
	 *
	 * @param Settings|null $settings The settings object.
	 * We accept a Settings object to don't override other sequential settings that are being updated elsewhere.
	 * @return void
	 */
	public function clear( Settings $settings = null ): void {
		if ( null === $settings ) {
			$settings = $this->settings;
		}

		$this->current_status = null;

		$settings->set( self::SETTINGS_KEY, self::SETTINGS_VALUE_UNDEFINED );
		$settings->persist();
	}

}
