<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use WooCommerce\PayPalCommerce\Assets\AssetGetter;
class OXXOPaymentMethod extends AbstractPaymentMethodType
{
    private AssetGetter $asset_getter;
    private string $version;
    private \WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\OXXOGateway $gateway;
    public function __construct(AssetGetter $asset_getter, string $version, \WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\OXXOGateway $gateway)
    {
        $this->asset_getter = $asset_getter;
        $this->version = $version;
        $this->gateway = $gateway;
        $this->name = \WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\OXXOGateway::ID;
    }
    public function initialize(): void
    {
    }
    public function is_active()
    {
        return \true;
    }
    public function get_payment_method_script_handles()
    {
        wp_register_script('ppcp-oxxo-payment-method', $this->asset_getter->get_asset_url('oxxo-payment-method.js'), array(), $this->version, \true);
        return array('ppcp-oxxo-payment-method');
    }
    public function get_payment_method_data()
    {
        return array('id' => $this->name, 'title' => $this->gateway->title, 'description' => $this->gateway->description, 'icon' => $this->gateway->icon);
    }
}
