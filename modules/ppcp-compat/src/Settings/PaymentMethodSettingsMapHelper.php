<?php

/**
 * A helper for mapping the old/new payment method settings.
 *
 * @package WooCommerce\PayPalCommerce\Compat\Settings
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Compat\Settings;

use WooCommerce\PayPalCommerce\Axo\Gateway\AxoGateway;
use WooCommerce\PayPalCommerce\Settings\Data\AbstractDataModel;
use WooCommerce\PayPalCommerce\Settings\Data\PaymentSettings;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
/**
 * A map of old to new payment method settings.
 *
 * @psalm-import-type newSettingsKey from SettingsMap
 * @psalm-import-type oldSettingsKey from SettingsMap
 */
class PaymentMethodSettingsMapHelper
{
    /**
     * Maps old setting keys to new payment method settings names.
     *
     * @psalm-return array<oldSettingsKey, newSettingsKey>
     */
    public function map(): array
    {
        return array('dcc_enabled' => CreditCardGateway::ID, 'axo_enabled' => AxoGateway::ID, 'axo_name_on_card' => 'cardholder_name', 'dcc_gateway_title' => '');
    }
    /**
     * Retrieves the value of a mapped key from the new settings.
     *
     * @param string                 $old_key The key from the legacy settings.
     * @param AbstractDataModel|null $payment_settings The payment settings model.
     * @return mixed The value of the mapped setting, (null if not found).
     */
    public function mapped_value(string $old_key, ?AbstractDataModel $payment_settings)
    {
        $new_key = $this->map()[$old_key] ?? \false;
        if (!$payment_settings instanceof PaymentSettings) {
            return null;
        }
        switch ($old_key) {
            case 'axo_name_on_card':
                return $payment_settings->get_cardholder_name();
            case 'dcc_gateway_title':
                $axo_gateway_settings = get_option('woocommerce_ppcp-axo-gateway_settings', array());
                return $axo_gateway_settings['title'] ?? null;
            default:
                return $new_key ? $this->is_gateway_enabled($new_key) : null;
        }
    }
    /**
     * Checks if the payment gateway with the given name is enabled.
     *
     * @param string $gateway_name The gateway name.
     * @return bool True if the payment gateway with the given name is enabled, otherwise false.
     */
    protected function is_gateway_enabled(string $gateway_name): bool
    {
        $gateway_settings = get_option("woocommerce_{$gateway_name}_settings", array());
        $gateway_enabled = $gateway_settings['enabled'] ?? \false;
        return $gateway_enabled === 'yes';
    }
}
