<?php

/**
 * Properties of the GooglePay module.
 *
 * @package WooCommerce\PayPalCommerce\Googlepay\Helper
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Googlepay\Helper;

/**
 * Class Button
 */
class PropertiesDictionary
{
    /**
     * Returns the possible list of button colors.
     *
     * @return array
     */
    public static function button_colors(): array
    {
        return array('white' => __('White', 'woocommerce-paypal-payments'), 'black' => __('Black', 'woocommerce-paypal-payments'));
    }
    /**
     * Returns the possible list of button types.
     *
     * @return array
     */
    public static function button_types(): array
    {
        return array('book' => __('Book', 'woocommerce-paypal-payments'), 'buy' => __('Buy', 'woocommerce-paypal-payments'), 'checkout' => __('Checkout', 'woocommerce-paypal-payments'), 'donate' => __('Donate', 'woocommerce-paypal-payments'), 'order' => __('Order', 'woocommerce-paypal-payments'), 'pay' => __('Pay', 'woocommerce-paypal-payments'), 'plain' => __('Plain', 'woocommerce-paypal-payments'), 'subscribe' => __('Subscribe', 'woocommerce-paypal-payments'));
    }
    /**
     * Returns the possible list of button languages.
     *
     * @return array
     */
    public static function button_languages(): array
    {
        return array('' => __('Browser language', 'woocommerce-paypal-payments'), 'ar' => __('Arabic', 'woocommerce-paypal-payments'), 'bg' => __('Bulgarian', 'woocommerce-paypal-payments'), 'ca' => __('Catalan', 'woocommerce-paypal-payments'), 'zh' => __('Chinese', 'woocommerce-paypal-payments'), 'hr' => __('Croatian', 'woocommerce-paypal-payments'), 'cs' => __('Czech', 'woocommerce-paypal-payments'), 'da' => __('Danish', 'woocommerce-paypal-payments'), 'nl' => __('Dutch', 'woocommerce-paypal-payments'), 'en' => __('English', 'woocommerce-paypal-payments'), 'et' => __('Estonian', 'woocommerce-paypal-payments'), 'fi' => __('Finnish', 'woocommerce-paypal-payments'), 'fr' => __('French', 'woocommerce-paypal-payments'), 'de' => __('German', 'woocommerce-paypal-payments'), 'el' => __('Greek', 'woocommerce-paypal-payments'), 'id' => __('Indonesian', 'woocommerce-paypal-payments'), 'it' => __('Italian', 'woocommerce-paypal-payments'), 'ja' => __('Japanese', 'woocommerce-paypal-payments'), 'ko' => __('Korean', 'woocommerce-paypal-payments'), 'ms' => __('Malay', 'woocommerce-paypal-payments'), 'no' => __('Norwegian', 'woocommerce-paypal-payments'), 'pl' => __('Polish', 'woocommerce-paypal-payments'), 'pt' => __('Portuguese', 'woocommerce-paypal-payments'), 'ru' => __('Russian', 'woocommerce-paypal-payments'), 'sr' => __('Serbian', 'woocommerce-paypal-payments'), 'sk' => __('Slovak', 'woocommerce-paypal-payments'), 'sl' => __('Slovenian', 'woocommerce-paypal-payments'), 'es' => __('Spanish', 'woocommerce-paypal-payments'), 'sv' => __('Swedish', 'woocommerce-paypal-payments'), 'th' => __('Thai', 'woocommerce-paypal-payments'), 'tr' => __('Turkish', 'woocommerce-paypal-payments'), 'uk' => __('Ukrainian', 'woocommerce-paypal-payments'));
    }
}
