<?php
/**
 * Registers the admin message to "connect your account" if necessary.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Notice
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Notice;

use WooCommerce\PayPalCommerce\AdminNotices\Entity\Message;
use WooCommerce\PayPalCommerce\Onboarding\State;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

/**
 * Class ConnectAdminNotice
 */
class UnsupportedCurrencyAdminNotice {

	/**
	 * The state.
	 *
	 * @var State
	 */
	private $state;

	/**
	 * The settings.
	 *
	 * @var ContainerInterface
	 */
	private $settings;

	/**
	 * ConnectAdminNotice constructor.
	 *
	 * @param State              $state The state.
	 * @param ContainerInterface $settings The settings.
	 */
	public function __construct( State $state, ContainerInterface $settings ) {
		$this->state    = $state;
		$this->settings = $settings;
	}

	/**
	 * Returns the message.
	 *
	 * @return Message|null
	 */
	public function unsupported_currency_message() {
		if ( ! $this->should_display() ) {
			return null;
		}

		$message = sprintf(
			/* translators: %1$s the gateway name. */
			__(
				'Attention: Your current WooCommerce store currency is not supported by PayPal. Please update your store currency to one that is supported by PayPal to ensure smooth transactions. Visit the <a href="%1$s">PayPal currency support page</a> for more information on supported currencies.',
				'woocommerce-paypal-payments'
			),
			"https://developer.paypal.com/api/rest/reference/currency-codes/"
		);
		return new Message( $message, 'warning' );
	}

	/**
	 * Whether the message should display.
	 *
	 * @return bool
	 */
	protected function should_display(): bool {
		return $this->state->current_state() === State::STATE_ONBOARDED && ! $this->currency_supported();
	}

	private function currency_supported()
	{
		//TODO - get the currency from the settings
	}
}
