<?php

/**
 * Pay with Crypto payment method.
 *
 * @package WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
/**
 * Class PWCPaymentMethod
 */
class PWCPaymentMethod extends AbstractPaymentMethodType
{
    /**
     * The URL of this module.
     *
     * @var string
     */
    private string $module_url;
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
     * PWCPaymentMethod constructor.
     *
     * @param string     $module_url The URL of this module.
     * @param string     $version The assets version.
     * @param PWCGateway $gateway Pay with Crypto WC gateway.
     */
    public function __construct(string $module_url, string $version, \WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\PWCGateway $gateway)
    {
        $this->module_url = $module_url;
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
        wp_register_script('ppcp-pwc-payment-method', trailingslashit($this->module_url) . 'assets/js/pwc-payment-method.js', array(), $this->version, \true);
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
