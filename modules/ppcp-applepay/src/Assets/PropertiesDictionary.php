<?php

/**
 * The Apple Pay module.
 *
 * @package WooCommerce\PayPalCommerce\Applepay
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Applepay\Assets;

/**
 * Class PropertiesDictionary
 */
class PropertiesDictionary
{
    public const DISALLOWED_USER_AGENTS = array(
        'Chrome/',
        'CriOS/',
        // Chrome on iOS.
        'Firefox/',
        'OPR/',
        // Opera.
        'Edg/',
    );
    public const ALLOWED_USER_BROWSERS = array('Safari');
    public const ALLOWED_USER_DEVICES = array('Macintosh', 'iPhone', 'iPad', 'iPod');
    public const BILLING_CONTACT_INVALID = 'billing Contact Invalid';
    public const BILLING_DATA_MODE_USE_WC = 'use_wc';
    public const BILLING_DATA_MODE_USE_APPLEPAY = 'use_applepay';
    public const CREATE_ORDER_SINGLE_PROD_REQUIRED_FIELDS = array(self::WCNONCE, self::PRODUCT_ID, self::PRODUCT_QUANTITY, self::BILLING_CONTACT, self::SHIPPING_CONTACT);
    public const UPDATE_METHOD_CART_REQUIRED_FIELDS = array(self::WCNONCE, self::SHIPPING_METHOD, self::CALLER_PAGE, self::SIMPLIFIED_CONTACT);
    public const UPDATE_CONTACT_CART_REQUIRED_FIELDS = array(self::WCNONCE, self::CALLER_PAGE, self::SIMPLIFIED_CONTACT, self::NEED_SHIPPING);
    public const UPDATE_CONTACT_SINGLE_PROD_REQUIRED_FIELDS = array(self::WCNONCE, self::PRODUCT_ID, self::PRODUCT_QUANTITY, self::CALLER_PAGE, self::SIMPLIFIED_CONTACT, self::NEED_SHIPPING);
    public const UPDATE_METHOD_SINGLE_PROD_REQUIRED_FIELDS = array(self::WCNONCE, self::PRODUCT_ID, self::PRODUCT_QUANTITY, self::SHIPPING_METHOD, self::CALLER_PAGE, self::SIMPLIFIED_CONTACT);
    public const PRODUCTS = 'products';
    public const PRODUCT_ID = 'product_id';
    public const PRODUCT_QUANTITY = 'product_quantity';
    public const PRODUCT_VARIATIONS = 'product_variations';
    public const PRODUCT_EXTRA = 'product_extra';
    public const PRODUCT_BOOKING = 'product_booking';
    public const SIMPLIFIED_CONTACT = 'simplified_contact';
    public const SHIPPING_METHOD = 'shipping_method';
    public const SHIPPING_CONTACT = 'shipping_contact';
    public const SHIPPING_CONTACT_INVALID = 'shipping Contact Invalid';
    public const BILLING_CONTACT = 'billing_contact';
    public const NONCE_ACTION = 'woocommerce-process_checkout';
    public const WCNONCE = 'woocommerce-process-checkout-nonce';
    public const CREATE_ORDER_CART_REQUIRED_FIELDS = array(self::WCNONCE, self::BILLING_CONTACT, self::SHIPPING_CONTACT);
    public const CALLER_PAGE = 'caller_page';
    public const NEED_SHIPPING = 'need_shipping';
    public const UPDATE_SHIPPING_CONTACT = 'ppcp_update_shipping_contact';
    public const UPDATE_SHIPPING_METHOD = 'ppcp_update_shipping_method';
    public const CREATE_ORDER = 'ppcp_create_order';
    public const VALIDATE = 'ppcp_validate';
    private const VALID_COLORS = array('white', 'black', 'white-outline');
    private const VALID_TYPES = array('book', 'buy', 'check-out', 'donate', 'order', 'pay', 'plain', 'subscribe', 'add-money', 'continue', 'contribute', 'reload', 'rent', 'setup', 'support', 'tip', 'top-up');
    private const VALID_LANGUAGES = array('', 'ar-AB', 'ca-ES', 'cs-CZ', 'da-DK', 'de-DE', 'el-GR', 'en-AU', 'en-GB', 'en-US', 'es-ES', 'es-MX', 'fi-FI', 'fr-CA', 'fr-FR', 'he-IL', 'hi-IN', 'hr-HR', 'hu-HU', 'id-ID', 'it-IT', 'ja-JP', 'ko-KR', 'ms-MY', 'nb-NO', 'nl-NL', 'pl-PL', 'pt-BR', 'pt-PT', 'ro-RO', 'ru-RU', 'sk-SK', 'sv-SE', 'th-TH', 'tr-TR', 'uk-UA', 'vi-VN', 'zh-CN', 'zh-HK', 'zh-TW');
    /**
     * Maps a color value from the React settings UI's "Styling" tab to a color-key
     * that is supported by the Apple Pay button.
     */
    public static function map_color(string $color): string
    {
        if (in_array($color, self::VALID_COLORS, \true)) {
            return $color;
        }
        switch ($color) {
            case 'silver':
                return 'white';
            case 'gold':
            case 'blue':
            default:
                return 'black';
        }
    }
    /**
     * Maps the "label" string from the React settings UI's "Styling" tab to a button type
     * that is supported by the Apple Pay button.
     */
    public static function map_type(string $label): string
    {
        if (in_array($label, self::VALID_TYPES, \true)) {
            return $label;
        }
        switch ($label) {
            case 'checkout':
                return 'check-out';
            case 'buynow':
                return 'buy';
            case 'paypal':
            default:
                return 'plain';
        }
    }
    /**
     * Translates the plugin's "language" setting to a valid language identifier that
     * the Apple Pay button understands.
     */
    public static function map_language(string $language): string
    {
        $language = str_replace('_', '-', $language);
        if (in_array($language, self::VALID_LANGUAGES, \true)) {
            return $language;
        }
        return '';
    }
    /**
     * Returns the possible list of billing data modes.
     *
     * @return array
     */
    public static function billing_data_modes(): array
    {
        return array(self::BILLING_DATA_MODE_USE_WC => __('Use WC checkout form data (do not show shipping address fields)', 'woocommerce-paypal-payments'), self::BILLING_DATA_MODE_USE_APPLEPAY => __('Do not use WC checkout form data (request billing and shipping addresses on Apple Pay)', 'woocommerce-paypal-payments'));
    }
}
