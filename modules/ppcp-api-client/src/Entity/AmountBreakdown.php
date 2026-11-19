<?php

/**
 * The Amount Breakdown object.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Entity
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\ApiClient\Entity;

/**
 * Class AmountBreakdown
 */
class AmountBreakdown
{
    /**
     * The item total.
     *
     * @var Money|null
     */
    private $item_total;
    /**
     * The shipping.
     *
     * @var Money|null
     */
    private $shipping;
    /**
     * The tax total.
     *
     * @var Money|null
     */
    private $tax_total;
    /**
     * The handling.
     *
     * @var Money|null
     */
    private $handling;
    /**
     * The insurance.
     *
     * @var Money|null
     */
    private $insurance;
    /**
     * The shipping discount.
     *
     * @var Money|null
     */
    private $shipping_discount;
    /**
     * The discount.
     *
     * @var Money|null
     */
    private $discount;
    /**
     * AmountBreakdown constructor.
     *
     * @param Money|null $item_total The item total.
     * @param Money|null $shipping The shipping.
     * @param Money|null $tax_total The tax total.
     * @param Money|null $handling The handling.
     * @param Money|null $insurance The insurance.
     * @param Money|null $shipping_discount The shipping discount.
     * @param Money|null $discount The discount.
     */
    public function __construct(?\WooCommerce\PayPalCommerce\ApiClient\Entity\Money $item_total = null, ?\WooCommerce\PayPalCommerce\ApiClient\Entity\Money $shipping = null, ?\WooCommerce\PayPalCommerce\ApiClient\Entity\Money $tax_total = null, ?\WooCommerce\PayPalCommerce\ApiClient\Entity\Money $handling = null, ?\WooCommerce\PayPalCommerce\ApiClient\Entity\Money $insurance = null, ?\WooCommerce\PayPalCommerce\ApiClient\Entity\Money $shipping_discount = null, ?\WooCommerce\PayPalCommerce\ApiClient\Entity\Money $discount = null)
    {
        $this->item_total = $item_total;
        $this->shipping = $shipping;
        $this->tax_total = $tax_total;
        $this->handling = $handling;
        $this->insurance = $insurance;
        $this->shipping_discount = $shipping_discount;
        $this->discount = $discount;
    }
    /**
     * Returns the item total.
     *
     * @return Money|null
     */
    public function item_total()
    {
        return $this->item_total;
    }
    /**
     * Returns the shipping.
     *
     * @return Money|null
     */
    public function shipping()
    {
        return $this->shipping;
    }
    /**
     * Returns the tax total.
     *
     * @return Money|null
     */
    public function tax_total()
    {
        return $this->tax_total;
    }
    /**
     * Returns the handling.
     *
     * @return Money|null
     */
    public function handling()
    {
        return $this->handling;
    }
    /**
     * Returns the insurance.
     *
     * @return Money|null
     */
    public function insurance()
    {
        return $this->insurance;
    }
    /**
     * Returns the shipping discount.
     *
     * @return Money|null
     */
    public function shipping_discount()
    {
        return $this->shipping_discount;
    }
    /**
     * Returns the discount.
     *
     * @return Money|null
     */
    public function discount()
    {
        return $this->discount;
    }
    /**
     * Returns the object as array.
     *
     * @return array
     */
    public function to_array()
    {
        $breakdown = array();
        if ($this->item_total) {
            $breakdown['item_total'] = $this->item_total->to_array();
        }
        if ($this->shipping) {
            $breakdown['shipping'] = $this->shipping->to_array();
        }
        if ($this->tax_total) {
            $breakdown['tax_total'] = $this->tax_total->to_array();
        }
        if ($this->handling) {
            $breakdown['handling'] = $this->handling->to_array();
        }
        if ($this->insurance) {
            $breakdown['insurance'] = $this->insurance->to_array();
        }
        if ($this->shipping_discount) {
            $breakdown['shipping_discount'] = $this->shipping_discount->to_array();
        }
        if ($this->discount) {
            $breakdown['discount'] = $this->discount->to_array();
        }
        return $breakdown;
    }
}
