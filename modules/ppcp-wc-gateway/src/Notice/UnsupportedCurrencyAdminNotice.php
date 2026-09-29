<?php

/**
 * Registers the admin message about unsupported currency set in WC shop settings.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Notice
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\WcGateway\Notice;

use WooCommerce\PayPalCommerce\AdminNotices\Entity\Message;
use WooCommerce\PayPalCommerce\ApiClient\Helper\CurrencyGetter;
/**
 * Class UnsupportedCurrencyAdminNotice
 */
class UnsupportedCurrencyAdminNotice
{
    /**
     * Whether the merchant completed onboarding.
     *
     * @var bool
     */
    private bool $is_connected;
    /**
     * The supported currencies.
     *
     * @var array
     */
    private $supported_currencies;
    /**
     * The shop currency.
     *
     * @var CurrencyGetter
     */
    private CurrencyGetter $shop_currency;
    /**
     * Indicates if we're on the WooCommerce gateways list page.
     *
     * @var bool
     */
    private $is_wc_gateways_list_page;
    /**
     * Indicates if we're on a PPCP Settings page.
     *
     * @var bool
     */
    private $is_ppcp_settings_page;
    /**
     * UnsupportedCurrencyAdminNotice constructor.
     *
     * @param bool           $is_connected Whether the merchant completed onboarding.
     * @param CurrencyGetter $shop_currency The shop currency.
     * @param array          $supported_currencies The supported currencies.
     * @param bool           $is_wc_gateways_list_page Indicates if we're on the WooCommerce gateways list page.
     * @param bool           $is_ppcp_settings_page Indicates if we're on a PPCP Settings page.
     */
    public function __construct(bool $is_connected, CurrencyGetter $shop_currency, array $supported_currencies, bool $is_wc_gateways_list_page, bool $is_ppcp_settings_page)
    {
        $this->is_connected = $is_connected;
        $this->shop_currency = $shop_currency;
        $this->supported_currencies = $supported_currencies;
        $this->is_wc_gateways_list_page = $is_wc_gateways_list_page;
        $this->is_ppcp_settings_page = $is_ppcp_settings_page;
    }
    /**
     * Returns the message.
     *
     * @return Message|null
     */
    public function unsupported_currency_message()
    {
        if (!$this->should_display()) {
            return null;
        }
        $paypal_currency_support_url = 'https://developer.paypal.com/api/rest/reference/currency-codes/';
        $message = sprintf(
            /* translators: %1$s the shop currency, %2$s the PayPal currency support page link opening HTML tag, %3$s the link ending HTML tag. */
            __('Attention: Your current WooCommerce store currency (%1$s) is not supported by PayPal. Please update your store currency to one that is supported by PayPal to ensure smooth transactions. Visit the %2$sPayPal currency support page%3$s for more information on supported currencies.', 'woocommerce-paypal-payments'),
            $this->shop_currency->get(),
            '<a href="' . esc_url($paypal_currency_support_url) . '">',
            '</a>'
        );
        return new Message($message, 'warning', \true, 'ppcp-notice-wrapper');
    }
    /**
     * Whether the message should display.
     *
     * @return bool
     */
    protected function should_display(): bool
    {
        return $this->is_connected && !$this->currency_supported() && ($this->is_wc_gateways_list_page || $this->is_ppcp_settings_page);
    }
    /**
     * Whether the currency is supported by PayPal.
     *
     * @return bool
     */
    private function currency_supported(): bool
    {
        $currency = $this->shop_currency->get();
        $supported_currencies = $this->supported_currencies;
        return in_array($currency, $supported_currencies, \true);
    }
}
