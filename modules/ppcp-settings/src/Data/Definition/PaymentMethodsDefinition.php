<?php

/**
 * Payment Methods Definitions
 *
 * @package WooCommerce\PayPalCommerce\Settings\Data\Definition
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Settings\Data\Definition;

use WooCommerce\PayPalCommerce\Applepay\ApplePayGateway;
use WooCommerce\PayPalCommerce\Axo\Gateway\AxoGateway;
use WooCommerce\PayPalCommerce\Googlepay\GooglePayGateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\BancontactGateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\BlikGateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\EPSGateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\IDealGateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\MultibancoGateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\MyBankGateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\P24Gateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\PWCGateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\TrustlyGateway;
use WooCommerce\PayPalCommerce\Settings\Data\PaymentSettings;
use WooCommerce\PayPalCommerce\Settings\Data\GeneralSettings;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CardButtonGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\OXXO\OXXO;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayUponInvoice\PayUponInvoiceGateway;
/**
 * Class PaymentMethodsDefinition
 *
 * Provides a list of all payment methods that are available in the settings UI.
 */
class PaymentMethodsDefinition
{
    /**
     * Data model that manages the payment method configuration.
     *
     * @var PaymentSettings
     */
    private PaymentSettings $settings;
    /**
     * Data model for the general plugin settings, used to access flags like
     * "own brand only" to modify the payment method details.
     *
     * @var GeneralSettings
     */
    private GeneralSettings $general_settings;
    /**
     * Conflict notices for Axo gateway.
     *
     * @var array
     */
    private array $axo_conflicts_notices;
    /**
     * List of WooCommerce payment gateways.
     *
     * @var array|null
     */
    private ?array $wc_gateways = null;
    /**
     * Constructor.
     *
     * @param PaymentSettings $settings              Payment methods data model.
     * @param GeneralSettings $general_settings      General plugin settings model.
     * @param array           $axo_conflicts_notices Conflicts notices for Axo.
     */
    public function __construct(PaymentSettings $settings, GeneralSettings $general_settings, array $axo_conflicts_notices = array())
    {
        $this->settings = $settings;
        $this->general_settings = $general_settings;
        $this->axo_conflicts_notices = $axo_conflicts_notices;
    }
    /**
     * Returns the payment method definitions.
     *
     * @return array
     */
    public function get_definitions(): array
    {
        // Refresh the WooCommerce gateway details before we build the definitions.
        $this->wc_gateways = WC()->payment_gateways()->payment_gateways();
        $all_methods = array_merge($this->group_paypal_methods(), $this->group_card_methods(), $this->group_apms());
        $result = array();
        foreach ($all_methods as $method) {
            $method_id = $method['id'];
            $result[$method_id] = $this->build_method_definition($method_id, $method['title'], $method['description'], $method['icon'], $method['fields'] ?? array(), $method['warningMessages'] ?? array());
        }
        return $result;
    }
    /**
     * Returns a new payment method configuration array that contains all
     * common attributes which must be present in every method definition.
     *
     * @param string      $gateway_id                 The payment method ID.
     * @param string      $title                      Admin-side payment method title.
     * @param string      $description                Admin-side info about the payment method.
     * @param string      $icon                       Admin-side icon of the payment method.
     * @param array|false $fields                     Optional. Additional fields to display in the
     *                                                edit modal. Setting this to false omits all
     *                                                fields.
     * @param array       $warning_messages           Optional. Warning messages to display in the
     *                                                UI.
     * @return array Payment method definition.
     */
    private function build_method_definition(string $gateway_id, string $title, string $description, string $icon, $fields = array(), array $warning_messages = array()): array
    {
        $gateway = $this->wc_gateways[$gateway_id] ?? null;
        $gateway_title = $gateway ? $gateway->get_title() : $title;
        $gateway_description = $gateway ? $gateway->description : $description;
        $enabled = $this->settings->is_method_enabled($gateway_id);
        $config = array('id' => $gateway_id, 'enabled' => $enabled, 'title' => str_replace('&amp;', '&', $gateway_title), 'description' => $gateway_description, 'icon' => $icon, 'itemTitle' => $title, 'itemDescription' => $description, 'warningMessages' => $warning_messages);
        if (is_array($fields)) {
            $base_fields = array('checkoutPageTitle' => array('type' => 'text', 'default' => $gateway_title, 'label' => __('Checkout page title', 'woocommerce-paypal-payments')));
            if (CreditCardGateway::ID !== $gateway_id) {
                $base_fields['checkoutPageDescription'] = array('type' => 'text', 'default' => $gateway_description, 'label' => __('Checkout page description', 'woocommerce-paypal-payments'));
            }
            $config['fields'] = array_merge($base_fields, $fields);
        }
        return $config;
    }
    // Payment method groups.
    /**
     * Defines PayPal's branded payment methods; not affected by the "own_brand_only" setting.
     *
     * @return array
     */
    public function group_paypal_methods(): array
    {
        $group = array(array('id' => PayPalGateway::ID, 'title' => __('PayPal', 'woocommerce-paypal-payments'), 'description' => __('Our all-in-one checkout solution lets you offer PayPal, Venmo, Pay Later options, and more to help maximize conversion.', 'woocommerce-paypal-payments'), 'icon' => 'payment-method-paypal', 'fields' => array('paypalShowLogo' => array('type' => 'toggle', 'default' => $this->settings->get_paypal_show_logo(), 'label' => __('Show logo', 'woocommerce-paypal-payments')))), array('id' => 'venmo', 'title' => __('Venmo', 'woocommerce-paypal-payments'), 'description' => __('Offer Venmo at checkout to millions of active users.', 'woocommerce-paypal-payments'), 'icon' => 'payment-method-venmo', 'fields' => \false), array('id' => 'pay-later', 'title' => __('Pay Later', 'woocommerce-paypal-payments'), 'description' => __('Get paid in full at checkout while giving your customers the flexibility to pay in installments over time with no late fees.', 'woocommerce-paypal-payments'), 'icon' => 'payment-method-paypal', 'fields' => \false));
        // This CardButtonGateway is a branded gateway!
        $group[] = array('id' => CardButtonGateway::ID, 'title' => __('Credit and debit card payments', 'woocommerce-paypal-payments'), 'description' => __("Accept all major credit and debit cards - even if your customer doesn't have a PayPal account . ", 'woocommerce-paypal-payments'), 'icon' => 'payment-method-cards');
        return apply_filters('woocommerce_paypal_payments_gateway_group_paypal', $group);
    }
    /**
     * Define embedded payment methods, which are only available in whitelabel mode.
     *
     * @return array
     */
    public function group_card_methods(): array
    {
        $group = array();
        if (!$this->general_settings->own_brand_only()) {
            $group[] = array('id' => CreditCardGateway::ID, 'title' => __('Advanced Credit and Debit Card Payments', 'woocommerce-paypal-payments'), 'description' => __("Present custom credit and debit card fields to your payers so they can pay with credit and debit cards using your site's branding.", 'woocommerce-paypal-payments'), 'icon' => 'payment-method-advanced-cards', 'fields' => array('cardholderName' => array('type' => 'toggle', 'default' => $this->settings->get_cardholder_name(), 'label' => __('Display cardholder name', 'woocommerce-paypal-payments'))));
            $group[] = array('id' => AxoGateway::ID, 'title' => __('Fastlane by PayPal', 'woocommerce-paypal-payments'), 'description' => __("Tap into the scale and trust of PayPal's customer network to recognize shoppers and make guest checkout more seamless than ever.", 'woocommerce-paypal-payments'), 'icon' => 'payment-method-fastlane', 'fields' => array('fastlaneDisplayWatermark' => array('type' => 'toggle', 'default' => $this->settings->get_fastlane_display_watermark(), 'label' => __('Display Fastlane Watermark', 'woocommerce-paypal-payments'))), 'warningMessages' => $this->axo_conflicts_notices);
            $group[] = array('id' => ApplePayGateway::ID, 'title' => __('Apple Pay', 'woocommerce-paypal-payments'), 'description' => __('Allow customers to pay via their Apple Pay digital wallet.', 'woocommerce-paypal-payments'), 'icon' => 'payment-method-apple-pay');
            $group[] = array('id' => GooglePayGateway::ID, 'title' => __('Google Pay', 'woocommerce-paypal-payments'), 'description' => __('Allow customers to pay via their Google Pay digital wallet.', 'woocommerce-paypal-payments'), 'icon' => 'payment-method-google-pay');
        }
        return apply_filters('woocommerce_paypal_payments_gateway_group_cards', $group);
    }
    /**
     * Get default titles and descriptions for APM gateways.
     * Single source of truth for all APM gateway metadata.
     *
     * @return array Array of default settings keyed by gateway ID.
     */
    public static function get_apm_defaults(): array
    {
        return array(PWCGateway::ID => array('method_title' => __('Pay with Crypto', 'woocommerce-paypal-payments'), 'method_description' => __('Accept cryptocurrency payments through PayPal, supporting various digital currencies for global customers.', 'woocommerce-paypal-payments'), 'title' => __('Pay with Crypto', 'woocommerce-paypal-payments'), 'description' => __('Clicking "Place order" will redirect you to PayPal\'s encrypted checkout to complete your cryptocurrency purchase.', 'woocommerce-paypal-payments')), BancontactGateway::ID => array('method_title' => __('Bancontact (via PayPal)', 'woocommerce-paypal-payments'), 'method_description' => __('A popular and trusted electronic payment method in Belgium, used by Belgian customers with Bancontact cards issued by local banks. Transactions are processed in EUR.', 'woocommerce-paypal-payments'), 'title' => __('Bancontact', 'woocommerce-paypal-payments'), 'description' => ''), BlikGateway::ID => array('method_title' => __('Blik (via PayPal)', 'woocommerce-paypal-payments'), 'method_description' => __('A widely used mobile payment method in Poland, allowing Polish customers to pay directly via their banking apps. Transactions are processed in PLN.', 'woocommerce-paypal-payments'), 'title' => __('Blik', 'woocommerce-paypal-payments'), 'description' => ''), EPSGateway::ID => array('method_title' => __('EPS (via PayPal)', 'woocommerce-paypal-payments'), 'method_description' => __('An online payment method in Austria, enabling Austrian buyers to make secure payments directly through their bank accounts. Transactions are processed in EUR.', 'woocommerce-paypal-payments'), 'title' => __('EPS', 'woocommerce-paypal-payments'), 'description' => ''), IDealGateway::ID => array('method_title' => __('iDeal (via PayPal)', 'woocommerce-paypal-payments'), 'method_description' => __('The most common payment method in the Netherlands, allowing Dutch buyers to pay directly through their preferred bank. Transactions are processed in EUR.', 'woocommerce-paypal-payments'), 'title' => __('iDeal', 'woocommerce-paypal-payments'), 'description' => ''), MyBankGateway::ID => array('method_title' => __('MyBank (via PayPal)', 'woocommerce-paypal-payments'), 'method_description' => __('A European online banking payment solution primarily used in Italy, enabling customers to make secure bank transfers during checkout. Transactions are processed in EUR.', 'woocommerce-paypal-payments'), 'title' => __('MyBank', 'woocommerce-paypal-payments'), 'description' => ''), P24Gateway::ID => array('method_title' => __('Przelewy24 (via PayPal)', 'woocommerce-paypal-payments'), 'method_description' => __('A popular online payment gateway in Poland, offering various payment options for Polish customers. Transactions can be processed in PLN or EUR.', 'woocommerce-paypal-payments'), 'title' => __('Przelewy24', 'woocommerce-paypal-payments'), 'description' => ''), TrustlyGateway::ID => array('method_title' => __('Trustly (via PayPal)', 'woocommerce-paypal-payments'), 'method_description' => __('A European payment method that allows buyers to make payments directly from their bank accounts, suitable for customers across multiple European countries. Supported currencies include EUR, DKK, SEK, GBP, and NOK.', 'woocommerce-paypal-payments'), 'title' => __('Trustly', 'woocommerce-paypal-payments'), 'description' => ''), MultibancoGateway::ID => array('method_title' => __('Multibanco (via PayPal)', 'woocommerce-paypal-payments'), 'method_description' => __('An online payment method in Portugal, enabling Portuguese buyers to make secure payments directly through their bank accounts. Transactions are processed in EUR.', 'woocommerce-paypal-payments'), 'title' => __('Multibanco', 'woocommerce-paypal-payments'), 'description' => ''), PayUponInvoiceGateway::ID => array('method_title' => __('Pay upon Invoice', 'woocommerce-paypal-payments'), 'method_description' => __('Pay upon Invoice is an invoice payment method in Germany. It is a local buy now, pay later payment method that allows the buyer to place an order, receive the goods, try them, verify they are in good order, and then pay the invoice within 30 days.', 'woocommerce-paypal-payments'), 'title' => __('Pay upon Invoice', 'woocommerce-paypal-payments'), 'description' => ''), OXXO::ID => array('method_title' => __('OXXO', 'woocommerce-paypal-payments'), 'method_description' => __('OXXO is a Mexican chain of convenience stores. *Get PayPal account permission to use OXXO payment functionality by contacting us at (+52) 800–925–0304', 'woocommerce-paypal-payments'), 'title' => __('OXXO', 'woocommerce-paypal-payments'), 'description' => ''));
    }
    /**
     * Builds an array of payment method definitions, which includes details
     * of all APM gateways.
     *
     * @return array List of payment method definitions.
     */
    public function group_apms(): array
    {
        $defaults = self::get_apm_defaults();
        $group = array(array('id' => PWCGateway::ID, 'title' => $defaults[PWCGateway::ID]['title'], 'description' => $defaults[PWCGateway::ID]['method_description'], 'icon' => 'payment-method-pwc'), array('id' => BancontactGateway::ID, 'title' => $defaults[BancontactGateway::ID]['title'], 'description' => $defaults[BancontactGateway::ID]['method_description'], 'icon' => 'payment-method-bancontact'), array('id' => BlikGateway::ID, 'title' => $defaults[BlikGateway::ID]['title'], 'description' => $defaults[BlikGateway::ID]['method_description'], 'icon' => 'payment-method-blik'), array('id' => EPSGateway::ID, 'title' => $defaults[EPSGateway::ID]['title'], 'description' => $defaults[EPSGateway::ID]['method_description'], 'icon' => 'payment-method-eps'), array('id' => IDealGateway::ID, 'title' => $defaults[IDealGateway::ID]['title'], 'description' => $defaults[IDealGateway::ID]['method_description'], 'icon' => 'payment-method-ideal'), array('id' => MyBankGateway::ID, 'title' => $defaults[MyBankGateway::ID]['title'], 'description' => $defaults[MyBankGateway::ID]['method_description'], 'icon' => 'payment-method-mybank'), array('id' => P24Gateway::ID, 'title' => $defaults[P24Gateway::ID]['title'], 'description' => $defaults[P24Gateway::ID]['method_description'], 'icon' => 'payment-method-przelewy24'), array('id' => TrustlyGateway::ID, 'title' => $defaults[TrustlyGateway::ID]['title'], 'description' => $defaults[TrustlyGateway::ID]['method_description'], 'icon' => 'payment-method-trustly'), array('id' => MultibancoGateway::ID, 'title' => $defaults[MultibancoGateway::ID]['title'], 'description' => $defaults[MultibancoGateway::ID]['method_description'], 'icon' => 'payment-method-multibanco'), array('id' => PayUponInvoiceGateway::ID, 'title' => $defaults[PayUponInvoiceGateway::ID]['title'], 'description' => $defaults[PayUponInvoiceGateway::ID]['method_description'], 'icon' => ''), array('id' => OXXO::ID, 'title' => $defaults[OXXO::ID]['title'], 'description' => $defaults[OXXO::ID]['method_description'], 'icon' => 'payment-method-oxxo'));
        return apply_filters('woocommerce_paypal_payments_gateway_group_apm', $group);
    }
}
