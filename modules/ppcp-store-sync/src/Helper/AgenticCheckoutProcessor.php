<?php

/**
 * Responsibility: WooCommerce Order creation
 *
 * Process the final checkout, turning an agentic cart into a paid WooCommerce order.
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\Helper
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Helper;

use WC_Order;
use Exception;
use WP_Error;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order as PayPalOrder;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Shipping;
use WooCommerce\PayPalCommerce\ApiClient\Factory\ShippingFactory;
use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\Button\Session\CartData;
use WooCommerce\PayPalCommerce\Button\Helper\WooCommerceOrderCreator;
use WooCommerce\PayPalCommerce\StoreSync\CartValidation\CouponValidator\AppliedCouponsBuilder;
use WooCommerce\PayPalCommerce\StoreSync\Schema\Address;
use WooCommerce\PayPalCommerce\StoreSync\Schema\PayPalCart;
use WooCommerce\PayPalCommerce\StoreSync\Schema\PaymentMethod;
use WooCommerce\PayPalCommerce\StoreSync\Schema\ShippingOption;
use WooCommerce\PayPalCommerce\StoreSync\StoreData\StorePayPalCart;
/**
 * Orchestrates the complete checkout workflow for Agentic Commerce.
 *
 * This service coordinates the following steps:
 * - Fetches PayPal order
 * - Syncs PayPal order with final cart totals
 * - Translates PayPalCart to CartData
 * - Creates WooCommerce order
 * - Links PayPal and WC orders
 * - Captures payment
 */
