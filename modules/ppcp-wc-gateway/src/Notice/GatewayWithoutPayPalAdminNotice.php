<?php
/**
 * Creates the admin message about the gateway being enabled without the PayPal gateway.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Notice
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Notice;

use WC_Payment_Gateway;
use WooCommerce\PayPalCommerce\AdminNotices\Entity\Message;
use WooCommerce\PayPalCommerce\Onboarding\State;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

/**
 * Creates the admin message about the gateway being enabled without the PayPal gateway.
 */
class GatewayWithoutPayPalAdminNotice {
	/**
	 * The gateway ID.
	 *
	 * @var string
	 */
	private $id;

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
	 * @param string             $id The gateway ID.
	 * @param State              $state The state.
	 * @param ContainerInterface $settings The settings.
	 * @param bool               $is_payments_page Whether the current page is the WC payment page.
	 * @param bool               $is_ppcp_settings_page Whether the current page is the PPCP settings page.
	 */
	public function __construct(
		string $id,
		State $state,
		ContainerInterface $settings,
		bool $is_payments_page,
		bool $is_ppcp_settings_page
	) {
		$this->id                    = $id;
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

		$gateway = $this->get_gateway();
		if ( ! $gateway ) {
			return null;
		}

		$name = $gateway->get_method_title();

		$message = sprintf(
			/* translators: %1$s the gateway name, %2$s URL. */
			__(
				'%1$s cannot be used without the PayPal gateway. <a href="%2$s">Enable the PayPal gateway</a>.',
				'woocommerce-paypal-payments'
			),
			$name,
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
		if ( State::STATE_ONBOARDED !== $this->state->current_state() ||
			( ! $this->is_payments_page && ! $this->is_ppcp_settings_page ) ) {
			return false;
		}
		if ( $this->settings->has( 'enabled' ) && $this->settings->get( 'enabled' ) ) {
			return false;
		}

		$gateway = $this->get_gateway();

		return $gateway && wc_string_to_bool( $gateway->get_option( 'enabled' ) );
	}

	/**
	 * Returns the gateway object or null.
	 *
	 * @return WC_Payment_Gateway|null
	 */
	protected function get_gateway(): ?WC_Payment_Gateway {
		$gateways = WC()->payment_gateways->payment_gateways();
		if ( ! isset( $gateways[ $this->id ] ) ) {
			return null;
		}
		return $gateways[ $this->id ];
	}
}
