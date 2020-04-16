<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Repository;

use Inpsyde\PayPalCommerce\ApiClient\Entity\Item;
use Inpsyde\PayPalCommerce\ApiClient\Entity\PurchaseUnit;
use Inpsyde\PayPalCommerce\ApiClient\Factory\PurchaseUnitFactory;

class CartRepository implements PurchaseUnitRepositoryInterface
{
    private $factory;
    public function __construct(PurchaseUnitFactory $factory)
    {
        $this->factory = $factory;
    }

    /**
     * Returns all Pur of the Woocommerce cart.
     *
     * @return PurchaseUnit[]
     */
    public function all() : array
    {
        $cart = WC()->cart ?? new \WC_Cart();
        return [$this->factory->fromWcCart($cart)];
    }
}
