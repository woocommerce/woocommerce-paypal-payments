<?php

/**
 * The applepay blocks module.
 *
 * @package WooCommerce\PayPalCommerce\Applepay
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Applepay\Assets;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use Automattic\WooCommerce\Blocks\Payments\PaymentMethodTypeInterface;
use WooCommerce\PayPalCommerce\Applepay\ApplePayGateway;
use WooCommerce\PayPalCommerce\Assets\AssetGetter;
use WooCommerce\PayPalCommerce\Button\Assets\ButtonInterface;
use WooCommerce\PayPalCommerce\Button\Helper\Context;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
class BlocksPaymentMethod extends AbstractPaymentMethodType
{
    private AssetGetter $asset_getter;
    private string $version;
    private ButtonInterface $button;
    private PaymentMethodTypeInterface $paypal_payment_method;
    private Context $context;
    private SettingsProvider $settings_provider;
    public function __construct(string $name, AssetGetter $asset_getter, string $version, ButtonInterface $button, PaymentMethodTypeInterface $paypal_payment_method, Context $context, SettingsProvider $settings_provider)
    {
        $this->name = $name;
        $this->asset_getter = $asset_getter;
        $this->version = $version;
        $this->button = $button;
        $this->paypal_payment_method = $paypal_payment_method;
        $this->context = $context;
        $this->settings_provider = $settings_provider;
    }
    public function initialize()
    {
    }
    public function is_active(): bool
    {
        $methods = $this->settings_provider->button_styling($this->context->context())->methods;
        if (!in_array(ApplePayGateway::ID, $methods, \true)) {
            return \false;
        }
        return $this->paypal_payment_method->is_active();
    }
    public function get_payment_method_script_handles()
    {
        $handle = $this->name . '-block';
        wp_register_script($handle, $this->asset_getter->get_asset_url('boot-block.js'), array(), $this->version, \true);
        return array($handle);
    }
    public function get_payment_method_data()
    {
        $paypal_data = $this->paypal_payment_method->get_payment_method_data();
        if (is_admin()) {
            $script_data = $this->button->script_data_for_admin();
            // @phpstan-ignore method.notFound
        } else {
            $script_data = $this->button->script_data();
        }
        return array(
            'id' => $this->name,
            'title' => $paypal_data['title'],
            // TODO : see if we should use another.
            'description' => $paypal_data['description'],
            // TODO : see if we should use another.
            'enabled' => $this->is_active(),
            'scriptData' => $script_data,
        );
    }
}
