<?php

/**
 * The googlepay blocks module.
 *
 * @package WooCommerce\PayPalCommerce\Googlepay
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Googlepay\Assets;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use Automattic\WooCommerce\Blocks\Payments\PaymentMethodTypeInterface;
use WooCommerce\PayPalCommerce\Assets\AssetGetter;
use WooCommerce\PayPalCommerce\Button\Assets\ButtonInterface;
/**
 * Class BlocksPaymentMethod
 */
class BlocksPaymentMethod extends AbstractPaymentMethodType
{
    private AssetGetter $asset_getter;
    /**
     * The assets version.
     *
     * @var string
     */
    private $version;
    /**
     * The button.
     *
     * @var ButtonInterface
     */
    private $button;
    /**
     * The paypal payment method.
     *
     * @var PaymentMethodTypeInterface
     */
    private $paypal_payment_method;
    /**
     * @param string                     $name The name of this module.
     * @param AssetGetter                $asset_getter
     * @param string                     $version The assets version.
     * @param ButtonInterface            $button The button.
     * @param PaymentMethodTypeInterface $paypal_payment_method The paypal payment method.
     */
    public function __construct(string $name, AssetGetter $asset_getter, string $version, ButtonInterface $button, PaymentMethodTypeInterface $paypal_payment_method)
    {
        $this->name = $name;
        $this->asset_getter = $asset_getter;
        $this->version = $version;
        $this->button = $button;
        $this->paypal_payment_method = $paypal_payment_method;
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
        return $this->paypal_payment_method->is_active();
    }
    /**
     * {@inheritDoc}
     */
    public function get_payment_method_script_handles()
    {
        $handle = $this->name . '-block';
        wp_register_script($handle, $this->asset_getter->get_asset_url('boot-block.js'), array(), $this->version, \true);
        return array($handle);
    }
    /**
     * {@inheritDoc}
     */
    public function get_payment_method_data()
    {
        $paypal_data = $this->paypal_payment_method->get_payment_method_data();
        return array(
            'id' => $this->name,
            'title' => $paypal_data['title'],
            // See if we should use another.
            'description' => $paypal_data['description'],
            // See if we should use another.
            'enabled' => $paypal_data['smartButtonsEnabled'],
            // This button is enabled when PayPal buttons are.
            'scriptData' => $this->button->script_data(),
        );
    }
}
