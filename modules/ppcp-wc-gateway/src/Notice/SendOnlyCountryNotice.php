<?php

/**
 * Creates an admin message that notifies user about send only country.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Notice
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\WcGateway\Notice;

use WooCommerce\PayPalCommerce\AdminNotices\Entity\Message;
/**
 * Creates an admin message that notifies user about send only country.
 */
class SendOnlyCountryNotice
{
    /**
     * Notice text
     *
     * @var string
     */
    protected string $message_text;
    /**
     * Indicates if current country is on the send only list
     *
     * @var bool
     */
    protected bool $is_send_only_country;
    /**
     * Indicates if we're on the WooCommerce gateways list page.
     *
     * @var bool
     */
    private bool $is_wc_gateways_list_page;
    /**
     * Indicates if we're on a PPCP Settings page.
     *
     * @var bool
     */
    private bool $is_ppcp_settings_page;
    /**
     * Onboarding state
     *
     * @var bool
     */
    private bool $is_connected;
    /**
     * AdminNotice constructor.
     *
     * @param string $message_text The message text.
     * @param bool   $is_send_only_country Determines if current WC country is a send only country.
     * @param bool   $is_ppcp_settings_page Determines if current page is ppcp settings page.
     * @param bool   $is_wc_gateways_list_page Determines if current page is ppcp gateway list page.
     * @param bool   $is_connected Whether onboarding was completed.
     */
    public function __construct(string $message_text, bool $is_send_only_country, bool $is_ppcp_settings_page, bool $is_wc_gateways_list_page, bool $is_connected)
    {
        $this->message_text = $message_text;
        $this->is_send_only_country = $is_send_only_country;
        $this->is_ppcp_settings_page = $is_ppcp_settings_page;
        $this->is_wc_gateways_list_page = $is_wc_gateways_list_page;
        $this->is_connected = $is_connected;
    }
    /**
     * Returns the message.
     *
     * @return Message|null
     */
    public function message(): ?Message
    {
        if (!$this->is_send_only_country || !$this->is_connected || !$this->is_ppcp_page()) {
            return null;
        }
        return new Message($this->message_text, 'warning', \true, 'ppcp-notice-wrapper');
    }
    /**
     * Checks if current page is ppcp page
     *
     * @return bool
     */
    protected function is_ppcp_page(): bool
    {
        return $this->is_ppcp_settings_page || $this->is_wc_gateways_list_page;
    }
}
