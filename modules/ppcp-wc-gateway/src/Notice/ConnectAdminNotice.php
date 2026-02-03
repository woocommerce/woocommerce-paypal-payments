<?php

/**
 * Registers the admin message to "connect your account" if necessary.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Notice
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\WcGateway\Notice;

use WooCommerce\PayPalCommerce\AdminNotices\Entity\Message;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
/**
 * Class ConnectAdminNotice
 */
class ConnectAdminNotice
{
    /**
     * Whether the merchant completed the onboarding and is connected to PayPal.
     *
     * @var bool
     */
    private bool $is_connected;
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
     * @param bool               $is_connected Whether onboarding was completed.
     * @param ContainerInterface $settings The settings.
     * @param bool               $is_current_country_send_only Whether the current store's country is classified as a send-only country.
     */
    public function __construct(bool $is_connected, ContainerInterface $settings, bool $is_current_country_send_only)
    {
        $this->is_connected = $is_connected;
        $this->settings = $settings;
        $this->is_current_country_send_only = $is_current_country_send_only;
    }
    /**
     * Returns the message.
     *
     * @return Message|null
     */
    public function connect_message()
    {
        if (!$this->should_display()) {
            return null;
        }
        $message = sprintf(
            /* translators: %1$s the gateway name. */
            __('PayPal Payments is almost ready. To get started, connect your account with the <b>Activate PayPal Payments</b> button <a href="%1$s">on the Account Setup page</a>.', 'woocommerce-paypal-payments'),
            admin_url('admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway&ppcp-tab=' . Settings::CONNECTION_TAB_ID)
        );
        return new Message($message, 'warning');
    }
    /**
     * Returns whether the current page is plugins.php.
     *
     * @return bool
     */
    private function is_current_page_plugins_page(): bool
    {
        global $pagenow;
        return isset($pagenow) && $pagenow === 'plugins.php';
    }
    /**
     * Whether the message should display.
     *
     * Only display the "almost ready" message for merchants that did not complete
     * the onboarding wizard. Also, ensure their store country is eligible for
     * collecting PayPal payments.
     *
     * @return bool
     */
    protected function should_display(): bool
    {
        return $this->is_current_page_plugins_page() && !$this->is_connected && !$this->is_current_country_send_only;
    }
}
