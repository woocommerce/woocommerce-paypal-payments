<?php

/**
 * Multibanco payment method.
 *
 * @package WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use WooCommerce\PayPalCommerce\Assets\AssetGetter;
/**
 * Class MultibancoPaymentMethod
 */
class MultibancoPaymentMethod extends AbstractPaymentMethodType
{
    private AssetGetter $asset_getter;
    /**
     * The assets version.
     *
     * @var string
     */
    private $version;
    /**
     * Multibanco WC gateway.
     *
     * @var MultibancoGateway
     */
    private $gateway;
    /**
     * @param AssetGetter       $asset_getter
     * @param string            $version The assets version.
     * @param MultibancoGateway $gateway Multibanco WC gateway.
     */
    public function __construct(AssetGetter $asset_getter, string $version, \WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\MultibancoGateway $gateway)
    {
        $this->asset_getter = $asset_getter;
        $this->version = $version;
        $this->gateway = $gateway;
        $this->name = \WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\MultibancoGateway::ID;
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
        wp_register_script('ppcp-multibanco-payment-method', $this->asset_getter->get_asset_url('multibanco-payment-method.js'), array(), $this->version, \true);
        return array('ppcp-multibanco-payment-method');
    }
    /**
     * {@inheritDoc}
     */
    public function get_payment_method_data()
    {
        return array('id' => $this->name, 'title' => $this->gateway->title, 'description' => $this->gateway->description, 'icon' => $this->gateway->icon);
    }
}
