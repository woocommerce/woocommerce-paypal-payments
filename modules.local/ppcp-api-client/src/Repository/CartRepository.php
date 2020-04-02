<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Repository;


use Inpsyde\PayPalCommerce\ApiClient\Entity\LineItem;
use Inpsyde\PayPalCommerce\ApiClient\Factory\LineItemFactory;

class CartRepository implements LineItemRepositoryInterface
{
    private $cart;
    private $factory;
    public function __construct(\WC_Cart $cart, LineItemFactory $factory)
    {
        $this->cart = $cart;
        $this->factory = $factory;
    }

    /**
     * Returns all LineItem of the Woocommerce cart.
     *
     * @return array
     */
    public function all() : array {

        return array_map(
            function(array $lineItem) : LineItem {
                return $this->factory->fromWoocommerceLineItem($lineItem, get_woocommerce_currency());
            },
            array_values($this->cart->get_cart())
        );
    }
}
