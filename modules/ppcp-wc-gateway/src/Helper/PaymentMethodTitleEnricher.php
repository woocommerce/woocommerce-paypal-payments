<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\WcGateway\Helper;

use WC_Order;
use WooCommerce\PayPalCommerce\Applepay\ApplePayGateway;
use WooCommerce\PayPalCommerce\Assets\AssetGetter;
use WooCommerce\PayPalCommerce\Googlepay\GooglePayGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
/**
 * Enriches the payment method title with contextual payment details
 * (payer email or card brand + last 4 digits) for supported gateways.
 */
class PaymentMethodTitleEnricher
{
    /**
     * Gateways for which the title is enriched.
     *
     * @var string[]
     */
    private const SUPPORTED_GATEWAYS = array(PayPalGateway::ID, CreditCardGateway::ID, ApplePayGateway::ID, GooglePayGateway::ID);
    /**
     * Payment source names that carry card details.
     *
     * @var string[]
     */
    private const CARD_SOURCES = array('card', 'apple_pay', 'google_pay');
    /**
     * Maps PayPal card brand identifiers to display labels.
     *
     * @var array<string, string>
     */
    private const BRAND_LABELS = array('VISA' => 'Visa', 'MASTERCARD' => 'Mastercard', 'AMEX' => 'American Express', 'AMERICAN_EXPRESS' => 'American Express', 'DISCOVER' => 'Discover', 'DINERS' => 'Diners Club', 'JCB' => 'JCB', 'MAESTRO' => 'Maestro', 'SOLO' => 'Solo', 'SWITCH' => 'Switch', 'UNIONPAY' => 'UnionPay');
    /**
     * Maps PayPal card brand identifiers to the bundled icon file name.
     *
     * Brands without a bundled icon are intentionally absent.
     *
     * @var array<string, string>
     */
    private const BRAND_ICONS = array('VISA' => 'visa', 'MASTERCARD' => 'mastercard', 'AMEX' => 'amex', 'AMERICAN_EXPRESS' => 'amex', 'DISCOVER' => 'discover', 'JCB' => 'jcb', 'ELO' => 'elo', 'HIPER' => 'hiper');
    /**
     * Maps payment sources that have their own logo to the bundled icon file name.
     *
     * @var array<string, string>
     */
    private const SOURCE_ICONS = array('paypal' => 'paypal', 'venmo' => 'venmo');
    private AssetGetter $asset_getter;
    public function __construct(AssetGetter $asset_getter)
    {
        $this->asset_getter = $asset_getter;
    }
    /**
     * Returns the payment method title enriched with contextual payment details,
     * or the original title when enrichment is disabled or no details are available.
     *
     * @param string   $title The current payment method title.
     * @param WC_Order $order The order the title belongs to.
     */
    public function enrich(string $title, WC_Order $order): string
    {
        /**
         * Whether to enrich the payment method title with contextual payment details.
         *
         * @param bool     $enrich Whether to enrich the title. Default true.
         * @param WC_Order $order  The order the title belongs to.
         */
        if (!apply_filters('woocommerce_paypal_payments_enrich_payment_method_title', \true, $order)) {
            return $title;
        }
        if (!in_array($order->get_payment_method(), self::SUPPORTED_GATEWAYS, \true)) {
            return $title;
        }
        $detail = $this->build_detail($order);
        /**
         * The contextual detail appended to the payment method title.
         *
         * Also applied when the plugin found no detail, so an empty value can be
         * replaced with a custom one. Return an empty string to keep the title
         * unchanged without disabling enrichment globally.
         *
         * @param string   $detail The detail built from the order, or an empty string.
         * @param WC_Order $order  The order the title belongs to.
         */
        $detail = (string) apply_filters('woocommerce_paypal_payments_payment_method_title_detail', $detail, $order);
        if ($detail === '') {
            return $title;
        }
        // Avoid appending the detail twice if it is already present in the title.
        if (\false !== strpos($title, $detail)) {
            return $title;
        }
        $source = $this->payment_source($order);
        $brand = $this->card_brand($order);
        /**
         * HTML for an icon prepended to the payment method title detail.
         *
         * Defaults to an empty string, so no icon is rendered unless a callback opts in.
         *
         * Note that the payment method title may be used in contexts where markup is unsafe —
         * plain-text emails, PDF invoices, REST responses, ... — so a callback is responsible
         * for checking the context. Front-end order views also run the title through
         * wp_kses_post(), which strips disallowed inline CSS properties, so prefer a class
         * over inline styles.
         *
         * @param string   $icon_html The icon markup. Default empty string.
         * @param string   $icon_url  URL of the bundled icon for this source and brand, or empty.
         * @param string   $source    The raw payment source meta value, e.g. "paypal" or "card".
         * @param string   $brand     The raw card brand, e.g. "VISA". Empty for non-card sources.
         * @param WC_Order $order     The order the title belongs to.
         */
        $icon_html = (string) apply_filters('woocommerce_paypal_payments_payment_method_title_icon', '', $this->get_icon_url($source, $brand), $source, $brand, $order);
        if ($icon_html !== '') {
            $detail = $icon_html . ' ' . $detail;
        }
        $enriched = sprintf('%1$s (%2$s)', $title, $detail);
        /**
         * The payment method title after the detail was appended.
         *
         * Only applied when a detail is actually appended, never on the paths that
         * return the title unchanged.
         *
         * @param string   $enriched The assembled title, e.g. "PayPal (buyer@example.com)".
         * @param string   $title    The original payment method title.
         * @param string   $detail   The appended detail, already filtered.
         * @param WC_Order $order    The order the title belongs to.
         */
        return (string) apply_filters('woocommerce_paypal_payments_enriched_payment_method_title', $enriched, $title, $detail, $order);
    }
    /**
     * Returns the URL of the bundled icon for the given payment source and card brand,
     * or an empty string when no icon is bundled for them.
     *
     * @param string $source The payment source, e.g. "paypal" or "card".
     * @param string $brand  The card brand, e.g. "VISA". Ignored for non-card sources.
     */
    public function get_icon_url(string $source, string $brand): string
    {
        if (isset(self::SOURCE_ICONS[$source])) {
            return $this->asset_getter->get_static_asset_url('images/' . self::SOURCE_ICONS[$source] . '.svg');
        }
        if (in_array($source, self::CARD_SOURCES, \true)) {
            $file = self::BRAND_ICONS[strtoupper($brand)] ?? '';
            return $file === '' ? '' : $this->asset_getter->get_static_asset_url("images/{$file}.svg");
        }
        return '';
    }
    /**
     * Builds the contextual detail string for the order, or an empty string when unavailable.
     */
    private function build_detail(WC_Order $order): string
    {
        $source = $this->payment_source($order);
        if ($source === 'paypal') {
            $email = sanitize_email((string) $order->get_meta(PayPalGateway::ORDER_PAYER_EMAIL_META_KEY));
            return $email;
        }
        if (in_array($source, self::CARD_SOURCES, \true)) {
            $brand = $this->card_brand($order);
            $last_digits = (string) $order->get_meta(PayPalGateway::ORDER_CARD_LAST_DIGITS_META_KEY);
            if ($brand === '' || $last_digits === '') {
                return '';
            }
            return sprintf(
                /* translators: %1$s: card brand, %2$s: card last 4 digits. */
                __('%1$s ending in %2$s', 'woocommerce-paypal-payments'),
                $this->normalize_brand($brand),
                $last_digits
            );
        }
        return '';
    }
    /**
     * Returns the payment source stored on the order, e.g. "paypal" or "card".
     */
    private function payment_source(WC_Order $order): string
    {
        return (string) $order->get_meta(PayPalGateway::ORDER_PAYMENT_SOURCE_META_KEY);
    }
    /**
     * Returns the raw card brand stored on the order, e.g. "VISA", or an empty string.
     */
    private function card_brand(WC_Order $order): string
    {
        return (string) $order->get_meta(PayPalGateway::ORDER_CARD_BRAND_META_KEY);
    }
    /**
     * Normalizes a PayPal card brand identifier to a display label.
     *
     * @param string $brand The brand identifier, e.g. "VISA".
     */
    private function normalize_brand(string $brand): string
    {
        $key = strtoupper($brand);
        if (isset(self::BRAND_LABELS[$key])) {
            return self::BRAND_LABELS[$key];
        }
        return ucfirst(strtolower(str_replace('_', ' ', $brand)));
    }
}
