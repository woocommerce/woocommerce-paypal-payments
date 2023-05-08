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
use WooCommerce\PayPalCommerce\WcGateway\Helper\SettingsStatus;

/**
 * Creates the admin message about the gateway being enabled without the PayPal gateway.
 */
class GatewayWithoutPayPalAdminNotice {
	private const NOTICE_OK                = '';
	private const NOTICE_DISABLED_GATEWAY  = 'disabled_gateway';
	private const NOTICE_DISABLED_LOCATION = 'disabled_location';

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
	 * The Settings status helper.
	 *
	 * @var SettingsStatus|null
	 */
	protected $settings_status;

	/**
	 * ConnectAdminNotice constructor.
	 *
	 * @param string              $id The gateway ID.
	 * @param State               $state The state.
	 * @param ContainerInterface  $settings The settings.
	 * @param bool                $is_payments_page Whether the current page is the WC payment page.
	 * @param bool                $is_ppcp_settings_page Whether the current page is the PPCP settings page.
	 * @param SettingsStatus|null $settings_status The Settings status helper.
	 */
	public function __construct(
		string $id,
		State $state,
		ContainerInterface $settings,
		bool $is_payments_page,
		bool $is_ppcp_settings_page,
		?SettingsStatus $settings_status = null
	) {
		$this->id                    = $id;
		$this->state                 = $state;
		$this->settings              = $settings;
		$this->is_payments_page      = $is_payments_page;
		$this->is_ppcp_settings_page = $is_ppcp_settings_page;
		$this->settings_status       = $settings_status;
	}

	/**
	 * Returns the message.
	 *
	 * @return Message|null
	 */
	public function message(): ?Message {
		$notice_type = $this->check();

		switch ( $notice_type ) {
			case self::NOTICE_DISABLED_GATEWAY:
				/* translators: %1$s the gateway name, %2$s URL. */
				$text = __(
					'%1$s cannot be used without the PayPal gateway. <a href="%2$s">Enable the PayPal gateway</a>.',
					'woocommerce-paypal-payments'
				);
				break;
			case self::NOTICE_DISABLED_LOCATION:
				/* translators: %1$s the gateway name, %2$s URL. */
				$text = __(
					'%1$s cannot be used without enabling the Checkout location for the PayPal gateway. <a href="%2$s">Enable the Checkout location</a>.',
					'woocommerce-paypal-payments'
				);
				break;
			default:
				return null;
		}

		$gateway = $this->get_gateway();
		if ( ! $gateway ) {
			return null;
		}

		$name = $gateway->get_method_title();

		$message = sprintf(
			$text,
			$name,
			admin_url( 'admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway' )
		);
		return new Message( $message, 'warning' );
	}

	/**
	 * Checks whether one of the messages should be displayed.
	 *
	 * @return string One of the NOTICE_* constants.
	 */
	protected function check(): string {
		if ( State::STATE_ONBOARDED !== $this->state->current_state() ||
			( ! $this->is_payments_page && ! $this->is_ppcp_settings_page ) ) {
			return self::NOTICE_OK;
		}

		$gateway         = $this->get_gateway();
		$gateway_enabled = $gateway && wc_string_to_bool( $gateway->get_option( 'enabled' ) );

		if ( ! $gateway_enabled ) {
			return self::NOTICE_OK;
		}

		$paypal_enabled = $this->settings->has( 'enabled' ) && $this->settings->get( 'enabled' );
		if ( ! $paypal_enabled ) {
			return self::NOTICE_DISABLED_GATEWAY;
		}

		if ( $this->settings_status && ! $this->settings_status->is_smart_button_enabled_for_location( 'checkout' ) ) {
			return self::NOTICE_DISABLED_LOCATION;
		}

		return self::NOTICE_OK;
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
