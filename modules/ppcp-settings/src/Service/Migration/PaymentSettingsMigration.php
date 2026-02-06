<?php

/**
 * Handles migration of payment settings from legacy format to new structure.
 *
 * @package WooCommerce\PayPalCommerce\Settings\Service\Migration
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Settings\Service\Migration;

use WooCommerce\PayPalCommerce\ApiClient\Helper\DccApplies;
use WooCommerce\PayPalCommerce\Applepay\ApplePayGateway;
use WooCommerce\PayPalCommerce\Axo\Gateway\AxoGateway;
use WooCommerce\PayPalCommerce\Googlepay\GooglePayGateway;
use WooCommerce\PayPalCommerce\Settings\Data\PaymentSettings;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Helper\CardPaymentsConfiguration;
use WooCommerce\PayPalCommerce\WcGateway\Helper\DCCProductStatus;
/**
 * Class PaymentSettingsMigration
 *
 * Handles migration of payment settings.
 */
class PaymentSettingsMigration implements \WooCommerce\PayPalCommerce\Settings\Service\Migration\SettingsMigrationInterface
{
    public const OPTION_NAME_BCDC_MIGRATION_OVERRIDE = 'woocommerce_paypal_payments_bcdc_migration_override';
    /**
     * @var array<string, mixed>
     */
    protected array $settings;
    protected PaymentSettings $payment_settings;
    protected DccApplies $dcc_applies;
    protected DCCProductStatus $dcc_status;
    protected CardPaymentsConfiguration $dcc_configuration;
    /**
     * The list of local apm methods.
     *
     * @var array<string, array>
     */
    protected array $local_apms;
    public function __construct(array $settings, PaymentSettings $payment_settings, DccApplies $dcc_applies, DCCProductStatus $dcc_status, CardPaymentsConfiguration $dcc_configuration, array $local_apms)
    {
        $this->settings = $settings;
        $this->payment_settings = $payment_settings;
        $this->dcc_applies = $dcc_applies;
        $this->dcc_status = $dcc_status;
        $this->local_apms = $local_apms;
        $this->dcc_configuration = $dcc_configuration;
    }
    public function migrate(): void
    {
        if (isset($this->settings['disable_funding'])) {
            $disable_funding = (array) $this->settings['disable_funding'];
            if (!in_array('venmo', $disable_funding, \true)) {
                $this->payment_settings->toggle_method_state('venmo', \true);
            }
            if (!empty($this->settings['allow_local_apm_gateways'])) {
                foreach ($this->local_apms as $apm) {
                    if (!in_array($apm['id'], $disable_funding, \true)) {
                        $this->payment_settings->toggle_method_state($apm['id'], \true);
                    }
                }
            }
        }
        if ($this->is_bcdc_enabled_for_acdc_merchant()) {
            update_option(self::OPTION_NAME_BCDC_MIGRATION_OVERRIDE, \true);
        }
        foreach ($this->map() as $old_key => $method_name) {
            if (!empty($this->settings[$old_key])) {
                $this->payment_settings->toggle_method_state($method_name, \true);
            }
        }
        $this->payment_settings->save();
    }
    /**
     * Maps old setting keys to new payment method names.
     *
     * @return array<string, string>
     */
    protected function map(): array
    {
        return array('dcc_enabled' => CreditCardGateway::ID, 'axo_enabled' => AxoGateway::ID, 'applepay_button_enabled' => ApplePayGateway::ID, 'googlepay_button_enabled' => GooglePayGateway::ID, 'pay_later_button_enabled' => 'pay-later');
    }
    /**
     * Checks if BCDC is enabled for ACDC merchant.
     *
     * This method verifies two conditions:
     * 1. The merchant is an ACDC merchant - determined by
     *    checking if DCC applies for the current country/currency and DCC status is active
     * 2. The BCDC is enabled
     *
     * @return bool True if BCDC is enabled for ACDC merchant, false otherwise.
     */
    public function is_bcdc_enabled_for_acdc_merchant(): bool
    {
        $is_acdc_merchant = $this->dcc_applies->for_country_currency() && $this->dcc_status->is_active();
        if (!$is_acdc_merchant) {
            return \false;
        }
        if ($this->dcc_configuration->is_acdc_enabled()) {
            return \false;
        }
        return !in_array('card', $this->settings['disable_funding'] ?? array(), \true);
    }
}
