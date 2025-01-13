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
class ConnectAdminNotice {

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
	 * Whether the current store's country is classified as a send-only country..
	 *
	 * @var bool
	 */
	private bool $is_current_country_send_only;

	/**
	 * ConnectAdminNotice constructor.
	 *
	 * @param State              $state The state.
	 * @param ContainerInterface $settings The settings.
	 * @param bool               $is_current_country_send_only Whether the current store's country is classified as a send-only country.
	 */
	public function __construct( State $state, ContainerInterface $settings, bool $is_current_country_send_only ) {
		$this->state                        = $state;
		$this->settings                     = $settings;
		$this->is_current_country_send_only = $is_current_country_send_only;
	}

	/**
	 * Returns the message.
	 *
	 * @return Message|null
	 */
	public function connect_message() {
		if ( ! $this->should_display() ) {
			return null;
		}

		$message = sprintf(
			/* translators: %1$s the gateway name. */
			__(
				'PayPal Payments is almost ready. To get started, connect your account with the <b>Activate PayPal</b> button <a href="%1$s">on the Account Setup page</a>.',
				'woocommerce-paypal-payments'
			),
			admin_url( 'admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway&ppcp-tab=' . Settings::CONNECTION_TAB_ID )
		);
		return new Message( $message, 'warning' );
	}

	/**
	 * Whether the message should display.
	 *
	 * @return bool
	 */
	protected function should_display(): bool {
		return $this->state->current_state() !== State::STATE_ONBOARDED && $this->is_current_country_send_only === false;
	}
}
