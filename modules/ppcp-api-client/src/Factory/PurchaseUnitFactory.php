<?php

/**
 * The PurchaseUnit factory.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Factory
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\ApiClient\Factory;

use WC_Session_Handler;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Item;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PurchaseUnit;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\ApiClient\Helper\PurchaseUnitSanitizer;
use WooCommerce\PayPalCommerce\Webhooks\CustomIds;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Address;
/**
 * Class PurchaseUnitFactory
 */
class PurchaseUnitFactory
{
    /**
     * The amount factory.
     *
     * @var AmountFactory
     */
    private $amount_factory;
    /**
     * The item factory.
     *
     * @var ItemFactory
     */
    private $item_factory;
    /**
     * The shipping factory.
     *
     * @var ShippingFactory
     */
    private $shipping_factory;
    /**
     * The payments factory.
     *
     * @var PaymentsFactory
     */
    private $payments_factory;
    /**
     * The Prefix.
     *
     * @var string
     */
    private $prefix;
    /**
     * The Soft Descriptor.
     *
     * @var string
     */
    private $soft_descriptor;
    /**
     * The sanitizer for purchase unit output data.
     *
     * @var PurchaseUnitSanitizer|null
     */
    private $sanitizer;
    /**
     * PurchaseUnitFactory constructor.
     *
     * @param AmountFactory          $amount_factory The amount factory.
     * @param ItemFactory            $item_factory The item factory.
     * @param ShippingFactory        $shipping_factory The shipping factory.
     * @param PaymentsFactory        $payments_factory The payments factory.
     * @param string                 $prefix The prefix.
     * @param string                 $soft_descriptor The soft descriptor.
     * @param ?PurchaseUnitSanitizer $sanitizer The purchase unit to_array sanitizer.
     */
    public function __construct(\WooCommerce\PayPalCommerce\ApiClient\Factory\AmountFactory $amount_factory, \WooCommerce\PayPalCommerce\ApiClient\Factory\ItemFactory $item_factory, \WooCommerce\PayPalCommerce\ApiClient\Factory\ShippingFactory $shipping_factory, \WooCommerce\PayPalCommerce\ApiClient\Factory\PaymentsFactory $payments_factory, string $prefix = 'WC-', string $soft_descriptor = '', ?PurchaseUnitSanitizer $sanitizer = null)
    {
        $this->amount_factory = $amount_factory;
        $this->item_factory = $item_factory;
        $this->shipping_factory = $shipping_factory;
        $this->payments_factory = $payments_factory;
        $this->prefix = $prefix;
        $this->soft_descriptor = $soft_descriptor;
        $this->sanitizer = $sanitizer;
    }
    /**
     * Creates a PurchaseUnit based off a WooCommerce order.
     *
     * @param \WC_Order $order The order.
     *
     * @return PurchaseUnit
     */
    public function from_wc_order(\WC_Order $order): PurchaseUnit
    {
        $amount = $this->amount_factory->from_wc_order($order);
        $items = array_filter($this->item_factory->from_wc_order($order), function (Item $item): bool {
            return $item->unit_amount()->value() >= 0;
        });
        $shipping = $this->shipping_factory->from_wc_order($order);
        $shipping_address = $shipping->address();
        if ($this->should_disable_shipping($items, $shipping_address)) {
            $shipping = null;
        }
        $reference_id = 'default';
        $description = '';
        $custom_id = (string) $order->get_id();
        $invoice_id = $this->prefix . $order->get_order_number();
        $soft_descriptor = $this->sanitize_soft_descriptor($this->soft_descriptor);
        $purchase_unit = new PurchaseUnit($amount, $items, $shipping, $reference_id, $description, $custom_id, $invoice_id, $soft_descriptor);
        $this->init_purchase_unit($purchase_unit);
        /**
         * Returns PurchaseUnit for the WC order.
         */
        return apply_filters('woocommerce_paypal_payments_purchase_unit_from_wc_order', $purchase_unit, $order);
    }
    /**
     * Creates a PurchaseUnit based off a WooCommerce cart.
     *
     * @param \WC_Cart|null $cart The cart.
     * @param bool          $with_shipping_options Include WC shipping methods.
     *
     * @return PurchaseUnit
     */
    public function from_wc_cart(?\WC_Cart $cart = null, bool $with_shipping_options = \false): PurchaseUnit
    {
        if (!$cart) {
            $cart = WC()->cart ?? new \WC_Cart();
        }
        $amount = $this->amount_factory->from_wc_cart($cart);
        $items = array_filter($this->item_factory->from_wc_cart($cart), function (Item $item): bool {
            return $item->unit_amount()->value() >= 0;
        });
        $shipping = null;
        $customer = \WC()->customer;
        /** @psalm-suppress RedundantConditionGivenDocblockType False positive. Ignored because $customer can be null as well. */
        if ($this->shipping_needed(...array_values($items)) && is_a($customer, \WC_Customer::class)) {
            $shipping = $this->shipping_factory->from_wc_customer(\WC()->customer, $with_shipping_options);
            $shipping_address = $shipping->address();
            if (!$shipping_address || 2 !== strlen($shipping_address->country_code()) || !$shipping_address->postal_code() && !$this->country_without_postal_code($shipping_address->country_code())) {
                $shipping = null;
            }
        }
        $reference_id = 'default';
        $description = '';
        $custom_id = '';
        $session = WC()->session;
        if ($session instanceof WC_Session_Handler) {
            $session_id = $session->get_customer_unique_id();
            if ($session_id) {
                $custom_id = CustomIds::CUSTOMER_ID_PREFIX . $session_id;
            }
        }
        $invoice_id = '';
        $soft_descriptor = $this->sanitize_soft_descriptor($this->soft_descriptor);
        $purchase_unit = new PurchaseUnit($amount, $items, $shipping, $reference_id, $description, $custom_id, $invoice_id, $soft_descriptor);
        $this->init_purchase_unit($purchase_unit);
        return $purchase_unit;
    }
    /**
     * Builds a Purchase unit based off a PayPal JSON response.
     *
     * @param \stdClass $data The JSON object.
     *
     * @return ?PurchaseUnit
     * @throws RuntimeException When JSON object is malformed.
     */
    public function from_paypal_response(\stdClass $data): ?PurchaseUnit
    {
        if (!isset($data->reference_id) || !is_string($data->reference_id)) {
            throw new RuntimeException('No reference ID given.');
        }
        $amount_data = $data->amount ?? null;
        $amount = $this->amount_factory->from_paypal_response($amount_data);
        if (null === $amount) {
            return null;
        }
        $description = isset($data->description) ? $data->description : '';
        $custom_id = isset($data->custom_id) ? $data->custom_id : '';
        $invoice_id = isset($data->invoice_id) ? $data->invoice_id : '';
        $soft_descriptor = $this->sanitize_soft_descriptor($data->soft_descriptor ?? $this->soft_descriptor);
        $items = array();
        if (isset($data->items) && is_array($data->items)) {
            $items = array_map(function (\stdClass $item): Item {
                return $this->item_factory->from_paypal_response($item);
            }, $data->items);
        }
        $shipping = null;
        try {
            if (isset($data->shipping) && !empty((array) $data->shipping)) {
                $shipping = $this->shipping_factory->from_paypal_response($data->shipping);
            }
        } catch (RuntimeException $error) {
            $shipping = null;
        }
        $payments = null;
        try {
            if (isset($data->payments)) {
                $payments = $this->payments_factory->from_paypal_response($data->payments);
            }
        } catch (RuntimeException $error) {
            $payments = null;
        }
        $purchase_unit = new PurchaseUnit($amount, $items, $shipping, $data->reference_id, $description, $custom_id, $invoice_id, $soft_descriptor, $payments);
        $this->init_purchase_unit($purchase_unit);
        return $purchase_unit;
    }
    /**
     * Whether we need a shipping address for a set of items or not.
     *
     * @param Item ...$items The items on based which the decision is made.
     *
     * @return bool
     */
    private function shipping_needed(Item ...$items): bool
    {
        /**
         * If you are returning false from this filter, do not forget to also set
         * shipping_preference to 'NO_SHIPPING', otherwise PayPal will return an error.
         *
         * @see ShippingPreferenceFactory::from_state() for
         *      the 'woocommerce_paypal_payments_shipping_preference' filter.
         */
        $shipping_needed = apply_filters('woocommerce_paypal_payments_shipping_needed', null, $items);
        if (is_bool($shipping_needed)) {
            return $shipping_needed;
        }
        foreach ($items as $item) {
            if ($item->category() !== Item::DIGITAL_GOODS) {
                return \true;
            }
        }
        return \false;
    }
    /**
     * Check if country does not have postal code.
     *
     * @param string $country_code The country code.
     * @return bool Whether country has postal code or not.
     */
    private function country_without_postal_code(string $country_code): bool
    {
        $countries = array('AE', 'AF', 'AG', 'AI', 'AL', 'AN', 'AO', 'AW', 'BB', 'BF', 'BH', 'BI', 'BJ', 'BM', 'BO', 'BS', 'BT', 'BW', 'BZ', 'CD', 'CF', 'CG', 'CI', 'CK', 'CL', 'CM', 'CO', 'CR', 'CV', 'DJ', 'DM', 'DO', 'EC', 'EG', 'ER', 'ET', 'FJ', 'FK', 'GA', 'GD', 'GH', 'GI', 'GM', 'GN', 'GQ', 'GT', 'GW', 'GY', 'HK', 'HN', 'HT', 'IE', 'IQ', 'IR', 'JM', 'JO', 'KE', 'KH', 'KI', 'KM', 'KN', 'KP', 'KW', 'KY', 'LA', 'LB', 'LC', 'LK', 'LR', 'LS', 'LY', 'ML', 'MM', 'MO', 'MR', 'MS', 'MT', 'MU', 'MW', 'MZ', 'NA', 'NE', 'NG', 'NI', 'NP', 'NR', 'NU', 'OM', 'PA', 'PE', 'PF', 'PY', 'QA', 'RW', 'SA', 'SB', 'SC', 'SD', 'SL', 'SN', 'SO', 'SR', 'SS', 'ST', 'SV', 'SY', 'TC', 'TD', 'TG', 'TL', 'TO', 'TT', 'TV', 'TZ', 'UG', 'UY', 'VC', 'VE', 'VG', 'VN', 'VU', 'WS', 'XA', 'XB', 'XC', 'XE', 'XL', 'XM', 'XN', 'XS', 'YE', 'ZM', 'ZW');
        return in_array($country_code, $countries, \true);
    }
    /**
     * Initializes a purchase unit object.
     *
     * @param PurchaseUnit $purchase_unit The purchase unit.
     * @return void
     */
    private function init_purchase_unit(PurchaseUnit $purchase_unit): void
    {
        if ($this->sanitizer instanceof PurchaseUnitSanitizer) {
            $purchase_unit->set_sanitizer($this->sanitizer);
        }
    }
    /**
     * Sanitizes a soft descriptor, ensuring it is limited to 22 chars.
     *
     * The soft descriptor in the DB is escaped using `wp_kses_post()` which
     * escapes certain characters via `wp_kses_normalize_entities()`. This
     * helper method reverts those normalized entities back to UTF characters.
     *
     * @param string $soft_descriptor Soft descriptor to sanitize.
     *
     * @return string The sanitized soft descriptor.
     */
    private function sanitize_soft_descriptor(string $soft_descriptor): string
    {
        $decoded = html_entity_decode($soft_descriptor, \ENT_QUOTES, 'UTF-8');
        $sanitized = preg_replace('/[^a-zA-Z0-9 *\-.]/', '', $decoded) ?: '';
        return substr($sanitized, 0, 22) ?: '';
    }
    /**
     * Determines whether shipping should be disabled for a purchase unit.
     *
     * @param array        $items Purchase unit items.
     * @param Address|null $shipping_address The shipping address to validate.
     *
     * @return bool
     */
    private function should_disable_shipping(array $items, ?Address $shipping_address): bool
    {
        return !$this->shipping_needed(...array_values($items)) || !$shipping_address || empty($shipping_address->country_code()) || empty($shipping_address->address_line_1()) || !$shipping_address->postal_code() && !$this->country_without_postal_code($shipping_address->country_code());
    }
}
