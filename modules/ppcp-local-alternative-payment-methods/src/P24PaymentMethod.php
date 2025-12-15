<?php

/**
 * Przelewy24 payment method.
 *
 * @package WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
/**
 * Class P24PaymentMethod
 */
class P24PaymentMethod extends AbstractPaymentMethodType
{
    /**
     * The URL of this module.
     *
     * @var string
     */
    private $module_url;
    /**
     * The assets version.
     *
     * @var string
     */
    private $version;
    /**
     * P24Gateway WC gateway.
     *
     * @var P24Gateway
     */
    private $gateway;
    /**
     * P24PaymentMethod constructor.
     *
     * @param string     $module_url The URL of this module.
     * @param string     $version The assets version.
     * @param P24Gateway $gateway Przelewy24 WC gateway.
     */
    public function __construct(string $module_url, string $version, \WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\P24Gateway $gateway)
    {
        $this->module_url = $module_url;
        $this->version = $version;
        $this->gateway = $gateway;
        $this->name = \WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\P24Gateway::ID;
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
        wp_register_script('ppcp-p24-payment-method', trailingslashit($this->module_url) . 'assets/js/p24-payment-method.js', array(), $this->version, \true);
        return array('ppcp-p24-payment-method');
    }
    /**
     * {@inheritDoc}
     */
    public function get_payment_method_data()
    {
        return array('id' => $this->name, 'title' => $this->gateway->title, 'description' => $this->gateway->description, 'icon' => $this->gateway->icon);
    }
}
