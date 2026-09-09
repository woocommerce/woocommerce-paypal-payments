<?php

/**
 * The difference between the total a wallet sheet quoted and the one charged.
 *
 * A sheet quotes tax against the shipping address, and CartQuoteEndpoint
 * re-prices against the card's real one and refuses an increase, so what can remain
 * is a decrease. Charging less than the sheet showed is still a surprise, so the
 * quote is noted on the order and explained to the shopper.
 *
 * @package WooCommerce\PayPalCommerce\SdkV6\Helper
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\SdkV6\Helper;

use WC_Order;
class RecordedQuote extends \WooCommerce\PayPalCommerce\SdkV6\Helper\SessionRecord
{
    protected const SESSION_KEY = 'ppcp_wallet_quoted_total';
    public const ORDER_META_KEY = '_ppcp_wallet_quoted_total';
    /**
     * Records what the sheet displayed, at the moment the shopper authorizes.
     *
     * @param string $quoted_total The total the sheet showed, as a decimal string.
     */
    public function set(string $quoted_total): void
    {
        if ('' !== $quoted_total) {
            $this->remember($quoted_total);
        }
    }
    /**
     * Moves the quote onto the order, recording it only when it differs.
     *
     * Consumed either way, or it would annotate the next order too.
     *
     * @param WC_Order $order The order just created for this payment.
     */
    public function apply_to_order(WC_Order $order): void
    {
        $quoted = $this->get();
        $this->forget();
        $charged = (float) $order->get_total();
        if ('' === $quoted || $this->cents((float) $quoted) === $this->cents($charged)) {
            return;
        }
        $order->add_order_note(sprintf(
            /* translators: 1: total shown in the wallet payment sheet, 2: total of this order */
            __('The wallet payment sheet showed %1$s and this order totals %2$s. The sheet estimated tax from the shipping address; the final amount uses the card\'s billing address.', 'woocommerce-paypal-payments'),
            $this->format((float) $quoted, $order),
            $this->format($charged, $order)
        ));
        $order->update_meta_data(self::ORDER_META_KEY, $quoted);
        $order->save();
    }
    /**
     * Explains a reduction on the order-received page.
     *
     * Only a reduction: an increase never reaches an order, because the endpoint
     * refuses the payment instead.
     *
     * @param string   $text  The message WooCommerce is about to show.
     * @param WC_Order $order The order being confirmed.
     * @return string
     */
    public function thank_you_message(string $text, WC_Order $order): string
    {
        $quoted = (float) $order->get_meta(self::ORDER_META_KEY);
        $charged = (float) $order->get_total();
        $savings = $this->cents($quoted) - $this->cents($charged);
        if ($savings <= 0) {
            return $text;
        }
        return trim($text . ' ' . sprintf(
            /* translators: 1: amount the shopper saved, 2: total shown in the wallet payment sheet, 3: total charged */
            __('You saved %1$s. We estimated %2$s at checkout, but the tax for your billing address is lower, so your actual charge is %3$s.', 'woocommerce-paypal-payments'),
            $this->format($savings / 100, $order),
            $this->format($quoted, $order),
            $this->format($charged, $order)
        ));
    }
    /**
     * The recorded quote, or an empty string when none applies.
     */
    private function get(): string
    {
        $quoted = $this->remembered();
        return is_string($quoted) ? $quoted : '';
    }
    /**
     * An amount as an integer number of cents, so two equal amounts compare equal.
     *
     * @param float $amount The amount to convert.
     */
    private function cents(float $amount): int
    {
        return (int) round($amount * 100);
    }
    /**
     * An amount as the shopper reads it, stripped for the plain-text places it
     * appears in: an order note and the order-received message.
     *
     * @param float    $amount The amount to format.
     * @param WC_Order $order  The order whose currency applies.
     */
    private function format(float $amount, WC_Order $order): string
    {
        return html_entity_decode(wp_strip_all_tags(wc_price($amount, array('currency' => $order->get_currency()))), \ENT_QUOTES, 'UTF-8');
    }
}
