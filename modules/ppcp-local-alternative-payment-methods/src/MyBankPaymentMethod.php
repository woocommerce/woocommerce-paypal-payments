<?php

/**
 * MyBank payment method.
 *
 * @package WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use WooCommerce\PayPalCommerce\Assets\AssetGetter;
/**
 * Class MyBankPaymentMethod
 */
class MyBankPaymentMethod extends AbstractPaymentMethodType
{
    private AssetGetter $asset_getter;
    /**
     * The assets version.
     *
     * @var string
     */
    private $version;
    /**
     * MyBank WC gateway.
     *
     * @var MyBankGateway
     */
    private $gateway;
    /**
     * @param AssetGetter   $asset_getter
     * @param string        $version The assets version.
     * @param MyBankGateway $gateway MyBank WC gateway.
     */
    public function __construct(AssetGetter $asset_getter, string $version, \WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\MyBankGateway $gateway)
    {
        $this->asset_getter = $asset_getter;
        $this->version = $version;
        $this->gateway = $gateway;
        $this->name = \WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\MyBankGateway::ID;
    }
    /**
     * {@inheritDoc}
     */
    public function initialize()
    {
    }
    /**
     * {@inheritDoc}
     */
    public function is_active()
    {
        return \true;
    }
    /**
     * {@inheritDoc}
     */
    public function get_payment_method_script_handles()
    {
        wp_register_script('ppcp-mybank-payment-method', $this->asset_getter->get_asset_url('mybank-payment-method.js'), array(), $this->version, \true);
        return array('ppcp-mybank-payment-method');
    }
    /**
     * {@inheritDoc}
     */
    public function get_payment_method_data()
    {
        return array('id' => $this->name, 'title' => $this->gateway->title, 'description' => $this->gateway->description, 'icon' => $this->gateway->icon);
    }
}
