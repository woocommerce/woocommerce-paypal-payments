<?php

/**
 * Creates the admin message about the gateway being enabled without the PayPal gateway.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Notice
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\WcGateway\Notice;

use WC_Payment_Gateway;
use WooCommerce\PayPalCommerce\AdminNotices\Entity\Message;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcGateway\Helper\SettingsStatus;
use WooCommerce\PayPalCommerce\WcGateway\Helper\CardPaymentsConfiguration;
/**
 * Creates the admin message about the gateway being enabled without the PayPal gateway.
 */
class GatewayWithoutPayPalAdminNotice
{
    private const NOTICE_OK = '';
    private const NOTICE_DISABLED_GATEWAY = 'disabled_gateway';
    private const NOTICE_DISABLED_LOCATION = 'disabled_location';
    private const NOTICE_DISABLED_CARD_BUTTON = 'disabled_card';
    /**
     * The gateway ID.
     *
     * @var string
     */
    private $id;
    /**
     * Whether the merchant completed onboarding.
     *
     * @var bool
     */
    private bool $is_connected;
    private SettingsProvider $settings_provider;
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
     * Provides details about the DCC configuration.
     *
     * @var CardPaymentsConfiguration
     */
    private CardPaymentsConfiguration $dcc_configuration;
    public function __construct(string $id, bool $is_connected, SettingsProvider $settings_provider, bool $is_payments_page, bool $is_ppcp_settings_page, CardPaymentsConfiguration $dcc_configuration, ?SettingsStatus $settings_status = null)
    {
        $this->id = $id;
        $this->is_connected = $is_connected;
        $this->settings_provider = $settings_provider;
        $this->is_payments_page = $is_payments_page;
        $this->is_ppcp_settings_page = $is_ppcp_settings_page;
        $this->dcc_configuration = $dcc_configuration;
        $this->settings_status = $settings_status;
    }
    /**
     * Returns the message.
     *
     * @return Message|null
     */
    public function message(): ?Message
    {
        $notice_type = $this->check();
        $url1 = '';
        $url2 = '';
        switch ($notice_type) {
            case self::NOTICE_DISABLED_GATEWAY:
                /* translators: %1$s the gateway name, %2$s URL. */
                $text = __('%1$s cannot be used without the PayPal gateway. <a href="%2$s">Enable the PayPal gateway</a>.', 'woocommerce-paypal-payments');
                break;
            case self::NOTICE_DISABLED_LOCATION:
                /* translators: %1$s the gateway name, %2$s URL. */
                $text = __('%1$s cannot be used without enabling the Checkout location for the PayPal gateway. <a href="%2$s">Enable the Checkout location</a>.', 'woocommerce-paypal-payments');
                break;
            case self::NOTICE_DISABLED_CARD_BUTTON:
                /* translators: %1$s Standard Card Button section URL, %2$s Advanced Card Processing section URL. */
                $text = __('The <a href="%1$s">Standard Card Button</a> cannot be used while <a href="%2$s">Advanced Card Processing</a> is enabled.', 'woocommerce-paypal-payments');
                $url1 = admin_url('admin.php?page=wc-settings&tab=checkout&section=ppcp-card-button-gateway');
                $url2 = admin_url('admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway');
                break;
            default:
                return null;
        }
        $gateway = $this->get_gateway();
        if (!$gateway) {
            return null;
        }
        $name = $gateway->get_method_title();
        $message = sprintf($text, $name, admin_url('admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway'));
        if ($notice_type === self::NOTICE_DISABLED_CARD_BUTTON) {
            $message = sprintf($text, $url1, $url2);
        }
        return new Message($message, 'warning');
    }
    /**
     * Checks whether one of the messages should be displayed.
     *
     * @return string One of the NOTICE_* constants.
     */
    protected function check(): string
    {
        if (!$this->is_connected || !$this->is_payments_page && !$this->is_ppcp_settings_page) {
            return self::NOTICE_OK;
        }
        $gateway = $this->get_gateway();
        $gateway_enabled = $gateway && wc_string_to_bool($gateway->get_option('enabled'));
        if (!$gateway_enabled) {
            return self::NOTICE_OK;
        }
        $paypal_enabled = $this->settings_provider->gateway_enabled(PayPalGateway::ID);
        if (!$paypal_enabled) {
            return self::NOTICE_DISABLED_GATEWAY;
        }
        if ($this->settings_status && !$this->settings_status->is_smart_button_enabled_for_location('checkout')) {
            return self::NOTICE_DISABLED_LOCATION;
        }
        $is_dcc_enabled = $this->dcc_configuration->is_enabled();
        if ($is_dcc_enabled) {
            return self::NOTICE_DISABLED_CARD_BUTTON;
        }
        return self::NOTICE_OK;
    }
    /**
     * Returns the gateway object or null.
     *
     * @return WC_Payment_Gateway|null
     */
    protected function get_gateway(): ?WC_Payment_Gateway
    {
        $gateways = WC()->payment_gateways->payment_gateways();
        if (!isset($gateways[$this->id])) {
            return null;
        }
        return $gateways[$this->id];
    }
}
