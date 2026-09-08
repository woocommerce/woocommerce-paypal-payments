<?php

/**
 * Responsibility: WC_Cart
 *
 * Builds a new, session independent WC_Cart from an agentic cart.
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\Helper
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Helper;

use Throwable;
use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use WP_Error;
use WC_Cart;
use WC_Customer;
use WooCommerce;
use WooCommerce\PayPalCommerce\Button\Session\CartDataFactory;
use WooCommerce\PayPalCommerce\Button\Session\CartData;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PurchaseUnit;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PurchaseUnitFactory;
use WooCommerce\PayPalCommerce\StoreSync\Schema\PayPalCart;
use WooCommerce\PayPalCommerce\StoreSync\Schema\CartItem;
use WooCommerce\PayPalCommerce\StoreSync\Schema\Customer;
use WooCommerce\PayPalCommerce\StoreSync\Schema\Coupon;
use WooCommerce\PayPalCommerce\StoreSync\Schema\ShippingOption;
class AgenticCartBuilder
{
    private WooCommerce $wc;
    private \WooCommerce\PayPalCommerce\StoreSync\Helper\ProductManager $product_manager;
    private CartDataFactory $cart_data_factory;
    private PurchaseUnitFactory $purchase_unit_factory;
    private LoggerInterface $logger;
    public function __construct(WooCommerce $wc, \WooCommerce\PayPalCommerce\StoreSync\Helper\ProductManager $product_manager, CartDataFactory $cart_data_factory, PurchaseUnitFactory $purchase_unit_factory, LoggerInterface $logger)
    {
        $this->wc = $wc;
        $this->product_manager = $product_manager;
        $this->cart_data_factory = $cart_data_factory;
        $this->purchase_unit_factory = $purchase_unit_factory;
        $this->logger = $logger;
    }
    /**
     * This is a relatively expensive operation, the result should be cached during the request.
     *
     * @param PayPalCart $paypal_cart The agentic input cart.
     * @return WC_Cart|WP_Error Either the populated WooCommerce cart or an error.
     */
    public function paypal_cart_to_wc_cart(PayPalCart $paypal_cart)
    {
        $wc_customer = $this->wc_customer();
        $wc_cart = $this->wc_cart();
        $result = $this->add_items_to_cart($wc_cart, $paypal_cart->items());
        if (is_wp_error($result)) {
            $this->logger->warning(sprintf('[WC_CART] Failed to convert PayPalCart into WC_Cart: %s', $result->get_error_message()), $result->get_error_data());
            return $result;
        }
        $this->apply_coupons($wc_cart, $paypal_cart->coupons());
        $this->set_customer_info($wc_customer, $paypal_cart->customer());
        $this->set_addresses($wc_customer, $paypal_cart);
        $this->apply_shipping_option($paypal_cart);
        $wc_cart->calculate_totals();
        $this->logger->info('[WC_CART] Converted PayPalCart to WC_Cart', array('cart' => $wc_cart, 'customer' => $wc_customer));
        return $wc_cart;
    }
    public function wc_cart_to_card_data(WC_Cart $wc_cart): CartData
    {
        /** @psalm-suppress MissingThrowsDocblock -- no throw possible when passing in a WC_Cart. */
        return $this->cart_data_factory->from_current_cart($wc_cart);
    }
    public function wc_cart_to_purchase_unit(WC_Cart $wc_cart): PurchaseUnit
    {
        return $this->purchase_unit_factory->from_wc_cart($wc_cart, \true);
    }
    /**
     * @param WC_Cart    $wc_cart The WC_Cart to update.
     * @param CartItem[] $items   Items that should be added to the cart.
     * @return true|WP_Error True on success, WP_Error on failure.
     */
    private function add_items_to_cart(WC_Cart $wc_cart, array $items)
    {
        $is_empty = \true;
        $errors = array();
        $wc_cart->empty_cart(\false);
        foreach ($items as $item) {
            $product = $this->product_manager->find_product($item);
            if (!$product) {
                // @phpstan-ignore-next-line method.deprecated
                $variant_or_id = $item->variant_id() ?: $item->item_id();
                $errors[] = sprintf('Product not found "%s"', (string) $variant_or_id);
                continue;
            }
            $product_id = $product->get_parent_id() ?: $product->get_id();
            $variation_id = $product->is_type('variation') ? $product->get_id() : 0;
            $quantity = $item->quantity();
            $variation = array();
            if ($variation_id && is_callable(array($product, 'get_variation_attributes'))) {
                $variation = $product->get_variation_attributes();
            }
            try {
                $cart_item_key = $wc_cart->add_to_cart($product_id, $quantity, $variation_id, $variation);
                if ($cart_item_key) {
                    $is_empty = \false;
                } else {
                    $errors[] = sprintf('Failed to add "%s".', $product->get_name());
                }
            } catch (Throwable $e) {
                $errors[] = sprintf('Failed to add "%s": %s', $product->get_name(), $e->getMessage());
            }
        }
        // Only return an error if the cart is still empty.
        if ($is_empty) {
            return new WP_Error('no_valid_items', 'No valid products could be added to cart', $errors);
        }
        return \true;
    }
    private function apply_shipping_option(PayPalCart $paypal_cart): void
    {
        $options = $paypal_cart->available_shipping_options();
        if (!$options || !$this->wc->session) {
            return;
        }
        foreach ($options as $option) {
            if ($option instanceof ShippingOption && $option->is_selected()) {
                $this->wc->session->set('chosen_shipping_methods', array($option->id()));
                return;
            }
        }
    }
    /**
     * @param WC_Cart       $wc_cart The cart to apply coupons to.
     * @param Coupon[]|null $coupons Coupons provided by the agentic cart.
     */
    private function apply_coupons(WC_Cart $wc_cart, ?array $coupons): void
    {
        if (!$coupons) {
            return;
        }
        foreach ($coupons as $coupon) {
            $action = $coupon->action();
            $code = $coupon->code();
            if ($action !== 'APPLY' || !$code) {
                continue;
            }
            $wc_cart->apply_coupon($code);
        }
    }
    private function set_customer_info(WC_Customer $wc_customer, ?Customer $customer): void
    {
        if (!$customer) {
            return;
        }
        $email = $customer->email_address();
        $name = $customer->name();
        if ($email) {
            $wc_customer->set_billing_email($email);
        }
        if ($name) {
            $wc_customer->set_first_name((string) $name->given_name(''));
            $wc_customer->set_last_name((string) $name->surname(''));
        }
    }
    private function set_addresses(WC_Customer $wc_customer, PayPalCart $paypal_cart): void
    {
        $shipping = $paypal_cart->shipping_address();
        $wc_customer->set_shipping_first_name($wc_customer->get_first_name());
        $wc_customer->set_shipping_last_name($wc_customer->get_last_name());
        $wc_customer->set_shipping_address_1((string) $shipping->address_line_1(''));
        $wc_customer->set_shipping_address_2((string) $shipping->address_line_2(''));
        $wc_customer->set_shipping_city((string) $shipping->admin_area_2(''));
        $wc_customer->set_shipping_state((string) $shipping->admin_area_1(''));
        $wc_customer->set_shipping_postcode((string) $shipping->postal_code(''));
        $wc_customer->set_shipping_country((string) $shipping->country_code(''));
        $billing = $paypal_cart->billing_address();
        if ($billing) {
            $wc_customer->set_billing_first_name($wc_customer->get_first_name());
            $wc_customer->set_billing_last_name($wc_customer->get_last_name());
            $wc_customer->set_billing_address_1((string) $billing->address_line_1(''));
            $wc_customer->set_billing_address_2((string) $billing->address_line_2(''));
            $wc_customer->set_billing_city((string) $billing->admin_area_2(''));
            $wc_customer->set_billing_state((string) $billing->admin_area_1(''));
            $wc_customer->set_billing_postcode((string) $billing->postal_code(''));
            $wc_customer->set_billing_country((string) $billing->country_code(''));
        }
    }
    private function wc_cart(): WC_Cart
    {
        $wc_cart = $this->wc->cart;
        if (!$wc_cart instanceof WC_Cart) {
            $wc_cart = new WC_Cart();
            $this->wc->cart = $wc_cart;
        }
        return $wc_cart;
    }
    /**
     * Since WC_Cart has no customer property but directly links details from the global
     * WC()->customer property to the cart/order, we use the global customer here. This works well
     * in the agentic module since there is no browser session that might collide with our
     * changes.
     *
     * @return WC_Customer
     */
    private function wc_customer(): WC_Customer
    {
        $wc_customer = $this->wc->customer;
        if (!$wc_customer instanceof WC_Customer) {
            // Create an in-memory customer - note that it has "is_session" set to false.
            $wc_customer = new WC_Customer();
            $this->wc->customer = $wc_customer;
        }
        return $wc_customer;
    }
}
