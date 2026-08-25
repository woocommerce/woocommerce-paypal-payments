<?php

/**
 * Prices the cart for an address and rate chosen inside a wallet payment sheet.
 *
 * One request per selection, because WooCommerce re-picks the shipping method on
 * every recalculation (see RecordedShippingRate): setting the destination and the rate in
 * separate requests loses the choice, and answering from two responses can show a
 * total and an option list that describe different carts. This endpoint sets both,
 * recalculates, and answers from the one state it leaves behind.
 *
 * @package WooCommerce\PayPalCommerce\SdkV6\Endpoint
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\SdkV6\Endpoint;

use Exception;
use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use WC_Cart;
use WC_Shipping_Rate;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Money;
use WooCommerce\PayPalCommerce\ApiClient\Factory\AmountFactory;
use WooCommerce\PayPalCommerce\OrderEndpoints\Endpoint\AbstractCartEndpoint;
use WooCommerce\PayPalCommerce\OrderEndpoints\Endpoint\RequestData;
use WooCommerce\PayPalCommerce\SdkV6\Helper\RecordedShippingRate;
use WooCommerce\PayPalCommerce\SdkV6\Helper\RecordedQuote;
use WooCommerce\PayPalCommerce\SdkV6\Helper\RecordedTaxBasis;
class WalletShippingEndpoint extends AbstractCartEndpoint
{
    public const ENDPOINT = 'ppc-sdk-v6-wallet-shipping';
    private AmountFactory $amount_factory;
    private RecordedShippingRate $recorded_rate;
    private RecordedTaxBasis $recorded_tax_basis;
    private RecordedQuote $recorded_quote;
    public function __construct(RequestData $request_data, AmountFactory $amount_factory, RecordedShippingRate $recorded_rate, RecordedTaxBasis $recorded_tax_basis, RecordedQuote $recorded_quote, LoggerInterface $logger)
    {
        $this->request_data = $request_data;
        $this->amount_factory = $amount_factory;
        $this->recorded_rate = $recorded_rate;
        $this->recorded_tax_basis = $recorded_tax_basis;
        $this->recorded_quote = $recorded_quote;
        $this->logger = $logger;
        $this->logger_tag = 'wallet shipping';
    }
    /**
     * Prices the cart and responds with the quote the sheet displays.
     *
     * @throws Exception If the request is invalid or the cart is unavailable.
     */
    protected function handle_data(): void
    {
        $data = $this->request_data->read_request($this->nonce());
        $cart = WC()->cart;
        if (!$cart) {
            throw new Exception('The cart is not available.');
        }
        if (!empty($data['release'])) {
            $this->send_release();
            return;
        }
        $this->send_quote($cart, $data);
    }
    /**
     * Drops every record of this payment, so the page behind the sheet is the
     * shopper's own again.
     */
    private function send_release(): void
    {
        $this->recorded_rate->forget();
        $this->recorded_tax_basis->forget();
        $this->recorded_quote->forget();
        wp_send_json_success(array('released' => \true));
    }
    /**
     * Applies the sheet's selection to the cart and answers with the resulting quote.
     *
     * @param WC_Cart              $cart The shopper's cart.
     * @param array<string, mixed> $data The request data.
     */
    private function send_quote(WC_Cart $cart, array $data): void
    {
        $rate_id = $this->text_field($data, 'rate_id');
        $shipping = $this->address_from_request($data, 'address');
        $billing = $this->address_from_request($data, 'billing_address');
        $expected_total = $this->text_field($data, 'expected_total');
        $this->apply_shipping_address($shipping);
        $this->record_tax_basis($shipping, $billing);
        // Settles WooCommerce's rate-key baseline for the new destination, so the
        // rate selected below is judged against the final rates.
        $cart->calculate_shipping();
        $cart->calculate_totals();
        if ($rate_id) {
            $this->apply_rate($rate_id);
        }
        $quote = $this->quote();
        if ($expected_total && $this->exceeds($quote['total'], $expected_total)) {
            $this->send_refusal($quote['total'], $expected_total, $billing);
            return;
        }
        // Only the commit request states an expected total, and only its quote can
        // reach an order.
        if ($expected_total) {
            $this->recorded_quote->set($expected_total);
        }
        wp_send_json_success($quote);
    }
    /**
     * Refuses a total higher than the sheet displayed, and tells the shopper why.
     *
     * Leads with the reassurance, because the sheet has just failed at the moment of
     * paying. Names the country and the new total, so the retry is not a second
     * surprise.
     *
     * @param string                $total    The reconciled total, as a decimal string.
     * @param string                $expected The total the sheet displayed.
     * @param array<string, string> $billing  The payer's billing address.
     */
    private function send_refusal(string $total, string $expected, array $billing): void
    {
        $this->logger->info(sprintf('Wallet payment refused: the sheet showed %1$s and the cart now totals %2$s.', $expected, $total));
        $message = sprintf(
            /* translators: 1: country the tax was recalculated for, 2: the corrected total */
            __('Nothing has been charged yet. Tax for %1$s differs from our estimate, so your total is now %2$s. Please try again.', 'woocommerce-paypal-payments'),
            $this->country_name($billing['country'] ?? ''),
            html_entity_decode(wp_strip_all_tags(wc_price((float) $total, array('currency' => get_woocommerce_currency()))), \ENT_QUOTES, 'UTF-8')
        );
        wp_send_json_error(array('message' => $message));
    }
    /**
     * A country's name in the shopper's language, or its code when there is no name.
     *
     * @param string $code An ISO-2 country code.
     */
    private function country_name(string $code): string
    {
        $countries = WC()->countries ? WC()->countries->get_countries() : array();
        return (string) ($countries[$code] ?? $code);
    }
    /**
     * Records which address this payment's tax is calculated from.
     *
     * Only where the store taxes on billing; elsewhere WooCommerce already prices the
     * destination the sheet collected. The destination stands in until the payer's
     * own address arrives with the final quote.
     *
     * @param array<string, string> $shipping The posted shipping address.
     * @param array<string, string> $billing  The payer's billing address, at commit.
     */
    private function record_tax_basis(array $shipping, array $billing): void
    {
        if ('billing' !== get_option('woocommerce_tax_based_on')) {
            return;
        }
        if ($billing) {
            $this->recorded_tax_basis->set($billing);
            return;
        }
        // An estimate never displaces the payer's own address.
        if (!$this->recorded_tax_basis->get()) {
            $this->recorded_tax_basis->set($shipping);
        }
    }
    /**
     * Whether the total exceeds what the sheet displayed.
     *
     * In cents, not the shop's precision: both sides come from AmountFactory, which
     * always formats to two decimals, so scaling by a zero-decimal currency would
     * round a real increase away. No tolerance, since any tolerance is exactly the
     * amount that could be silently overcharged.
     *
     * @param string $total    The total this request produced.
     * @param string $expected The total the sheet displayed.
     */
    private function exceeds(string $total, string $expected): bool
    {
        return (int) round((float) $total * 100) > (int) round((float) $expected * 100);
    }
    /**
     * Reads a posted address, keeping only the fields WooCommerce prices from.
     *
     * Both sheets redact the street and the recipient until the shopper authorizes,
     * so before then those simply are not there.
     *
     * @param array<string, mixed> $data The request data.
     * @param string               $key  Which posted address to read.
     * @return array<string, string> WC address fields.
     */
    private function address_from_request(array $data, string $key): array
    {
        $posted = isset($data[$key]) && is_array($data[$key]) ? $data[$key] : array();
        $fields = array('country', 'state', 'postcode', 'city', 'address_1', 'address_2', 'first_name', 'last_name');
        $address = array();
        foreach ($fields as $field) {
            $value = $this->text_field($posted, $field);
            if ('' !== $value) {
                $address[$field] = $value;
            }
        }
        if (isset($address['country'])) {
            $address['country'] = strtoupper($address['country']);
        }
        return $address;
    }
    /**
     * Writes the sheet's destination onto the customer, leaving billing alone.
     *
     * The order is created from the customer record, so it must be priced from that
     * same record. An absent field means unchanged, never blanked. Billing is left to
     * RecordedTaxBasis.
     *
     * @param array<string, string> $address WC address fields.
     */
    private function apply_shipping_address(array $address): void
    {
        $customer = WC()->customer;
        if (!$customer || !$address) {
            return;
        }
        foreach ($address as $field => $value) {
            $setter = "set_shipping_{$field}";
            if (is_callable(array($customer, $setter))) {
                $customer->{$setter}($value);
            }
        }
        $customer->save();
    }
    /**
     * Selects a rate and makes the selection survive the recalculation.
     *
     * An unknown rate id is ignored: the response must never name a rate the order
     * will not be charged for.
     *
     * @param string $rate_id The WC rate id, e.g. flat_rate:3.
     */
    private function apply_rate(string $rate_id): void
    {
        $session = WC()->session;
        $cart = WC()->cart;
        if (!$session || !$cart || !isset($this->available_rates()[$rate_id])) {
            return;
        }
        // Pinned before recalculating, so the pin's filter can restore the choice
        // if this calculation, or the later one inside ppc-create-order, resets it.
        $this->recorded_rate->set($rate_id);
        $session->set('chosen_shipping_methods', array($rate_id));
        $cart->calculate_shipping();
        $cart->calculate_totals();
    }
    /**
     * The rates of the first shipping package.
     *
     * Only the first: neither sheet nor PayPal can express a per-package choice.
     *
     * @return array<string, WC_Shipping_Rate>
     */
    private function available_rates(): array
    {
        $packages = WC()->shipping() ? WC()->shipping()->get_packages() : array();
        $package = reset($packages);
        return is_array($package) && isset($package['rates']) ? $package['rates'] : array();
    }
    /**
     * The rate WooCommerce will charge for the first package.
     *
     * @return string The rate id, or an empty string when none is chosen.
     */
    private function chosen_rate_id(): string
    {
        $session = WC()->session;
        $chosen = $session ? $session->get('chosen_shipping_methods') : array();
        return is_array($chosen) && isset($chosen[0]) ? (string) $chosen[0] : '';
    }
    /**
     * The quote describing the cart as this request leaves it.
     *
     * Every figure comes from AmountFactory, the source the purchase unit is built
     * from, so the sheet displays the total PayPal is asked to charge. WC_Cart's own
     * total can differ by a cent, because AmountFactory sums its breakdown in integer
     * cents and PayPal rejects an amount that does not match that sum.
     *
     * @return array<string, mixed>
     */
    private function quote(): array
    {
        $cart = WC()->cart;
        $decimals = wc_get_price_decimals();
        $amount = $this->amount_factory->from_wc_cart($cart);
        $breakdown = $amount->breakdown();
        $options = array();
        foreach ($this->available_rates() as $rate) {
            $options[] = array('id' => $rate->get_id(), 'label' => $rate->get_label(), 'cost' => wc_format_decimal($rate->get_cost(), $decimals));
        }
        return array('total' => $amount->value_str(), 'shipping_fee' => $this->money_str($breakdown ? $breakdown->shipping() : null), 'subtotal' => $this->money_str($breakdown ? $breakdown->item_total() : null), 'tax' => $this->money_str($breakdown ? $breakdown->tax_total() : null), 'discount' => $this->money_str($breakdown ? $breakdown->discount() : null), 'currency_code' => get_woocommerce_currency(), 'needs_shipping' => $cart->needs_shipping(), 'selected_rate_id' => $this->chosen_rate_id(), 'options' => $options);
    }
    /**
     * A posted text field, sanitized. Anything but a scalar reads as absent.
     *
     * @param array<string, mixed> $data The data to read from.
     * @param string               $key  The field to read.
     */
    private function text_field(array $data, string $key): string
    {
        $value = wp_unslash($data[$key] ?? '');
        return is_scalar($value) ? sanitize_text_field((string) $value) : '';
    }
    /**
     * A breakdown figure as a decimal string, or zero where the breakdown omits it.
     *
     * @param Money|null $money The breakdown figure.
     */
    private function money_str(?Money $money): string
    {
        return $money ? $money->value_str() : wc_format_decimal(0, wc_get_price_decimals());
    }
}
