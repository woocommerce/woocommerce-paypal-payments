<?php

/**
 * Properties of the GooglePay module.
 *
 * @see     https://developers.google.com/pay/api/web/guides/resources/customize
 * @package WooCommerce\PayPalCommerce\Googlepay\Helper
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Googlepay\Helper;

/**
 * Class Button
 */
class PropertiesDictionary
{
    private const VALID_COLORS = array('', 'black', 'white');
    private const VALID_TYPES = array('book', 'buy', 'checkout', 'donate', 'order', 'pay', 'plain', 'subscribe');
    private const VALID_LANGUAGES = array('', 'ar', 'bg', 'ca', 'zh', 'hr', 'cs', 'da', 'nl', 'en', 'et', 'fi', 'fr', 'de', 'el', 'id', 'it', 'ja', 'ko', 'ms', 'no', 'pl', 'pt', 'ru', 'sr', 'sk', 'sl', 'es', 'sv', 'th', 'tr', 'uk');
    /**
     * Maps a color value from the React settings UI's "Styling" tab to a color-key
     * that is supported by Google button.
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
     * that is supported by Google button.
     */
    public static function map_type(string $label): string
    {
        if (in_array($label, self::VALID_TYPES, \true)) {
            return $label;
        }
        switch ($label) {
            case 'buynow':
                return 'buy';
            case 'paypal':
            default:
                return 'plain';
        }
    }
    /**
     * Translates the plugin's "language" setting to a valid language identifier that
     * the Google button understands.
     */
    public static function map_language(string $language): string
    {
        if (strlen($language) > 2) {
            $language = substr($language, 0, 2);
        }
        if (in_array($language, self::VALID_LANGUAGES, \true)) {
            return $language;
        }
        return '';
    }
}