class AgenticCheckoutProcessor
{
    private \WooCommerce\PayPalCommerce\StoreSync\Helper\PayPalOrderManager $order_manager;
    private WooCommerceOrderCreator $wc_order_creator;
    private \WooCommerce\PayPalCommerce\StoreSync\Helper\AgenticCartBuilder $cart_builder;
    private AppliedCouponsBuilder $applied_coupons_builder;
    private ShippingFactory $shipping_factory;
    private LoggerInterface $logger;
    public function __construct(\WooCommerce\PayPalCommerce\StoreSync\Helper\PayPalOrderManager $order_manager, WooCommerceOrderCreator $wc_order_creator, \WooCommerce\PayPalCommerce\StoreSync\Helper\AgenticCartBuilder $cart_builder, AppliedCouponsBuilder $applied_coupons_builder, ShippingFactory $shipping_factory, LoggerInterface $logger)
    {
        $this->order_manager = $order_manager;
        $this->wc_order_creator = $wc_order_creator;
        $this->cart_builder = $cart_builder;
        $this->applied_coupons_builder = $applied_coupons_builder;
        $this->shipping_factory = $shipping_factory;
        $this->logger = $logger;
    }
    /**
     * Process agentic checkout: translate cart, create order, capture payment.
     *
     * This orchestrates the complete checkout workflow:
     * 1. Fetches the PayPal Order using the token (order ID)
     * 2. Syncs PayPal order with final cart totals
     * 3. Translates PayPalCart to CartData
     * 4. Creates WooCommerce order from PayPal Order and CartData
     * 5. Links PayPal order with WC order ID
     * 6. Captures the PayPal payment
     *
     * @return WC_Order|WP_Error The created order or error.
     */
    public function process(StorePayPalCart $store_cart, PaymentMethod $payment_method, string $paypal_order_id)
    {
        $cart = $store_cart->paypal_cart();
        $this->logger->info('[CHECKOUT] Starting checkout', array('order_id' => $paypal_order_id, 'item_count' => count($cart->items())));
        try {
            $paypal_order = $this->order_manager->fetch_order($paypal_order_id);
            $this->logger->info('[CHECKOUT] PayPal order fetched', array('order_id' => $paypal_order_id, 'status' => $paypal_order->status()->name()));
            $total_discount = $this->applied_coupons_builder->calculate_total_discount($store_cart);
            $this->order_manager->update_order($paypal_order_id, $cart, $total_discount);
            $this->logger->info('[CHECKOUT] PayPal order synced with final cart totals', array('order_id' => $paypal_order_id, 'discount' => $total_discount));
            $wc_cart = $this->cart_builder->paypal_cart_to_wc_cart($cart);
            if (is_wp_error($wc_cart)) {
                $this->logger->warning('[CHECKOUT] Failed to build WC_Cart from PayPal cart', array('order_id' => $paypal_order_id, 'error' => $wc_cart->get_error_message()));
                return $wc_cart;
            }
            $cart_data = $this->cart_builder->wc_cart_to_card_data($wc_cart);
            $this->logger->info('[CHECKOUT] Creating WooCommerce order', array('order_id' => $paypal_order_id));
            $wc_order = $this->create_order($paypal_order, $cart_data, $cart, $payment_method, $paypal_order_id);
            if (is_wp_error($wc_order)) {
                $this->logger->warning('[CHECKOUT] WooCommerce order creation failed', array('order_id' => $paypal_order_id, 'error' => $wc_order->get_error_message()));
                return $wc_order;
            }
            $this->logger->info('[CHECKOUT] WooCommerce order created', array('order_id' => $paypal_order_id, 'wc_order_id' => $wc_order->get_id()));
            $this->link_orders($paypal_order_id, $wc_order);
            $this->capture_payment($paypal_order, $wc_order, $paypal_order_id);
            return $wc_order;
        } catch (Exception $e) {
            $this->logger->error('[CHECKOUT] Checkout failed with exception', array('order_id' => $paypal_order_id, 'error' => $e->getMessage()));
            return new WP_Error('order_creation_failed', $e->getMessage());
        }
    }
    /**
     * Create WooCommerce order from PayPal order and cart data.
     *
     * @param PayPalOrder   $paypal_order    The PayPal order object.
     * @param CartData      $cart_data       The cart data.
     * @param PayPalCart    $cart            The PayPal cart with customer data.
     * @param PaymentMethod $payment_method  The payment method data.
     * @param string        $paypal_order_id The PayPal order ID.
     * @return WC_Order|WP_Error The created order or error.
     */
    private function create_order(PayPalOrder $paypal_order, $cart_data, PayPalCart $cart, PaymentMethod $payment_method, string $paypal_order_id)
    {
        // Build PayPal-specific data for order creation.
        $paypal_data = array('payment_method' => array('token' => $payment_method->token(), 'payer_id' => $payment_method->payer_id()));
        // Add payer information.
        $payer_data = $this->build_payer_data($cart);
        if (!empty($payer_data)) {
            $paypal_data['payer'] = $payer_data;
        }
        $shipping_data = $this->build_shipping_data($cart);
        if (!empty($shipping_data)) {
            $paypal_data['shipping_address'] = $shipping_data;
        }
        $provide_shipping_data = fn(): ?Shipping => $this->build_shipping_from_paypal_cart($cart);
        try {
            add_filter('woocommerce_paypal_payments_order_creator_get_shipping', $provide_shipping_data);
            $wc_order = $this->wc_order_creator->create_from_paypal_order($paypal_order, $cart_data, $paypal_data);
        } finally {
            remove_filter('woocommerce_paypal_payments_order_creator_get_shipping', $provide_shipping_data);
        }
        // Mark as agentic commerce order with metadata.
        $wc_order->update_meta_data('_paypal_order_id', $paypal_order_id);
        $wc_order->update_meta_data('_agentic_commerce', '1');
        $wc_order->set_status('on-hold', 'Awaiting PayPal payment capture.');
        $wc_order->save();
        return $wc_order;
    }
    /**
     * Build a synthetic Shipping entity from the cart's selected shipping option.
     *
     * Returns null when no option is selected or the cart has no options, allowing
     * WooCommerceOrderCreator to fall back to its default behaviour.
     *
     * @param PayPalCart $cart The PayPal cart.
     * @return Shipping|null The synthetic shipping entity, or null.
     */
    private function build_shipping_from_paypal_cart(PayPalCart $cart): ?Shipping
    {
        $options = $cart->available_shipping_options();
        if (empty($options)) {
            return null;
        }
        $selected = null;
        $option_price = null;
        foreach ($options as $option) {
            if ($option instanceof ShippingOption && $option->is_selected()) {
                $selected = $option;
                $option_price = $option->price();
                break;
            }
        }
        if (null === $selected || null === $option_price) {
            return null;
        }
        $option_data = (object) array('id' => $selected->id(), 'label' => $selected->name(), 'type' => 'SHIPPING', 'selected' => \true, 'amount' => (object) array('currency_code' => $option_price->currency_code(), 'value' => (string) $option_price->value()));
        $customer = $cart->customer();
        $full_name = $customer ? $customer->full_name() : '';
        $data = (object) array('name' => (object) array('full_name' => $full_name), 'address' => (object) $cart->shipping_address()->to_array(), 'options' => array($option_data));
        return $this->shipping_factory->from_paypal_response($data);
    }
    /**
     * Build payer data from PayPal cart.
     *
     * @param PayPalCart $cart The PayPal cart.
     * @return array Payer data array.
     */
    private function build_payer_data(PayPalCart $cart): array
    {
        if (!$cart->customer() && !$cart->billing_address()) {
            return array();
        }
        $payer_data = array();
        $customer = $cart->customer();
        if ($customer) {
            $customer_name = $customer->name();
            if ($customer_name) {
                $payer_data['name'] = $customer_name->to_array();
            }
            if ($customer->email_address()) {
                $payer_data['email_address'] = $customer->email_address();
            }
        }
        if ($cart->billing_address()) {
            $payer_data['address'] = $cart->billing_address()->to_array();
        }
        return $payer_data;
    }
    /**
     * Build shipping data from PayPalCart.
     *
     * @param PayPalCart $cart The PayPal cart.
     * @return array Shipping data array.
     */
    private function build_shipping_data(PayPalCart $cart): array
    {
        if ($cart->shipping_address()->is_empty()) {
            return array();
        }
        $customer = $cart->customer();
        $full_name = $customer ? $customer->full_name() : '';
        return array('name' => array('full_name' => $full_name), 'address' => $cart->shipping_address()->to_array());
    }
    /**
     * Link PayPal order with WooCommerce order ID.
     *
     * Delegates to PayPalOrderManager for the PATCH operation.
     *
     * @param string   $paypal_order_id The PayPal order ID.
     * @param WC_Order $wc_order        The WooCommerce order.
     * @return void
     */
    private function link_orders(string $paypal_order_id, WC_Order $wc_order): void
    {
        $this->order_manager->link_wc_order($paypal_order_id, $wc_order->get_id());
    }
    /**
     * Capture PayPal payment and update WC order.
     *
     * Delegates to PayPalOrderManager for the capture operation.
     *
     * @param PayPalOrder $paypal_order    The PayPal order object (unused, kept for signature
     *                                     compatibility).
     * @param WC_Order    $wc_order        The WooCommerce order.
     * @param string      $paypal_order_id The PayPal order ID.
     * @return void
     */
    private function capture_payment(PayPalOrder $paypal_order, WC_Order $wc_order, string $paypal_order_id): void
    {
        $this->logger->info('[CHECKOUT] Capturing PayPal payment', array('order_id' => $paypal_order_id, 'wc_order_id' => $wc_order->get_id()));
        $capture_result = $this->order_manager->capture_order($paypal_order_id);
        if ($capture_result) {
            $transaction_id = $capture_result['transaction_id'] ?? $paypal_order_id;
            $this->logger->info('[CHECKOUT] Payment captured successfully', array('order_id' => $paypal_order_id, 'wc_order_id' => $wc_order->get_id(), 'transaction_id' => $transaction_id));
            $wc_order->payment_complete($paypal_order_id);
            $wc_order->add_order_note(sprintf(
                /* translators: %s: PayPal transaction ID */
                __('PayPal payment captured. Transaction ID: %s', 'woocommerce-paypal-payments'),
                $transaction_id
            ));
            $wc_order->save();
        } else {
            $this->logger->warning('[CHECKOUT] Capture returned null — payment may require manual action or webhook', array('order_id' => $paypal_order_id, 'wc_order_id' => $wc_order->get_id()));
        }
    }
}
