<?php

/**
 * PayPal Cart Response.
 *
 * @package WooCommerce\PayPalCommerce\AgenticCommerce\Response
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\AgenticCommerce\Response;

use WC_Cart;
use WooCommerce\PayPalCommerce\AgenticCommerce\Schema\PayPalCart;
use WooCommerce\PayPalCommerce\AgenticCommerce\Validation\ValidationIssue;
use WooCommerce\PayPalCommerce\AgenticCommerce\Helper\CartHelper;
use WooCommerce\PayPalCommerce\AgenticCommerce\Enums\ErrorCode;
class CartResponse
{
    private const ALLOWED_STATUS = array('CREATED', 'INCOMPLETE', 'READY', 'COMPLETED');
    private const ALLOWED_VALIDATION_STATUS = array('VALID', 'INVALID', 'REQUIRES_ADDITIONAL_INFORMATION');
    protected PayPalCart $cart;
    protected ?WC_Cart $wc_cart;
    /**
     * Applied coupons data.
     *
     * @var array
     */
    protected array $applied_coupons = array();
    /**
     * The cart ID used by the API to reference to an existing cart.
     */
    private string $cart_id;
    /**
     * Used to track cart lifecycle.
     * Possible values: CREATED, INCOMPLETE, READY, COMPLETED
     */
    protected string $status = 'INCOMPLETE';
    /**
     * Used to determine the next step.
     * Possible values: VALID, INVALID, REQUIRES_ADDITIONAL_INFORMATION
     */
    protected string $validation_status = 'INVALID';
    /**
     * The payment method token, used to verify checkout.
     */
    protected string $token = '';
    /**
     * Constructor.
     *
     * @param PayPalCart   $cart The PayPal cart.
     * @param array        $applied_coupons Applied coupons data.
     * @param string       $cart_id The cart ID.
     * @param WC_Cart|null $wc_cart The WooCommerce cart.
     */
    public function __construct(PayPalCart $cart, array $applied_coupons = array(), string $cart_id = '', ?WC_Cart $wc_cart = null)
    {
        $this->cart = $cart;
        $this->applied_coupons = $applied_coupons;
        $this->cart_id = $cart_id;
        $this->wc_cart = $wc_cart;
        if (!$this->cart->issues()) {
            $this->validation_status = 'VALID';
        }
    }
    /**
     * Convert to array for API response.
     *
     * @return array The response array.
     */
    public function to_array(): array
    {
        $data = array('id' => $this->cart_id, 'status' => $this->status(), 'validation_status' => $this->validation_status(), 'validation_issues' => array_map(static fn(ValidationIssue $issue) => $issue->to_array(), $this->cart->issues()));
        // Add applied_coupons if any coupons were successfully applied.
        if (!empty($this->applied_coupons)) {
            $data['applied_coupons'] = $this->applied_coupons;
        }
        $data = array_merge($data, $this->cart->to_array());
        $totals = $this->calculate_totals();
        if ($totals) {
            $data['totals'] = $totals;
        }
        return $data;
    }
    /**
     * Calculate cart totals.
     *
     * @return array The cart-totals array, or null if not calculatable.
     */
    private function calculate_totals(): ?array
    {
        // Cart items have invalid prices or currency: do not calculate totals.
        if (!$this->wc_cart || $this->cart->has_validation_issue(ErrorCode::PRICING_ERROR)) {
            return null;
        }
        $currency_code = CartHelper::currency($this->cart);
        $item_total = (float) $this->wc_cart->get_cart_contents_total();
        $discount_total = (float) $this->wc_cart->get_discount_total();
        $shipping_total = $this->wc_cart->get_shipping_total();
        $tax_total = $this->wc_cart->get_total_tax();
        $cart_total = (float) $this->wc_cart->get_total('edit');
        // Cart has no items, no currency, no quantity: nothing to calculate.
        if (!$currency_code || $item_total <= 0 || $cart_total <= 0) {
            return null;
        }
        $totals = array('item_total' => $this->money($currency_code, $item_total), 'shipping' => $this->money($currency_code, (float) $shipping_total), 'tax_total' => $this->money($currency_code, (float) $tax_total), 'amount' => $this->money($currency_code, $cart_total));
        if ($discount_total > 0) {
            $totals['discount'] = $this->money($currency_code, $discount_total);
        }
        return $totals;
    }
    private function money(string $currency_code, float $value): array
    {
        return array('currency_code' => $currency_code, 'value' => number_format($value, 2));
    }
    private function status(): string
    {
        if (in_array($this->status, self::ALLOWED_STATUS, \true)) {
            return $this->status;
        }
        return 'INCOMPLETE';
    }
    private function validation_status(): string
    {
        if (in_array($this->validation_status, self::ALLOWED_VALIDATION_STATUS, \true)) {
            return $this->validation_status;
        }
        return 'INVALID';
    }
}
