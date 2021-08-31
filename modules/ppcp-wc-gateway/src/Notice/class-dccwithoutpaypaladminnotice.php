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
	public function message(): ?Message {
		if ( ! $this->should_display() ) {
			return null;
		}

		$message = sprintf(
			/* translators: %1$s the gateway name. */
			__(
				'PayPal Card Processing cannot be used without the PayPal gateway. <a href="%1$s">Enable  the PayPal Gateway</a>.',
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
			&& ( $this->settings->has( 'dcc_enabled' ) && $this->settings->get( 'dcc_enabled' ) )
			&& ( ! $this->settings->has( 'enabled' ) || ! $this->settings->get( 'enabled' ) );
	}
}
