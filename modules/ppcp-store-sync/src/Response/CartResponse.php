<?php

/**
 * PayPal Cart Response.
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\Response
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Response;

use WC_Cart;
use WooCommerce\PayPalCommerce\StoreSync\Schema\PayPalCart;
use WooCommerce\PayPalCommerce\StoreSync\Validation\ValidationIssue;
use WooCommerce\PayPalCommerce\StoreSync\Helper\CartHelper;
use WooCommerce\PayPalCommerce\StoreSync\Enums\ErrorCode;
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
     * @return array|null The cart-totals array, or null if not calculable.
     */
    private function calculate_totals(): ?array
    {
        if (!$this->wc_cart || $this->cart->has_validation_issue(ErrorCode::PRICING_ERROR)) {
            return null;
        }
        $currency_code = CartHelper::currency($this->cart);
        return CartHelper::calculate_totals($this->wc_cart, $currency_code);
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
