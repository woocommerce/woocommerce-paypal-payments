<?php

/**
 * PayPal Cart Response.
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\Response
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Response;

use WC_Order;
use WooCommerce\PayPalCommerce\StoreSync\StoreData\StorePayPalCart;
use WooCommerce\PayPalCommerce\StoreSync\Validation\ValidationIssue;
class CartResponse
{
    private const ALLOWED_STATUS = array('CREATED', 'INCOMPLETE', 'READY', 'COMPLETED');
    private const ALLOWED_VALIDATION_STATUS = array('VALID', 'INVALID', 'REQUIRES_ADDITIONAL_INFORMATION');
    private StorePayPalCart $store_cart;
    private array $applied_coupons = array();
    private array $shipping_options = array();
    /**
     * The cart ID used by the API to reference to an existing cart.
     */
    private string $cart_id;
    /**
     * Used to track cart lifecycle.
     * Possible values: CREATED, INCOMPLETE, READY, COMPLETED
     */
    private string $status = 'INCOMPLETE';
    /**
     * Used to determine the next step.
     * Possible values: VALID, INVALID, REQUIRES_ADDITIONAL_INFORMATION
     */
    private string $validation_status = 'INVALID';
    /**
     * The WooCommerce order created during checkout, only set for completed carts.
     */
    private ?WC_Order $wc_order = null;
    /**
     * @param StorePayPalCart $store_cart The enriched cart.
     * @param string          $cart_id    The cart ID.
     */
    private function __construct(StorePayPalCart $store_cart, string $cart_id = '')
    {
        $this->store_cart = $store_cart;
        $this->cart_id = $cart_id;
        if ($store_cart->validation()->is_empty()) {
            $this->validation_status = 'VALID';
        }
    }
    /**
     * Create a base cart response (status: INCOMPLETE).
     */
    public static function create(StorePayPalCart $store_cart, string $cart_id): self
    {
        return new self($store_cart, $cart_id);
    }
    /**
     * Create a new cart response (status: CREATED).
     */
    public static function create_new(StorePayPalCart $store_cart, string $cart_id): self
    {
        $instance = new self($store_cart, $cart_id);
        $instance->status = 'CREATED';
        return $instance;
    }
    /**
     * Create a completed cart response (status: COMPLETED).
     */
    public static function create_completed(StorePayPalCart $store_cart, string $cart_id, WC_Order $wc_order): self
    {
        $instance = new self($store_cart, $cart_id);
        $instance->status = 'COMPLETED';
        $instance->wc_order = $wc_order;
        return $instance;
    }
    /**
     * Configures the CartResponse instance - only used by the ResponseFactory.
     *
     * @param null|array $coupons Applied coupons data.
     * @return $this
     */
    public function applied_coupons(?array $coupons): self
    {
        if (null !== $coupons) {
            $this->applied_coupons = $coupons;
        }
        return $this;
    }
    /**
     * Configures the CartResponse instance - only used by the ResponseFactory.
     *
     * @param null|array $options Available shipping options.
     * @return $this
     */
    public function shipping_options(?array $options): self
    {
        if (null !== $options) {
            $this->shipping_options = $options;
        }
        return $this;
    }
    // === API RESPONSE FORMAT ===
    /**
     * Convert to array for API response.
     *
     * @return array The response array.
     */
    public function to_array(): array
    {
        $data = array('id' => $this->cart_id, 'status' => $this->get_status(), 'validation_status' => $this->get_validation_status(), 'validation_issues' => $this->store_cart->get_validation_issues(), 'items' => $this->store_cart->get_items(), 'customer' => $this->store_cart->get_customer(), 'shipping_address' => $this->store_cart->get_shipping_address(), 'billing_address' => $this->store_cart->get_billing_address(), 'available_shipping_options' => $this->get_available_shipping_options(), 'totals' => $this->store_cart->get_totals(), 'payment_method' => $this->store_cart->get_payment_method(), 'applied_coupons' => $this->get_applied_coupons(), 'payment_confirmation' => $this->get_payment_confirmation());
        // Strip items with `null` value from the response.
        return array_filter($data, static fn($v) => $v !== null);
    }
    private function get_status(): string
    {
        if (in_array($this->status, self::ALLOWED_STATUS, \true)) {
            return $this->status;
        }
        return 'INCOMPLETE';
    }
    private function get_validation_status(): string
    {
        if (in_array($this->validation_status, self::ALLOWED_VALIDATION_STATUS, \true)) {
            return $this->validation_status;
        }
        return 'INVALID';
    }
    private function get_applied_coupons(): ?array
    {
        if (empty($this->applied_coupons)) {
            return null;
        }
        return $this->applied_coupons;
    }
    private function get_available_shipping_options(): ?array
    {
        if (empty($this->shipping_options)) {
            return null;
        }
        return $this->shipping_options;
    }
    private function get_payment_confirmation(): ?array
    {
        $wc_order = $this->wc_order;
        if (!$wc_order) {
            return null;
        }
        return array('merchant_order_number' => $wc_order->get_id(), 'order_review_page' => $wc_order->get_checkout_order_received_url());
    }
    // === END OF API RESPONSE FORMAT ===
}
