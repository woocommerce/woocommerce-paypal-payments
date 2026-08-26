<?php

/**
 * Pay with Crypto payment method.
 *
 * @package WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use WooCommerce\PayPalCommerce\Assets\AssetGetter;
/**
 * Class PWCPaymentMethod
 */
class PWCPaymentMethod extends AbstractPaymentMethodType
{
    private AssetGetter $asset_getter;
    /**
     * The assets version.
     *
     * @var string
     */
    private string $version;
    /**
     * PWCGateway WC gateway.
     *
     * @var PWCGateway
     */
    private \WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\PWCGateway $gateway;
    /**
     * @param AssetGetter $asset_getter
     * @param string      $version The assets version.
     * @param PWCGateway  $gateway Pay with Crypto WC gateway.
     */
    public function __construct(AssetGetter $asset_getter, string $version, \WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\PWCGateway $gateway)
    {
        $this->asset_getter = $asset_getter;
        $this->version = $version;
        $this->gateway = $gateway;
        $this->name = \WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\PWCGateway::ID;
    }
    /**
     * {@inheritDoc}
     */
    public function initialize(): void
    {
    }
    /**
     * {@inheritDoc}
     */
    public function is_active(): bool
    {
        return \true;
    }
    /**
     * {@inheritDoc}
     */
    public function get_payment_method_script_handles(): array
    {
        wp_register_script('ppcp-pwc-payment-method', $this->asset_getter->get_asset_url('pwc-payment-method.js'), array(), $this->version, \true);
        return array('ppcp-pwc-payment-method');
    }
    /**
     * {@inheritDoc}
     */
    public function get_payment_method_data(): array
    {
        return array('id' => $this->name, 'title' => $this->gateway->title, 'description' => $this->gateway->description, 'icon' => $this->gateway->icon);
    }
}
