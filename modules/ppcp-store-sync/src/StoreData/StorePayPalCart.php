<?php

/**
 * Enriched working object for an agentic cart request.
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\StoreData
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\StoreData;

use WC_Cart;
use WP_Error;
use WooCommerce\PayPalCommerce\StoreSync\Config\StoreCurrencyValue;
use WooCommerce\PayPalCommerce\StoreSync\Helper\AgenticCartBuilder;
use WooCommerce\PayPalCommerce\StoreSync\Schema\Money;
use WooCommerce\PayPalCommerce\StoreSync\Schema\PayPalCart;
use WooCommerce\PayPalCommerce\StoreSync\Validation\StoreValidation;
use WooCommerce\PayPalCommerce\StoreSync\Validation\ValidationIssue;
/**
 * Output contract for an agentic cart request.
 *
 * PayPalCart is the input contract — it parses and validates what the agent sends.
 * This class is the output contract — same schema, but store-authoritative values fill the
 * blanks (real WC prices, resolved products, calculated totals). A fully-populated PayPalCart
 * and its StorePayPalCart counterpart must produce a nearly identical to_array() output.
 */
class StorePayPalCart
{
    private PayPalCart $paypal_cart;
    private StoreValidation $validation;
    /** @var StoreCartItem[] */
    private array $store_items;
    private AgenticCartBuilder $cart_builder;
    private StoreCurrencyValue $store_currency;
    private bool $wc_cart_built = \false;
    private ?WC_Cart $wc_cart_cache = null;
    private string $paypal_order = '';
    public function __construct(PayPalCart $paypal_cart, StoreValidation $validation, array $store_items, AgenticCartBuilder $cart_builder, StoreCurrencyValue $store_currency)
    {
        $this->paypal_cart = $paypal_cart;
        $this->validation = $validation;
        $this->store_items = $store_items;
        $this->cart_builder = $cart_builder;
        $this->store_currency = $store_currency;
    }
    public function set_paypal_order(string $order_id): void
    {
        $this->paypal_order = $order_id;
    }
    public function paypal_cart(): PayPalCart
    {
        return $this->paypal_cart;
    }
    /**
     * @return StoreCartItem[]
     */
    public function cart_items(): array
    {
        return $this->store_items;
    }
    /**
     * Lazily builds the WC_Cart on first call and caches the result.
     * Returns null when the cart could not be built (e.g. no valid products).
     */
    public function wc_cart(): ?WC_Cart
    {
        if (!$this->wc_cart_built) {
            $this->wc_cart_built = \true;
            $result = $this->cart_builder->paypal_cart_to_wc_cart($this->paypal_cart);
            if (!$result instanceof WP_Error) {
                $this->wc_cart_cache = $result;
            }
        }
        return $this->wc_cart_cache;
    }
    public function currency(): string
    {
        return $this->store_currency->value();
    }
    public function validation(): StoreValidation
    {
        return $this->validation;
    }
    /**
     * Whether the current cart is ready for a payment attempt.
     *
     * Verifies if cart items and payment token are present, and no validation issues exist.
     */
    public function is_ready_for_payment(): bool
    {
        // An empty cart cannot be paid.
        if (empty($this->store_items)) {
            return \false;
        }
        // Any validation issue is a blocker that must be resolved before payment attempt.
        if (!$this->validation->is_empty()) {
            return \false;
        }
        // Missing payment token, cart is not ready.
        if (!$this->paypal_cart->payment_method()->token()) {
            return \false;
        }
        return \true;
    }
    // === API RESPONSE FORMAT ===
    /**
     * Calculates cart totals from the WC_Cart if available and no pricing issues exist.
     *
     * @return array|null
     */
    public function get_totals(): ?array
    {
        $wc_cart = $this->wc_cart();
        if (!$wc_cart) {
            return null;
        }
        $currency_code = $this->currency();
        $item_total = (float) $wc_cart->get_cart_contents_total();
        $discount_total = (float) $wc_cart->get_discount_total();
        $shipping_total = (float) $wc_cart->get_shipping_total();
        $tax_total = (float) $wc_cart->get_total_tax();
        $cart_total = (float) $wc_cart->get_total('edit');
        if ($item_total <= 0 || $cart_total <= 0) {
            return null;
        }
        $totals = array('subtotal' => Money::create($item_total, $currency_code)->to_array(), 'shipping' => Money::create($shipping_total, $currency_code)->to_array(), 'tax' => Money::create($tax_total, $currency_code)->to_array(), 'total' => Money::create($cart_total, $currency_code)->to_array());
        if ($discount_total > 0) {
            $totals['discount'] = Money::create($discount_total, $currency_code)->to_array();
        }
        return $totals;
    }
    /**
     * An array containing all validation issues for the API response. If no validation
     * issues were recorded, an empty array is returned.
     */
    public function get_validation_issues(): array
    {
        return array_map(static fn(ValidationIssue $issue) => $issue->to_array(), $this->validation()->all());
    }
    /**
     * The full "payment_method" schema, including the ec_token (if a paypal_order is set).
     */
    public function get_payment_method(): array
    {
        $data = $this->paypal_cart->payment_method()->to_array();
        if ($this->paypal_order) {
            $data['token'] = $this->paypal_order;
        }
        return $data;
    }
    public function get_items(): array
    {
        return array_map(static fn(\WooCommerce\PayPalCommerce\StoreSync\StoreData\StoreCartItem $item) => $item->to_array(), $this->cart_items());
    }
    public function get_customer(): array
    {
        $customer = $this->paypal_cart->customer();
        if (!$customer) {
            return array();
        }
        return $customer->to_array();
    }
    public function get_shipping_address(): array
    {
        $address = $this->paypal_cart->shipping_address();
        if ($address->is_empty()) {
            return array();
        }
        return $address->to_array();
    }
    public function get_billing_address(): ?array
    {
        $address = $this->paypal_cart->billing_address();
        if (!$address) {
            return null;
        }
        return $address->to_array();
    }
    // === END OF API RESPONSE FORMAT ===
}
