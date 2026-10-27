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
use WooCommerce\PayPalCommerce\ApiClient\Factory\AmountFactory;
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
        // Any validation issue is a blocker that must be resolved before payment attempt.
        if (!$this->validation->is_empty()) {
            return \false;
        }
        // An empty cart cannot be paid.
        if (empty($this->store_items)) {
            $this->validation->add_missing_field('items', 'Cannot pay cart: It is empty.');
            return \false;
        }
        $method = $this->paypal_cart()->payment_method();
        // Cart not reads: missing payment type (expected "paypal").
        if (!$method->type()) {
            $this->validation->add_missing_field('payment_method.type', 'Cannot pay cart: Payment type is not defined.');
            return \false;
        }
        // Cart not reads: missing payment token.
        if (!$method->token()) {
            $this->validation->add_missing_field('payment_method.token', 'Cannot pay cart: PayPal order is missing.');
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
        $item_cents = $this->item_total_cents();
        $shipping_cents = $this->to_cents((float) $wc_cart->get_shipping_total());
        $total_cents = $this->to_cents((float) $wc_cart->get_total('edit'));
        if ($item_cents <= 0 || $total_cents <= 0) {
            return null;
        }
        list($tax_cents, $discount_cents) = AmountFactory::reconcile_with_total($total_cents, $item_cents, $shipping_cents, $this->to_cents((float) $wc_cart->get_total_tax()), $this->to_cents((float) $wc_cart->get_discount_total()));
        $totals = array('subtotal' => $this->money_array($item_cents), 'shipping' => $this->money_array($shipping_cents), 'tax' => $this->money_array($tax_cents), 'total' => $this->money_array($total_cents));
        if ($discount_cents > 0) {
            $totals['discount'] = $this->money_array($discount_cents);
        }
        return $totals;
    }
    /**
     * Sum of the listed item prices, so that the subtotal matches the items in the response.
     */
    private function item_total_cents(): int
    {
        $cents = 0;
        foreach ($this->store_items as $item) {
            $cents += $this->to_cents($item->real_price()) * $item->quantity();
        }
        return $cents;
    }
    private function to_cents(float $amount): int
    {
        return (int) round($amount * 100);
    }
    private function money_array(int $cents): array
    {
        return Money::create($cents / 100, $this->currency())->to_array();
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
