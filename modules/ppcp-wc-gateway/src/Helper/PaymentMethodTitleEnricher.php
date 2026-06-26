<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\WcGateway\Helper;

use WC_Order;
use WooCommerce\PayPalCommerce\Applepay\ApplePayGateway;
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
        if ('' === $detail) {
            return $title;
        }
        // Avoid appending the detail twice if it is already present in the title.
        if (\false !== strpos($title, $detail)) {
            return $title;
        }
        return sprintf('%1$s (%2$s)', $title, $detail);
    }
    /**
     * Builds the contextual detail string for the order, or an empty string when unavailable.
     */
    private function build_detail(WC_Order $order): string
    {
        $source = (string) $order->get_meta(PayPalGateway::ORDER_PAYMENT_SOURCE_META_KEY);
        if ('paypal' === $source) {
            $email = sanitize_email((string) $order->get_meta(PayPalGateway::ORDER_PAYER_EMAIL_META_KEY));
            return $email;
        }
        if (in_array($source, self::CARD_SOURCES, \true)) {
            $brand = (string) $order->get_meta(PayPalGateway::ORDER_CARD_BRAND_META_KEY);
            $last_digits = (string) $order->get_meta(PayPalGateway::ORDER_CARD_LAST_DIGITS_META_KEY);
            if ('' === $brand || '' === $last_digits) {
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
