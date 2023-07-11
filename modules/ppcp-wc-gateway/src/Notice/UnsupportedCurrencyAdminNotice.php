<?php
/**
 * Registers the admin message about unsupported currency set in WC shop settings.
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
 * Class UnsupportedCurrencyAdminNotice
 */
class UnsupportedCurrencyAdminNotice {

	/**
	 * The state.
	 *
	 * @var State
	 */
	private $state;

	/**
	 * The supported currencies.
	 *
	 * @var array
	 */
	private $supported_currencies;

	/**
	 * The shop currency.
	 *
	 * @var string
	 */
	private $shop_currency;

	/**
	 * UnsupportedCurrencyAdminNotice constructor.
	 *
	 * @param State  $state The state.
	 * @param string $shop_currency The shop currency.
	 * @param array  $supported_currencies The supported currencies.
	 */
	public function __construct( State $state, string $shop_currency, array $supported_currencies ) {
		$this->state                = $state;
		$this->shop_currency        = $shop_currency;
		$this->supported_currencies = $supported_currencies;
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
			/* translators: %1$s the shop currency, 2$s the gateway name. */
			__(
				'Attention: Your current WooCommerce store currency (%1$s) is not supported by PayPal. Please update your store currency to one that is supported by PayPal to ensure smooth transactions. Visit the <a href="%2$s">PayPal currency support page</a> for more information on supported currencies.',
				'woocommerce-paypal-payments'
			),
			$this->shop_currency,
			'https://developer.paypal.com/api/rest/reference/currency-codes/'
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

	/**
	 * Whether the currency is supported by PayPal.
	 *
	 * @return bool
	 */
	private function currency_supported(): bool {
		$currency             = $this->shop_currency;
		$supported_currencies = $this->supported_currencies;
		return in_array( $currency, $supported_currencies, true );
	}
}
