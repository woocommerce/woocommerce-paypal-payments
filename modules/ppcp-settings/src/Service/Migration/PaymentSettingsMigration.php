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
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CardButtonGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\OXXO\OXXO;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayUponInvoice\PayUponInvoiceGateway;
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
    protected bool $legacy_pui_enabled;
    protected bool $legacy_oxxo_enabled;
    public function __construct(array $settings, PaymentSettings $payment_settings, DccApplies $dcc_applies, DCCProductStatus $dcc_status, CardPaymentsConfiguration $dcc_configuration, array $local_apms)
    {
        $this->settings = $settings;
        $this->payment_settings = $payment_settings;
        $this->dcc_applies = $dcc_applies;
        $this->dcc_status = $dcc_status;
        $this->local_apms = $local_apms;
        $this->dcc_configuration = $dcc_configuration;
        $pui_option = get_option('woocommerce_' . PayUponInvoiceGateway::ID . '_settings', array());
        $this->legacy_pui_enabled = is_array($pui_option) && ($pui_option['enabled'] ?? 'no') === 'yes';
        $oxxo_option = get_option('woocommerce_' . OXXO::ID . '_settings', array());
        $this->legacy_oxxo_enabled = is_array($oxxo_option) && ($oxxo_option['enabled'] ?? 'no') === 'yes';
    }
    public function migrate(): void
    {
        $disable_funding = (array) ($this->settings['disable_funding'] ?? array());
        if (!in_array('venmo', $disable_funding, \true)) {
            $this->payment_settings->toggle_method_state('venmo', \true);
        }
        foreach ($this->local_apms as $apm) {
            if (!in_array($apm['id'], $disable_funding, \true)) {
                $this->payment_settings->toggle_method_state($apm['id'], \true);
            }
        }
        $card_funding_was_active = !in_array('card', $disable_funding, \true);
        if ($this->is_bcdc_enabled_for_acdc_merchant()) {
            update_option(self::OPTION_NAME_BCDC_MIGRATION_OVERRIDE, \true);
        }
        if ($card_funding_was_active) {
            $this->payment_settings->toggle_method_state(CardButtonGateway::ID, \true);
        }
        foreach ($this->map() as $old_key => $method_name) {
            if (!empty($this->settings[$old_key])) {
                $this->payment_settings->toggle_method_state($method_name, \true);
            }
        }
        $pui_settings = get_option('woocommerce_ppcp-pay-upon-invoice-gateway_settings', array());
        if (is_array($pui_settings)) {
            if (!empty($pui_settings['brand_name'])) {
                $this->payment_settings->set_pui_brand_name($pui_settings['brand_name']);
            }
            if (!empty($pui_settings['logo_url'])) {
                $this->payment_settings->set_pui_logo_url($pui_settings['logo_url']);
            }
            if (!empty($pui_settings['customer_service_instructions'])) {
                $this->payment_settings->set_pui_customer_service_instructions($pui_settings['customer_service_instructions']);
            }
        }
        if ($this->legacy_pui_enabled) {
            $this->payment_settings->toggle_method_state(PayUponInvoiceGateway::ID, \true);
        }
        if ($this->legacy_oxxo_enabled) {
            $this->payment_settings->toggle_method_state(OXXO::ID, \true);
        }
        if (isset($this->settings['dcc_name_on_card'])) {
            $this->payment_settings->set_cardholder_name($this->settings['dcc_name_on_card'] === 'yes');
        }
        if (!empty($this->settings['title'])) {
            $this->payment_settings->set_method_title('ppcp-gateway', $this->settings['title']);
        }
        if (!empty($this->settings['description'])) {
            $this->payment_settings->set_method_description('ppcp-gateway', $this->settings['description']);
        }
        if (!empty($this->settings['dcc_gateway_title'])) {
            $this->payment_settings->set_method_title(CreditCardGateway::ID, $this->settings['dcc_gateway_title']);
        }
        if (!empty($this->settings['dcc_gateway_description'])) {
            $this->payment_settings->set_method_description(CreditCardGateway::ID, $this->settings['dcc_gateway_description']);
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
