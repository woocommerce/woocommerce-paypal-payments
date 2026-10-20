<?php

/**
 * Responsibility: PayPal Order API
 *
 * Unified interface for PayPal Order lifecycle management (create, update).
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\Helper
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Helper;

use RuntimeException;
use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\Orders;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order as WooOrder;
use WooCommerce\PayPalCommerce\ApiClient\Entity\ExperienceContext;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Patch;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PatchCollection;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\ApiClient\Factory\AmountFactory;
use WooCommerce\PayPalCommerce\StoreSync\Config\StoreCurrencyValue;
use WooCommerce\PayPalCommerce\StoreSync\Schema\Money;
use WooCommerce\PayPalCommerce\StoreSync\Schema\PayPalCart;
use WC_Cart;
class PayPalOrderManager
{
    private OrderEndpoint $order_endpoint;
    private Orders $orders_api;
    private \WooCommerce\PayPalCommerce\StoreSync\Helper\AgenticCartBuilder $cart_builder;
    private LoggerInterface $logger;
    private StoreCurrencyValue $store_currency;
    private AmountFactory $amount_factory;
    public function __construct(OrderEndpoint $order_endpoint, Orders $orders_api, \WooCommerce\PayPalCommerce\StoreSync\Helper\AgenticCartBuilder $cart_builder, LoggerInterface $logger, StoreCurrencyValue $store_currency, AmountFactory $amount_factory)
    {
        $this->order_endpoint = $order_endpoint;
        $this->orders_api = $orders_api;
        $this->cart_builder = $cart_builder;
        $this->logger = $logger;
        $this->store_currency = $store_currency;
        $this->amount_factory = $amount_factory;
    }
    /**
     * Create a new PayPal Order from cart WITHOUT creating a WooCommerce order.
     *
     * This follows the agentic commerce pattern where:
     * 1. CreateCart: Creates PayPal order + stores cart in session (NO WC order)
     * 2. Checkout: Creates WC order + captures payment
     *
     * @param PayPalCart $cart The cart.
     * @return string The PayPal Order ID (ec_token) or an empty string.
     */
    public function create_order(PayPalCart $cart): string
    {
        $this->logger->info('[ORDER] Creating PayPal Order', array('item_count' => count($cart->items()), 'cart' => $cart->to_array()));
        $wc_cart = $this->cart_builder->paypal_cart_to_wc_cart($cart);
        if (is_wp_error($wc_cart)) {
            $this->logger->error('[ORDER] PayPal order creation aborted due to invalid cart data.', $wc_cart->get_all_error_data());
            return '';
        }
        // At this stage, the order intent is always AUTHORIZE, not CAPTURE.
        $set_order_intent = static fn(): string => 'AUTHORIZE';
        $purchase_unit = $this->cart_builder->wc_cart_to_purchase_unit($wc_cart);
        $paypal_order = null;
        try {
            add_filter('woocommerce_paypal_payments_order_intent', $set_order_intent);
            // Create PayPal Order (application_context filter is registered in StoreSyncModule).
            $paypal_order = $this->order_endpoint->create(array($purchase_unit), ExperienceContext::SHIPPING_PREFERENCE_NO_SHIPPING);
        } catch (PayPalApiException $error) {
            $details = $error->details();
            $this->logger->error('[ORDER] PayPal order creation failed', array('error' => reset($details), 'item_count' => count($cart->items())));
        } catch (RuntimeException $error) {
            $this->logger->error('[ORDER] PayPal API request failed', array('error' => $error->getMessage(), 'item_count' => count($cart->items())));
        } finally {
            remove_filter('woocommerce_paypal_payments_order_intent', $set_order_intent);
        }
        if (!$paypal_order) {
            return '';
        }
        $order_id = $paypal_order->id();
        $this->logger->info('[ORDER] PayPal Order created successfully', array('order_id' => $order_id, 'item_count' => count($cart->items())));
        return $order_id;
    }
    /**
     * Update an existing PayPal Order with new cart data via PATCH API.
     *
     * When cart items change, we need to update both the items array AND the amount breakdown.
     * PayPal validates that item_total equals sum(unit_amount * quantity) for all items.
     *
     * @param string     $order_id The PayPal Order ID.
     * @param PayPalCart $cart     The updated cart.
     * @param float      $discount The total discount amount from applied coupons.
     * @throws RuntimeException If the update fails.
     */
    public function update_order(string $order_id, PayPalCart $cart, float $discount = 0.0): void
    {
        $wc_cart = $this->cart_builder->paypal_cart_to_wc_cart($cart);
        if (is_wp_error($wc_cart)) {
            $this->logger->warning('[ORDER] Cannot update PayPal Order: failed to build WC_Cart', array('order_id' => $order_id, 'error' => $wc_cart->get_error_message()));
            return;
        }
        $amount = $this->amount_factory->from_wc_cart($wc_cart);
        $items = $this->build_items_for_patch($wc_cart);
        $this->logger->info('[ORDER] Updating PayPal Order', array('order_id' => $order_id, 'discount' => $discount, 'item_count' => count($items), 'amount' => $amount->to_array()));
        $patches = new PatchCollection(new Patch('replace', "/purchase_units/@reference_id=='default'/items", $items), new Patch('replace', "/purchase_units/@reference_id=='default'/amount", $amount->to_array()));
        try {
            $this->order_endpoint->patch($order_id, $patches);
            $this->logger->info('[ORDER] PayPal Order updated successfully', array('order_id' => $order_id, 'amount' => $amount->value_str(), 'item_count' => count($items)));
        } catch (RuntimeException $error) {
            $this->logger->error('[ORDER] PayPal Order update failed', array('order_id' => $order_id, 'error' => $error->getMessage(), 'amount' => $amount->to_array()));
            throw $error;
        }
    }
    /**
     * Build items array for PayPal Order PATCH operation.
     *
     * Prices are always taken from the WooCommerce store via StoreData, never from the agent
     * payload. Items whose product cannot be resolved are silently skipped.
     *
     * @param WC_Cart $wc_cart The cart.
     * @return array Items formatted for PayPal API.
     */
    private function build_items_for_patch(WC_Cart $wc_cart): array
    {
        $items = array();
        $currency = $this->store_currency->value();
        foreach ($wc_cart->get_cart() as $cart_item) {
            $product = $cart_item['data'] ?? null;
            $quantity = (int) ($cart_item['quantity'] ?? 0);
            if (!$product instanceof \WC_Product || $quantity <= 0) {
                continue;
            }
            $line_total = (float) ($cart_item['line_subtotal'] ?? 0.0);
            $unit_price = $line_total / $quantity;
            $items[] = array('name' => substr($product->get_name() ?? 'Item', 0, 127), 'quantity' => (string) $quantity, 'unit_amount' => Money::create($unit_price, $currency)->to_array());
        }
        return $items;
    }
    /**
     * Fetch a PayPal Order by ID.
     *
     * @param string $order_id The PayPal Order ID.
     * @return WooOrder The PayPal Order.
     * @throws RuntimeException If fetching fails.
     */
    public function fetch_order(string $order_id): WooOrder
    {
        $this->logger->info('[ORDER] Fetching PayPal Order', array('order_id' => $order_id));
        try {
            $paypal_order = $this->order_endpoint->order($order_id);
            $this->logger->info('[ORDER] PayPal Order fetched successfully', array('order_id' => $order_id, 'status' => $paypal_order->status()));
            return $paypal_order;
        } catch (RuntimeException $error) {
            $this->logger->error('[ORDER] Failed to fetch PayPal Order', array('order_id' => $order_id, 'error' => $error->getMessage()));
            throw $error;
        }
    }
    /**
     * Link PayPal Order with WooCommerce order ID.
     *
     * Updates the PayPal order's custom_id field with the WC order ID
     * to enable webhook matching and order correlation.
     *
     * @param string $order_id    The PayPal Order ID.
     * @param int    $wc_order_id The WooCommerce order ID.
     * @return void
     */
    public function link_wc_order(string $order_id, int $wc_order_id): void
    {
        $this->logger->info('[ORDER] Linking WooCommerce order to PayPal Order', array('order_id' => $order_id, 'wc_order_id' => $wc_order_id));
        // Intentionally not using the PatchCollection or Patch classes
        // because they expect an array as value, while this is a string.
        // todo: could be topic of a future refactoring of the Patch class.
        $patch_data = array(array('op' => 'add', 'path' => '/purchase_units/@reference_id==\'default\'/custom_id', 'value' => (string) $wc_order_id));
        try {
            $this->orders_api->patch_order($order_id, $patch_data);
            $this->logger->info('[ORDER] WooCommerce order linked successfully', array('order_id' => $order_id, 'wc_order_id' => $wc_order_id));
        } catch (RuntimeException $error) {
            $this->logger->warning('[ORDER] Failed to link WooCommerce order', array('order_id' => $order_id, 'wc_order_id' => $wc_order_id, 'error' => $error->getMessage()));
            // Don't throw: Order was created, webhook matching can still work via _paypal_order_id meta.
        }
    }
    /**
     * Capture PayPal Order payment.
     *
     * Captures the authorized payment for the order.
     *
     * @param string $order_id The PayPal Order ID.
     * @return array|null Capture the result with transaction_id, or null on failure.
     */
    public function capture_order(string $order_id): ?array
    {
        $this->logger->info('[ORDER] Capturing PayPal Order payment', array('order_id' => $order_id));
        try {
            $transaction_id = $order_id;
            $paypal_order = $this->fetch_order($order_id);
            $capture_result = $this->order_endpoint->capture($paypal_order);
            $payments = $capture_result->purchase_units()[0]->payments();
            if ($payments) {
                $transaction_id = $payments->captures()[0]->id();
            }
            $this->logger->info('[ORDER] PayPal Order payment captured successfully', array('order_id' => $order_id, 'transaction_id' => $transaction_id));
            return array('order_id' => $order_id, 'transaction_id' => $transaction_id);
        } catch (RuntimeException $error) {
            $this->logger->error('[ORDER] PayPal Order capture failed', array('order_id' => $order_id, 'error' => $error->getMessage()));
            // Return null - payment can be handled manually or via webhook.
            return null;
        }
    }
}
