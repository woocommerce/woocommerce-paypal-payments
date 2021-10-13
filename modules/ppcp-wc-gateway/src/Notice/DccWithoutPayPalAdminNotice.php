<?php
/**
 * Creates the admin message about the DCC gateway being enabled without the PayPal gateway.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Notice
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Notice;

use WooCommerce\PayPalCommerce\AdminNotices\Entity\Message;
use WooCommerce\PayPalCommerce\Onboarding\State;
use Psr\Container\ContainerInterface;

/**
 * Creates the admin message about the DCC gateway being enabled without the PayPal gateway.
 */
class DccWithoutPayPalAdminNotice {

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
	 * Whether the current page is the WC payment page.
	 *
	 * @var bool
	 */
	private $is_payments_page;

	/**
	 * Whether the current page is the PPCP settings page.
	 *
	 * @var bool
	 */
	private $is_ppcp_settings_page;

	/**
	 * ConnectAdminNotice constructor.
	 *
	 * @param State              $state The state.
	 * @param ContainerInterface $settings The settings.
	 * @param bool               $is_payments_page Whether the current page is the WC payment page.
	 * @param bool               $is_ppcp_settings_page Whether the current page is the PPCP settings page.
	 */
	public function __construct(
		State $state,
		ContainerInterface $settings,
		bool $is_payments_page,
		bool $is_ppcp_settings_page
	) {
		$this->state                 = $state;
		$this->settings              = $settings;
		$this->is_payments_page      = $is_payments_page;
		$this->is_ppcp_settings_page = $is_ppcp_settings_page;
	}

	/**
	 * Returns the message.
	 *
	 * @return Message|null
	 */
	public function message(): ?Message {
		if ( ! $this->should_display() ) {
			return null;
		}

		$message = sprintf(
			/* translators: %1$s the gateway name. */
			__(
				'PayPal Card Processing cannot be used without the PayPal gateway. <a href="%1$s">Enable the PayPal Gateway</a>.',
				'woocommerce-paypal-payments'
			),
			admin_url( 'admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway' )
		);
		return new Message( $message, 'warning' );
	}

	/**
	 * Whether the message should be displayed.
	 *
	 * @return bool
	 */
	protected function should_display(): bool {
		return State::STATE_ONBOARDED === $this->state->current_state()
				&& ( $this->is_payments_page || $this->is_ppcp_settings_page )
			&& ( $this->settings->has( 'dcc_enabled' ) && $this->settings->get( 'dcc_enabled' ) )
			&& ( ! $this->settings->has( 'enabled' ) || ! $this->settings->get( 'enabled' ) );
	}
}
