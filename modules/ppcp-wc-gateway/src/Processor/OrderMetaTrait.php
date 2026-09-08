<?php

/**
 * Adds common metadata to the order.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Processor
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\WcGateway\Processor;

use WC_Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Helper\OrderTransient;
use WooCommerce\PayPalCommerce\WcGateway\Helper\Environment;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Shipping;
/**
 * Trait OrderMetaTrait.
 */
trait OrderMetaTrait
{
    /**
     * Adds common metadata to the order.
     *
     * @param WC_Order            $wc_order The WC order to which metadata will be added.
     * @param Order               $order The PayPal order.
     * @param Environment         $environment The environment.
     * @param OrderTransient|null $order_transient The order transient helper.
     */
    public function add_paypal_meta(WC_Order $wc_order, Order $order, Environment $environment, ?OrderTransient $order_transient = null): void
    {
        $wc_order->update_meta_data(PayPalGateway::ORDER_ID_META_KEY, $order->id());
        $wc_order->update_meta_data(PayPalGateway::INTENT_META_KEY, $order->intent());
        $wc_order->update_meta_data(PayPalGateway::ORDER_PAYMENT_MODE_META_KEY, $environment->is_sandbox() ? 'sandbox' : 'live');
        $payment_source = $this->get_payment_source($order);
        if ($payment_source) {
            $wc_order->update_meta_data(PayPalGateway::ORDER_PAYMENT_SOURCE_META_KEY, $payment_source);
        }
        $payer = $order->payer();
        if ($payer && $payment_source && in_array($payment_source, PayPalGateway::PAYMENT_SOURCES_WITH_PAYER_EMAIL, \true)) {
            $payer_email = $payer->email_address();
            if ($payer_email) {
                $wc_order->update_meta_data(PayPalGateway::ORDER_PAYER_EMAIL_META_KEY, $payer_email);
            }
        }
        $this->add_card_details_meta($wc_order, $order);
        $this->add_contact_details_to_wc_order($wc_order, $order);
        $wc_order->save();
        do_action('woocommerce_paypal_payments_woocommerce_order_created', $wc_order, $order);
    }
    /**
     * Adds the custom contact details provided by PayPal via the "Contact Module" integration.
     *
     * @param WC_Order $wc_order The WooCommerce order to update.
     * @param Order    $order    The PayPal order which provides the details.
     */
    private function add_contact_details_to_wc_order(WC_Order $wc_order, Order $order): void
    {
        $shipping_details = $this->get_shipping_details($order);
        if (!$shipping_details) {
            return;
        }
        $contact_email = $shipping_details->email_address();
        $contact_phone = $shipping_details->phone_number();
        $added = \false;
        if ($contact_email && is_email($contact_email)) {
            $billing_email = $wc_order->get_billing_email();
            if ($billing_email && $billing_email !== $contact_email) {
                $wc_order->update_meta_data(PayPalGateway::CONTACT_EMAIL_META_KEY, $contact_email);
                $wc_order->update_meta_data(PayPalGateway::ORIGINAL_EMAIL_META_KEY, $billing_email);
                $added = \true;
            }
        }
        if ($contact_phone) {
            $billing_phone = $wc_order->get_billing_phone();
            $contact_phone_number = $contact_phone->national_number();
            if ($billing_phone && $billing_phone !== $contact_phone_number) {
                $wc_order->update_meta_data(PayPalGateway::CONTACT_PHONE_META_KEY, $contact_phone_number);
                $wc_order->update_meta_data(PayPalGateway::ORIGINAL_PHONE_META_KEY, $billing_phone);
                $added = \true;
            }
        }
        if ($added) {
            do_action('woocommerce_paypal_payments_contacts_added', $wc_order, $order);
        }
    }
    /**
     * Returns the shipping address details for the order.
     *
     * @param Order $order The PayPal order that contains potential shipping information.
     * @return ?Shipping The shipping details, or null if none present.
     */
    private function get_shipping_details(Order $order): ?Shipping
    {
        foreach ($order->purchase_units() as $unit) {
            $shipping = $unit->shipping();
            if ($shipping) {
                return $shipping;
            }
        }
        return null;
    }
    /**
     * Returns the payment source type or null,
     *
     * @param Order $order The PayPal order.
     * @return string|null
     */
    private function get_payment_source(Order $order): ?string
    {
        $source = $order->payment_source();
        if ($source) {
            return $source->name();
        }
        return null;
    }
    /**
     * Persists the card brand and last 4 digits for card-backed payment sources.
     */
    private function add_card_details_meta(WC_Order $wc_order, Order $order): void
    {
        $payment_source = $order->payment_source();
        if (!$payment_source) {
            return;
        }
        $properties = $payment_source->properties();
        // Wallets nest the card details under "card"; the direct card source exposes them at the top level.
        $card = isset($properties->card) ? $properties->card : $properties;
        $brand = isset($card->brand) ? (string) $card->brand : '';
        $last_digits = isset($card->last_digits) ? (string) $card->last_digits : '';
        if ($brand !== '') {
            $wc_order->update_meta_data(PayPalGateway::ORDER_CARD_BRAND_META_KEY, $brand);
        }
        if ($last_digits !== '') {
            $wc_order->update_meta_data(PayPalGateway::ORDER_CARD_LAST_DIGITS_META_KEY, $last_digits);
        }
    }
}
