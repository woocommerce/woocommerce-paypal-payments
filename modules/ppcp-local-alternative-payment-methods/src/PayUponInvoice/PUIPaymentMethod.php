<?php

/**
 * The Pay upon Invoice block payment method type.
 *
 * @package WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\PayUponInvoice
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\PayUponInvoice;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use WooCommerce\PayPalCommerce\Assets\AssetGetter;
/**
 * Class PUIPaymentMethod.
 *
 * Custom block integration — PUI cannot reuse the shared APM block component because it
 * needs extra checkout fields (birth date, phone) and Ratepay legal terms.
 */
class PUIPaymentMethod extends AbstractPaymentMethodType
{
    private AssetGetter $asset_getter;
    private string $version;
    private \WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\PayUponInvoice\PayUponInvoiceGateway $gateway;
    public function __construct(AssetGetter $asset_getter, string $version, \WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\PayUponInvoice\PayUponInvoiceGateway $gateway)
    {
        $this->asset_getter = $asset_getter;
        $this->version = $version;
        $this->gateway = $gateway;
        $this->name = \WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\PayUponInvoice\PayUponInvoiceGateway::ID;
    }
    public function initialize(): void
    {
    }
    public function is_active()
    {
        return \true;
    }
    public function get_payment_method_script_handles()
    {
        wp_register_script('ppcp-pui-payment-method', $this->asset_getter->get_asset_url('pui-payment-method.js'), array(), $this->version, \true);
        return array('ppcp-pui-payment-method');
    }
    public function get_payment_method_data()
    {
        $site_language = get_bloginfo('language');
        $site_country_code = explode('-', $site_language)[0] ?? '';
        // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
        $button_text = apply_filters('woocommerce_order_button_text', __('Place order', 'woocommerce'));
        return array('id' => $this->name, 'title' => $this->gateway->title, 'description' => $this->gateway->description, 'icon' => $this->gateway->icon, 'requiresBirthDate' => \true, 'requiresPhone' => $this->requires_phone(), 'siteLanguage' => $site_country_code, 'placeOrderButtonText' => $button_text, 'ratepayTerms' => array('de' => $this->ratepay_terms_de($button_text), 'en' => $this->ratepay_terms_en($button_text)));
    }
    /**
     * Whether PUI must collect the phone number itself (mirrors the classic checkout logic).
     *
     * @return bool
     */
    private function requires_phone(): bool
    {
        if (!function_exists('WC') || !WC()->checkout()) {
            return \true;
        }
        $checkout_fields = WC()->checkout()->get_checkout_fields();
        $billing_fields = $checkout_fields['billing'] ?? array();
        $phone_required = $billing_fields['billing_phone']['required'] ?? \false;
        return !array_key_exists('billing_phone', $billing_fields) || $phone_required === \false;
    }
    /**
     * German Ratepay legal terms.
     *
     * @param string $button_text The place-order button text.
     * @return string
     */
    private function ratepay_terms_de(string $button_text): string
    {
        return 'Mit Klicken auf ' . $button_text . ' akzeptieren Sie die <a href="https://www.ratepay.com/legal-payment-terms" target="_blank">Ratepay Zahlungsbedingungen</a> und erklären sich mit der Durchführung einer <a href="https://www.ratepay.com/legal-payment-dataprivacy" target="_blank">Risikoprüfung durch Ratepay</a>, unseren Partner, einverstanden. Sie akzeptieren auch PayPals <a href="https://www.paypal.com/de/webapps/mpp/ua/privacy-full?locale.x=de_DE&_ga=1.228729434.718583817.1563460395" target="_blank">Datenschutzerklärung</a>. Falls Ihre Transaktion per Kauf auf Rechnung erfolgreich abgewickelt werden kann, wird der Kaufpreis an Ratepay abgetreten und Sie dürfen nur an Ratepay überweisen, nicht an den Händler.';
    }
    /**
     * English Ratepay legal terms.
     *
     * @param string $button_text The place-order button text.
     * @return string
     */
    private function ratepay_terms_en(string $button_text): string
    {
        return 'By clicking on ' . $button_text . ', you agree to the <a href="https://www.ratepay.com/legal-payment-terms" target="_blank">terms of payment</a> and <a href="https://www.ratepay.com/legal-payment-dataprivacy">performance of a risk check</a> from the payment partner, Ratepay. You also agree to PayPal’s <a href="https://www.paypal.com/de/webapps/mpp/ua/privacy-full?locale.x=eng_DE&_ga=1.267010504.718583817.1563460395">privacy statement</a>. If your request to purchase upon invoice is accepted, the purchase price claim will be assigned to Ratepay, and you may only pay Ratepay, not the merchant.';
    }
}
