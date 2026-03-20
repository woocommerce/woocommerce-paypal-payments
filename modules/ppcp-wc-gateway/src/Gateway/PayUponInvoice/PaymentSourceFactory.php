<?php

/**
 * PUI payment source factory.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Gateway\PayUponInvoice
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\WcGateway\Gateway\PayUponInvoice;

use WC_Order;
use WooCommerce\PayPalCommerce\Settings\Data\PaymentSettings;
/**
 * Class PaymentSourceFactory.
 */
class PaymentSourceFactory
{
    /**
     * The payment settings.
     *
     * @var PaymentSettings
     */
    private PaymentSettings $payment_settings;
    /**
     * PaymentSourceFactory constructor.
     *
     * @param PaymentSettings $payment_settings The payment settings.
     */
    public function __construct(PaymentSettings $payment_settings)
    {
        $this->payment_settings = $payment_settings;
    }
    /**
     * Create a PUI payment source from a WC order.
     *
     * @param WC_Order $order The WC order.
     * @param string   $birth_date The birth date.
     * @return PaymentSource
     */
    public function from_wc_order(WC_Order $order, string $birth_date)
    {
        $address = $order->get_address();
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $phone = wc_clean(wp_unslash($_POST['billing_phone'] ?? ''));
        if (!$phone) {
            $phone = $address['phone'] ?? '';
        }
        $phone_country_code = WC()->countries->get_country_calling_code($address['country']);
        $phone_country_code = is_array($phone_country_code) && !empty($phone_country_code) ? $phone_country_code[0] : $phone_country_code;
        if (is_string($phone_country_code) && '' !== $phone_country_code) {
            $phone_country_code = substr($phone_country_code, strlen('+')) ?: '';
        } else {
            $phone_country_code = '';
        }
        $merchant_name = $this->payment_settings->get_pui_brand_name();
        $logo_url = $this->payment_settings->get_pui_logo_url();
        $customer_service_instructions = $this->payment_settings->get_pui_customer_service_instructions();
        return new \WooCommerce\PayPalCommerce\WcGateway\Gateway\PayUponInvoice\PaymentSource($address['first_name'] ?? '', $address['last_name'] ?? '', $address['email'] ?? '', $birth_date, preg_replace('/[^0-9]/', '', $phone) ?? '', $phone_country_code, $address['address_1'] ?? '', $address['city'] ?? '', $address['postcode'] ?? '', $address['country'] ?? '', 'de-DE', $merchant_name, $logo_url, array($customer_service_instructions));
    }
}
