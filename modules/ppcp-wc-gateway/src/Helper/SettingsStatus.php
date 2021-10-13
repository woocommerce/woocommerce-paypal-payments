<?php
/**
 * Helper to get settings status.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Helper
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Helper;

use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;

/**
 * Class SettingsStatus
 */
class SettingsStatus {

	/**
	 * The Settings.
	 *
	 * @var Settings
	 */
	protected $settings;

	/**
	 * SettingsStatus constructor.
	 *
	 * @param Settings $settings The Settings.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Check whether Pay Later message is enabled either for checkout, cart or product page.
	 *
	 * @return bool
	 * @throws \WooCommerce\PayPalCommerce\WcGateway\Exception\NotFoundException When a setting was not found.
	 */
	public function pay_later_messaging_is_enabled(): bool {
		$pay_later_message_enabled_for_checkout = $this->settings->has( 'message_enabled' )
			&& (bool) $this->settings->get( 'message_enabled' );

		$pay_later_message_enabled_for_cart = $this->settings->has( 'message_cart_enabled' )
			&& (bool) $this->settings->get( 'message_cart_enabled' );

		$pay_later_message_enabled_for_product = $this->settings->has( 'message_product_enabled' )
			&& (bool) $this->settings->get( 'message_product_enabled' );

		return $pay_later_message_enabled_for_checkout ||
			$pay_later_message_enabled_for_cart ||
			$pay_later_message_enabled_for_product;
	}
}
