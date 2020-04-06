<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Entity;

class AmountBreakdown
{

    private $itemTotal;
    private $shipping;
    private $taxTotal;
    private $handling;
    private $insurance;
    private $shippingDiscount;
    private $discount;
    public function __construct(
        Money $itemTotal = null,
        Money $shipping = null,
        Money $taxTotal = null,
        Money $handling = null,
        Money $insurance = null,
        Money $shippingDiscount = null,
        Money $discount = null
    ) {

        $this->itemTotal = $itemTotal;
        $this->shipping = $shipping;
        $this->taxTotal = $taxTotal;
        $this->handling = $handling;
        $this->insurance = $insurance;
        $this->shippingDiscount = $shippingDiscount;
        $this->discount = $discount;
    }

    public function toArray() : array
    {
        $breakdown = [];
        if ($this->itemTotal) {
            $breakdown['item_total'] = $this->itemTotal->toArray();
        }
        if ($this->shipping) {
            $breakdown['shipping'] = $this->shipping->toArray();
        }
        if ($this->taxTotal) {
            $breakdown['tax_total'] = $this->taxTotal->toArray();
        }
        if ($this->handling) {
            $breakdown['handling'] = $this->handling->toArray();
        }
        if ($this->insurance) {
            $breakdown['insurance'] = $this->insurance->toArray();
        }
        if ($this->shippingDiscount) {
            $breakdown['shipping_discount'] = $this->shippingDiscount->toArray();
        }
        if ($this->discount) {
            $breakdown['discount'] = $this->discount->toArray();
        }

        return $breakdown;
    }
}
