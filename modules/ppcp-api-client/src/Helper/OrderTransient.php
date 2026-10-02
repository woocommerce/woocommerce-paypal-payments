<?php

/**
 * PayPal order transient helper.
 *
 * This class is used to pass transient data between the PayPal order and the WooCommerce order.
 * These two orders can be created on different requests and at different times so this transient
 * data must be persisted between requests.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Helper
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\ApiClient\Helper;

use WC_Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
/**
 * Class OrderTransient
 */
class OrderTransient
{
    const CACHE_KEY = 'order_transient';
    const CACHE_TIMEOUT = 60 * 60 * 24;
    // DAY_IN_SECONDS, if necessary we can increase this.
    /**
     * The Cache.
     *
     * @var Cache
     */
    private $cache;
    /**
     * The purchase unit sanitizer.
     *
     * @var PurchaseUnitSanitizer
     */
    private $purchase_unit_sanitizer;
    /**
     * OrderTransient constructor.
     *
     * @param Cache                 $cache The Cache.
     * @param PurchaseUnitSanitizer $purchase_unit_sanitizer The purchase unit sanitizer.
     */
    public function __construct(\WooCommerce\PayPalCommerce\ApiClient\Helper\Cache $cache, \WooCommerce\PayPalCommerce\ApiClient\Helper\PurchaseUnitSanitizer $purchase_unit_sanitizer)
    {
        $this->cache = $cache;
        $this->purchase_unit_sanitizer = $purchase_unit_sanitizer;
    }
    /**
     * Processes the created PayPal order.
     *
     * @param Order $order The PayPal order.
     * @return void
     */
    public function on_order_created(Order $order): void
    {
        $message = $this->purchase_unit_sanitizer->get_last_message();
        $this->add_order_note($order, $message);
    }
    /**
     * Processes the created WooCommerce order.
     *
     * @param WC_Order $wc_order The WooCommerce order.
     * @param Order    $order The PayPal order.
     * @return void
     */
    public function on_woocommerce_order_created(WC_Order $wc_order, Order $order): void
    {
        $cache_key = $this->cache_key($order);
        if (!$cache_key) {
            return;
        }
        $this->apply_order_notes($order, $wc_order);
        $this->cache->delete($cache_key);
    }
    /**
     * Adds an order note associated with a PayPal order.
     * It can be added to a WooCommerce order associated with this PayPal order in the future.
     *
     * @param Order  $order The PayPal order.
     * @param string $message The message to be added to order notes.
     * @return void
     */
    private function add_order_note(Order $order, string $message): void
    {
        if (!$message) {
            return;
        }
        $cache_key = $this->cache_key($order);
        if (!$cache_key) {
            return;
        }
        $transient = $this->cache->get($cache_key);
        if (!is_array($transient)) {
            $transient = array();
        }
        if (!is_array($transient['notes'] ?? null)) {
            $transient['notes'] = array();
        }
        $transient['notes'][] = $message;
        $this->cache->set($cache_key, $transient, self::CACHE_TIMEOUT);
    }
    /**
     * Adds an order note associated with a PayPal order.
     * It can be added to a WooCommerce order associated with this PayPal order in the future.
     *
     * @param Order    $order The PayPal order.
     * @param WC_Order $wc_order The WooCommerce order.
     * @return void
     */
    private function apply_order_notes(Order $order, WC_Order $wc_order): void
    {
        $cache_key = $this->cache_key($order);
        if (!$cache_key) {
            return;
        }
        $transient = $this->cache->get($cache_key);
        if (!is_array($transient)) {
            return;
        }
        if (!is_array($transient['notes'])) {
            return;
        }
        foreach ($transient['notes'] as $note) {
            if (!is_string($note)) {
                continue;
            }
            $wc_order->add_order_note($note);
        }
    }
    /**
     * Build cache key.
     *
     * @param Order $order The PayPal order.
     * @return string|null
     */
    private function cache_key(Order $order): ?string
    {
        if (!$order->id()) {
            return null;
        }
        return implode('_', array(self::CACHE_KEY . $order->id()));
    }
}
