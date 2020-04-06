<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Repository;

use Inpsyde\PayPalCommerce\ApiClient\Entity\Item;
use Inpsyde\PayPalCommerce\ApiClient\Entity\PurchaseUnit;
use Inpsyde\PayPalCommerce\ApiClient\Factory\PurchaseUnitFactory;

class CartRepository implements PurchaseUnitRepositoryInterface
{
    private $cart;
    private $factory;
    public function __construct(\WC_Cart $cart, PurchaseUnitFactory $factory)
    {
        $this->cart = $cart;
        $this->factory = $factory;
    }

    /**
     * Returns all Pur of the Woocommerce cart.
     *
     * @return PurchaseUnit[]
     */
    public function all() : array
    {
        return [$this->factory->fromWcCart($this->cart)];
    }
}
