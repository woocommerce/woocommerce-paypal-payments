<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\WcGateway\Settings;

use Inpsyde\PayPalCommerce\WcGateway\Exception\NotFoundException;
use Inpsyde\PayPalCommerce\WcGateway\Gateway\WcGatewayInterface;
use Psr\Container\ContainerInterface;

class Settings implements ContainerInterface
{
    public const KEY = 'woocommerce-ppcp-settings';
    private $settings = [];

    public function __construct()
    {
    }

    // phpcs:disable Inpsyde.CodeQuality.ReturnTypeDeclaration.NoReturnType
    // phpcs:disable Inpsyde.CodeQuality.ArgumentTypeDeclaration.NoArgumentType
    public function get($id)
    {
        if (!$this->has($id)) {
            throw new NotFoundException();
        }
        return $this->settings[$id];
    }

    public function has($id)
    {
        $this->load();
        return array_key_exists($id, $this->settings);
    }

    public function set($id, $value)
    {
        $this->load();
        $this->settings[$id] = $value;
    }

    public function persist()
    {

        update_option(self::KEY, $this->settings);
    }

    public function reset(): bool
    {
        $this->load();
        $fieldsToReset = [
            'enabled',
            'intent',
            'client_id',
            'client_secret',
            'merchant_email',
        ];
        foreach ($fieldsToReset as $id) {
            $this->settings[$id] = null;
        }

        return true;
    }

    private function load(): bool
    {

        if ($this->settings) {
            return false;
        }
        $this->settings = get_option(self::KEY, []);

        $defaults = [
            'title' => __('PayPal', 'woocommerce-paypal-commerce-gateway'),
            'description' => __('Pay via PayPal; you can pay with your credit card if you don\'t have a PayPal account.', 'woocommerce-paypal-commerce-gateway'),
            'button_single_product_enabled' => true,
            'button_mini_cart_enabled' => true,
            'button_cart_enabled' => true,
            'dcc_cart_enabled' => true,
            'dcc_mini_cart_enabled' => true,
            'dcc_checkout_enabled' => true,
            'dcc_single_product_enabled' => true,
        ];
        foreach ($defaults as $key => $value) {
            if (isset($this->settings[$key])) {
                continue;
            }
            $this->settings[$key] = $value;
        }
        return true;
    }
}
