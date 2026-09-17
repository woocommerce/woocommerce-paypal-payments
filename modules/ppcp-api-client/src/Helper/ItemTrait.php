<?php

/**
 * PayPal item helper.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Helper
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\ApiClient\Helper;

trait ItemTrait
{
    /**
     * Cleans up item strings (title and description for example) and prepares them for sending to PayPal.
     *
     * @param string $string Item string.
     * @return string
     */
    protected function prepare_item_string(string $string): string
    {
        $string = strip_shortcodes(wp_strip_all_tags($string));
        return substr($string, 0, 127) ?: '';
    }
    /**
     * Prepares the sku for sending to PayPal.
     *
     * @param string $sku Item sku.
     * @return string
     */
    protected function prepare_sku(string $sku): string
    {
        return substr(wp_strip_all_tags($sku), 0, 127) ?: '';
    }
}
