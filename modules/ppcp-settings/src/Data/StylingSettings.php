<?php

/**
 * Styling details class
 *
 * @package WooCommerce\PayPalCommerce\Settings\Data
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Settings\Data;

use RuntimeException;
use WooCommerce\PayPalCommerce\Settings\DTO\LocationStylingDTO;
use WooCommerce\PayPalCommerce\Settings\Service\DataSanitizer;
/**
 * Class StylingSettings
 *
 * Stores and manages the styling details.
 */
class StylingSettings extends \WooCommerce\PayPalCommerce\Settings\Data\AbstractDataModel
{
    /**
     * Option key where profile details are stored.
     *
     * @var string
     */
    protected const OPTION_KEY = 'woocommerce-ppcp-data-styling';
    /**
     * Data sanitizer service.
     *
     * @var DataSanitizer
     */
    protected DataSanitizer $sanitizer;
    /**
     * Constructor.
     *
     * @param DataSanitizer $sanitizer Data sanitizer service.
     * @throws RuntimeException If the OPTION_KEY is not defined in the child class.
     */
    public function __construct(DataSanitizer $sanitizer)
    {
        $this->sanitizer = $sanitizer;
        parent::__construct();
    }
    /**
     * Get default values for the model.
     *
     * @return array
     */
    protected function get_defaults(): array
    {
        return array('cart' => new LocationStylingDTO('cart'), 'classic_checkout' => new LocationStylingDTO('classic_checkout'), 'express_checkout' => new LocationStylingDTO('express_checkout'), 'mini_cart' => new LocationStylingDTO('mini_cart', \false), 'product' => new LocationStylingDTO('product'));
    }
    /**
     * Get styling details for Cart and Block Cart.
     *
     * @return LocationStylingDTO
     */
    public function get_cart(): LocationStylingDTO
    {
        return $this->data['cart'];
    }
    /**
     * Set styling details for Cart and Block Cart.
     *
     * @param mixed $styles The new styling details.
     * @return void
     */
    public function set_cart($styles): void
    {
        $this->data['cart'] = $this->sanitizer->sanitize_location_style($styles);
    }
    /**
     * Get styling details for Classic Checkout.
     *
     * @return LocationStylingDTO
     */
    public function get_classic_checkout(): LocationStylingDTO
    {
        return $this->data['classic_checkout'];
    }
    /**
     * Set styling details for Classic Checkout.
     *
     * @param mixed $styles The new styling details.
     * @return void
     */
    public function set_classic_checkout($styles): void
    {
        $this->data['classic_checkout'] = $this->sanitizer->sanitize_location_style($styles);
    }
    /**
     * Get styling details for Express Checkout.
     *
     * @return LocationStylingDTO
     */
    public function get_express_checkout(): LocationStylingDTO
    {
        return $this->data['express_checkout'];
    }
    /**
     * Set styling details for Express Checkout.
     *
     * @param mixed $styles The new styling details.
     * @return void
     */
    public function set_express_checkout($styles): void
    {
        $this->data['express_checkout'] = $this->sanitizer->sanitize_location_style($styles);
    }
    /**
     * Get styling details for Mini Cart
     *
     * @return LocationStylingDTO
     */
    public function get_mini_cart(): LocationStylingDTO
    {
        return $this->data['mini_cart'];
    }
    /**
     * Set styling details for Mini Cart.
     *
     * @param mixed $styles The new styling details.
     * @return void
     */
    public function set_mini_cart($styles): void
    {
        $this->data['mini_cart'] = $this->sanitizer->sanitize_location_style($styles);
    }
    /**
     * Get styling details for Product Page.
     *
     * @return LocationStylingDTO
     */
    public function get_product(): LocationStylingDTO
    {
        return $this->data['product'];
    }
    /**
     * Set styling details for Product Page.
     *
     * @param mixed $styles The new styling details.
     * @return void
     */
    public function set_product($styles): void
    {
        $this->data['product'] = $this->sanitizer->sanitize_location_style($styles);
    }
    /**
     * Gets an array of enabled smart button location names.
     *
     * @return array Array of location names where buttons are enabled.
     */
    public function get_smart_button_locations(): array
    {
        $locations = array();
        $location_map = array('cart' => 'cart', 'classic_checkout' => 'checkout', 'express_checkout' => 'checkout-block-express', 'mini_cart' => 'mini-cart', 'product' => 'product');
        foreach ($location_map as $key => $location_name) {
            if (isset($this->data[$key]) && $this->data[$key] instanceof LocationStylingDTO && $this->data[$key]->enabled) {
                $locations[] = $location_name;
            }
        }
        return $locations;
    }
    /**
     * Gets an array of enabled Pay Later button location names.
     *
     * @return array Array of location names where Pay Later buttons are enabled.
     */
    public function get_pay_later_button_locations(): array
    {
        $locations = array();
        $location_map = array('cart' => 'cart', 'classic_checkout' => 'checkout', 'express_checkout' => 'checkout-block-express', 'mini_cart' => 'mini-cart', 'product' => 'product');
        foreach ($location_map as $key => $location_name) {
            if (isset($this->data[$key]) && $this->data[$key] instanceof LocationStylingDTO) {
                $dto = $this->data[$key];
                if ($dto->enabled && in_array('pay-later', $dto->methods, \true)) {
                    $locations[] = $location_name;
                }
            }
        }
        return $locations;
    }
}
