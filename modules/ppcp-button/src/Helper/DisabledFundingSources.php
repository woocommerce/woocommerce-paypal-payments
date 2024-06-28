<?php
/**
 * Creates the list of disabled funding sources.
 *
 * @package WooCommerce\PayPalCommerce\Button\Helper
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Button\Helper;

use WooCommerce\PayPalCommerce\WcGateway\Exception\NotFoundException;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CardButtonGateway;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
use WooCommerce\PayPalCommerce\WcSubscriptions\FreeTrialHandlerTrait;

/**
 * Class DisabledFundingSources
 */
class DisabledFundingSources {

	use FreeTrialHandlerTrait;

	/**
	 * The settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * All existing funding sources.
	 *
	 * @var array
	 */
	private $all_funding_sources;

	/**
	 * DisabledFundingSources constructor.
	 *
	 * @param Settings $settings The settings.
	 * @param array    $all_funding_sources All existing funding sources.
	 */
	public function __construct( Settings $settings, array $all_funding_sources ) {
		$this->settings            = $settings;
		$this->all_funding_sources = $all_funding_sources;
	}

	/**
	 * Returns the list of funding sources to be disabled.
	 *
	 * @param string $context The context.
	 * @return array|int[]|mixed|string[]
	 * @throws NotFoundException When the setting is not found.
	 */
	public function sources( string $context ) {
		$disable_funding = $this->settings->has( 'disable_funding' )
			? $this->settings->get( 'disable_funding' )
			: array();

		$is_dcc_enabled = $this->settings->has( 'dcc_enabled' ) && $this->settings->get( 'dcc_enabled' );

		if (
			! is_checkout()
			|| ( $is_dcc_enabled && in_array( $context, array( 'checkout-block', 'cart-block' ), true ) )
		) {
			$disable_funding[] = 'card';
		}

		$available_gateways       = WC()->payment_gateways->get_available_payment_gateways();
		$is_separate_card_enabled = isset( $available_gateways[ CardButtonGateway::ID ] );

		if (
			(
				is_checkout()
				&& ! in_array( $context, array( 'checkout-block', 'cart-block' ), true )
			)
			&& (
				$is_dcc_enabled
				|| $is_separate_card_enabled
			)
		) {
			$key = array_search( 'card', $disable_funding, true );
			if ( false !== $key ) {
				unset( $disable_funding[ $key ] );
			}
		}

		if ( in_array( $context, array( 'checkout-block', 'cart-block' ), true ) ) {
			$disable_funding = array_merge(
				$disable_funding,
				array_diff(
					array_keys( $this->all_funding_sources ),
					array( 'venmo', 'paylater', 'paypal', 'card' )
				)
			);
		}

		if ( $this->is_free_trial_cart() ) {
			$all_sources = array_keys( $this->all_funding_sources );
			if ( $is_dcc_enabled || $is_separate_card_enabled ) {
				$all_sources = array_diff( $all_sources, array( 'card' ) );
			}
			$disable_funding = $all_sources;
		}

		return $disable_funding;
	}
}
