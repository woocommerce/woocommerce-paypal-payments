<?php

/**
 * The seller status entity.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Entity
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\ApiClient\Entity;

/**
 * Class SellerStatus
 */
class SellerStatus
{
    /**
     * The products.
     *
     * @var SellerStatusProduct[]
     */
    private $products;
    /**
     * The capabilities.
     *
     * @var SellerStatusCapability[]
     */
    private $capabilities;
    /**
     * Merchant country on PayPal.
     *
     * @var string
     */
    private string $country;
    /**
     * SellerStatus constructor.
     *
     * @param SellerStatusProduct[]    $products The products.
     * @param SellerStatusCapability[] $capabilities The capabilities.
     * @param string                   $country Merchant country on PayPal.
     *
     * @psalm-suppress RedundantConditionGivenDocblockType
     */
    public function __construct(array $products, array $capabilities, string $country = '')
    {
        foreach ($products as $key => $product) {
            if (is_a($product, \WooCommerce\PayPalCommerce\ApiClient\Entity\SellerStatusProduct::class)) {
                continue;
            }
            unset($products[$key]);
        }
        foreach ($capabilities as $key => $capability) {
            if (is_a($capability, \WooCommerce\PayPalCommerce\ApiClient\Entity\SellerStatusCapability::class)) {
                continue;
            }
            unset($capabilities[$key]);
        }
        $this->products = $products;
        $this->capabilities = $capabilities;
        $this->country = $country;
    }
    /**
     * Returns the products.
     *
     * @return SellerStatusProduct[]
     */
    public function products(): array
    {
        return $this->products;
    }
    /**
     * Returns the capabilities.
     *
     * @return SellerStatusCapability[]
     */
    public function capabilities(): array
    {
        return $this->capabilities;
    }
    /**
     * Returns merchant's country on PayPal.
     *
     * @return string
     */
    public function country(): string
    {
        return $this->country;
    }
    /**
     * Returns the entity as array.
     *
     * @return array
     */
    public function to_array(): array
    {
        $products = array_map(function (\WooCommerce\PayPalCommerce\ApiClient\Entity\SellerStatusProduct $product): array {
            return $product->to_array();
        }, $this->products());
        $capabilities = array_map(function (\WooCommerce\PayPalCommerce\ApiClient\Entity\SellerStatusCapability $capability): array {
            return $capability->to_array();
        }, $this->capabilities());
        return array('products' => $products, 'capabilities' => $capabilities, 'country' => $this->country);
    }
}
