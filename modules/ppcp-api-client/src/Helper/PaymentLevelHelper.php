<?php

/**
 * Helper for building Level 2/3 card processing data.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Helper
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\ApiClient\Helper;

use WooCommerce\PayPalCommerce\ApiClient\Entity\Amount;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Money;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Item;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Shipping;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
class PaymentLevelHelper
{
    private SettingsProvider $settings;
    public function __construct(SettingsProvider $settings)
    {
        $this->settings = $settings;
    }
    /**
     * Builds supplementary card data.
     *
     * @param Amount        $amount   The Amount object based off a WooCommerce cart.
     * @param Item[]|null   $items    Array of Item objects for Level 3.
     * @param Shipping|null $shipping Shipping object for Level 3.
     * @return array{
     *     supplementary_data: array{
     *         card: array{
     *             level_2?: array{
     *                 invoice_id: string,
     *                 tax_total?: array{
     *                     currency_code: string,
     *                     value: string
     *                 }
     *             },
     *             level_3?: array{
     *                 shipping_amount?: array{
     *                     currency_code: string,
     *                     value: string
     *                 },
     *                 discount_amount?: array{
     *                     currency_code: string,
     *                     value: string
     *                 },
     *                 duty_amount?: array{
     *                     currency_code: string,
     *                     value: string
     *                 },
     *                 shipping_address?: array{
     *                     address_line_1?: string,
     *                     address_line_2?: string,
     *                     admin_area_1?: string,
     *                     admin_area_2?: string,
     *                     postal_code?: string,
     *                     country_code: string
     *                 },
     *                 ships_from_postal_code?: string,
     *                 line_items?: array<int, array{
     *                     name: string,
     *                     quantity: string,
     *                     unit_amount: array{
     *                         currency_code: string,
     *                         value: string
     *                     },
     *                     total_amount: array{
     *                         currency_code: string,
     *                         value: string
     *                     },
     *                     description?: string,
     *                     commodity_code?: string,
     *                     upc?: array{type: string, code: string},
     *                     tax?: array{
     *                         currency_code: string,
     *                         value: string
     *                     },
     *                     discount_amount?: array{
     *                         currency_code: string,
     *                         value: string
     *                     },
     *                     unit_of_measure?: string
     *                 }>
     *             }
     *         }
     *     }
     * }|null Supplementary data array ready for PurchaseUnit, or null if no data could be built.
     */
    public function build(Amount $amount, ?array $items = null, ?Shipping $shipping = null): ?array
    {
        $breakdown = $amount->breakdown();
        $tax_total = $breakdown ? $breakdown->tax_total() : null;
        $data = array('supplementary_data' => array('card' => array('level_2' => $this->build_level_2($tax_total))));
        $level_3_data = $this->build_level_3($amount, $items, $shipping);
        if ($level_3_data) {
            $data['supplementary_data']['card']['level_3'] = $level_3_data;
        }
        return $data;
    }
    /**
     * Builds Level 2 card data.
     *
     * @param Money|null $tax_total The tax total amount.
     * @return array{
     *     invoice_id: string,
     *     tax_total?: array{
     *         currency_code: string,
     *         value: string
     *     }
     * } Level 2 data array.
     */
    private function build_level_2(?Money $tax_total): array
    {
        /**
         * Filters the Level 2 invoice ID.
         *
         * @param string $invoice_id The invoice ID (default: unique cart identifier).
         */
        $invoice_id = apply_filters('woocommerce_paypal_payments_level2_invoice_id', 'INV_' . strtoupper(uniqid()));
        $level_2 = array('invoice_id' => (string) substr($invoice_id, 0, 127));
        if ($tax_total) {
            $level_2['tax_total'] = array('currency_code' => $tax_total->currency_code(), 'value' => $tax_total->value_str());
        }
        return $level_2;
    }
    /**
     * Builds Level 3 card data.
     *
     * @param Amount        $amount   The Amount object.
     * @param Item[]|null   $items    Array of Item objects.
     * @param Shipping|null $shipping Shipping object.
     * @return array{
     *     shipping_amount?: array{
     *         currency_code: string,
     *         value: string
     *     },
     *     discount_amount?: array{
     *         currency_code: string,
     *         value: string
     *     },
     *     duty_amount?: array{
     *         currency_code: string,
     *         value: string
     *     },
     *     shipping_address?: array{
     *         address_line_1?: string,
     *         address_line_2?: string,
     *         admin_area_1?: string,
     *         admin_area_2?: string,
     *         postal_code?: string,
     *         country_code: string
     *     },
     *     ships_from_postal_code?: string,
     *     line_items?: array<int, array{
     *         name: string,
     *         quantity: string,
     *         unit_amount: array{currency_code: string, value: string},
     *         total_amount: array{currency_code: string, value: string},
     *         description?: string,
     *         commodity_code?: string,
     *         upc?: array{type: string, code: string},
     *         tax?: array{currency_code: string, value: string},
     *         discount_amount?: array{currency_code: string, value: string},
     *         unit_of_measure?: string
     *     }>
     * }|null Level 3 data array, or null if insufficient data.
     */
    private function build_level_3(Amount $amount, ?array $items, ?Shipping $shipping): ?array
    {
        $breakdown = $amount->breakdown();
        if (!$breakdown) {
            return null;
        }
        $level_3 = array();
        $shipping_amount = $breakdown->shipping();
        if ($shipping_amount) {
            $level_3['shipping_amount'] = array('currency_code' => $shipping_amount->currency_code(), 'value' => $shipping_amount->value_str());
        }
        $discount_amount = $breakdown->discount();
        if ($discount_amount) {
            $level_3['discount_amount'] = array('currency_code' => $discount_amount->currency_code(), 'value' => $discount_amount->value_str());
        }
        /**
         * Filters the Level 3 duty amount.
         *
         * Duty amount (WooCommerce doesn't track customs duties by default)
         *
         * @param Money|null $duty_amount The duty amount (default: null).
         * @param Amount $amount The Amount object.
         */
        $duty_amount = apply_filters('woocommerce_paypal_payments_level3_duty_amount', null, $amount);
        if ($duty_amount instanceof Money) {
            $level_3['duty_amount'] = array('currency_code' => $duty_amount->currency_code(), 'value' => $duty_amount->value_str());
        }
        if ($shipping) {
            $address = $shipping->address();
            if ($address) {
                /** @var array{
                 *     address_line_1?: string,
                 *     address_line_2?: string,
                 *     admin_area_1?: string,
                 *     admin_area_2?: string,
                 *     postal_code?: string,
                 *     country_code: string
                 * } $shipping_address
                 */
                $shipping_address = $address->to_array();
                $level_3['shipping_address'] = $shipping_address;
            }
        }
        /**
         * Filters the Level 3 ships from postal code.
         *
         * Allows overriding the ships-from postal code set in settings.
         *
         * @param string $postal_code The postal code where items ship from (default: from settings).
         */
        $ships_from_postal_code = apply_filters('woocommerce_paypal_payments_level3_ships_from_postal_code', $this->settings->ships_from_postal_code());
        if ($ships_from_postal_code) {
            $level_3['ships_from_postal_code'] = (string) substr($ships_from_postal_code, 0, 60);
        }
        if ($items && count($items) > 0) {
            $line_items = $this->build_level_3_line_items($items);
            if (!empty($line_items)) {
                $level_3['line_items'] = $line_items;
            }
        }
        return !empty($level_3) ? $level_3 : null;
    }
    /**
     * Builds Level 3 line items data.
     *
     * @param Item[] $items Array of Item objects.
     * @return array<int, array{
     *     name: string,
     *     quantity: string,
     *     unit_amount: array{currency_code: string, value: string},
     *     total_amount: array{currency_code: string, value: string},
     *     description?: string,
     *     commodity_code?: string,
     *     upc?: array{type: string, code: string},
     *     tax?: array{currency_code: string, value: string},
     *     discount_amount?: array{currency_code: string, value: string},
     *     unit_of_measure?: string
     * }> Array of Level 3 line items.
     */
    private function build_level_3_line_items(array $items): array
    {
        $line_items = array();
        foreach ($items as $item) {
            $line_item = array('name' => (string) substr($item->name(), 0, 127), 'quantity' => (string) $item->quantity(), 'unit_amount' => array('currency_code' => $item->unit_amount()->currency_code(), 'value' => $item->unit_amount()->value_str()), 'total_amount' => array('currency_code' => $item->unit_amount()->currency_code(), 'value' => number_format($item->unit_amount()->value() * (float) $item->quantity(), 2, '.', '')));
            if ($item->description()) {
                $line_item['description'] = (string) substr($item->description(), 0, 127);
            }
            /**
             * Filters the Level 3 commodity code.
             *
             * Uses SKU as fallback, filterable for custom codes.
             *
             * @param string $commodity_code The commodity code (default: SKU or empty).
             * @param Item $item The Item object.
             */
            $commodity_code = apply_filters('woocommerce_paypal_payments_level3_commodity_code', $item->sku(), $item);
            if ($commodity_code) {
                $line_item['commodity_code'] = (string) substr($commodity_code, 0, 12);
            }
            $gtin = '';
            if ($item->product_id()) {
                $product = wc_get_product($item->product_id());
                if ($product) {
                    /** @psalm-suppress UndefinedMethod - get_global_unique_id exists since WC 9.1.0 */
                    $gtin = $product->get_global_unique_id();
                }
            }
            /**
             * Filters the Level 3 UPC data.
             *
             * Defaults to WooCommerce GTIN field if product ID is available.
             * Use this filter to provide UPC data for custom implementations or different barcode types.
             *
             * @param array{type: string, code: string}|null $upc  The UPC data (default: from GTIN field or null).
             * @param Item $item The Item object.
             * @param string $gtin The GTIN value from product meta (empty if not found).
             */
            $upc = apply_filters('woocommerce_paypal_payments_level3_upc', $gtin ? array('type' => 'UPC-A', 'code' => $gtin) : null, $item, $gtin);
            if (is_array($upc) && isset($upc['type'], $upc['code']) && $upc['code']) {
                // Strip non-alphanumeric characters (PayPal only accepts letters and numbers).
                $sanitized_code = preg_replace('/[^A-Za-z0-9]/', '', $upc['code']);
                if ($sanitized_code) {
                    $line_item['upc'] = array('type' => (string) substr($upc['type'], 0, 5), 'code' => (string) substr($sanitized_code, 0, 17));
                }
            }
            $tax = $item->tax();
            if ($tax) {
                $line_item['tax'] = array('currency_code' => $tax->currency_code(), 'value' => $tax->value_str());
            }
            /**
             * Filters the Level 3 line item discount amount.
             *
             * Defaults to item discount from WooCommerce (includes coupons and sale prices).
             *
             * @param Money|null $discount The discount amount (default: from Item entity).
             * @param Item $item The Item object.
             */
            $discount = apply_filters('woocommerce_paypal_payments_level3_line_item_discount', $item->discount(), $item);
            if ($discount instanceof Money) {
                $line_item['discount_amount'] = array('currency_code' => $discount->currency_code(), 'value' => $discount->value_str());
            }
            $unit_of_measure = $this->get_unit_of_measure();
            /**
             * Filters the Level 3 unit of measure.
             *
             * Maps from WooCommerce weight units
             *
             * @param string|null $unit_of_measure The unit of measure (default: from WooCommerce weight unit).
             * @param Item $item The Item object.
             */
            $unit_of_measure = apply_filters('woocommerce_paypal_payments_level3_unit_of_measure', $unit_of_measure, $item);
            if ($unit_of_measure) {
                $line_item['unit_of_measure'] = (string) substr($unit_of_measure, 0, 12);
            }
            $line_items[] = $line_item;
        }
        return $line_items;
    }
    /**
     * Gets PayPal unit of measure from WooCommerce weight unit.
     *
     * @return string|null PayPal unit of measure or null if not mapped.
     */
    private function get_unit_of_measure(): ?string
    {
        $wc_weight_unit = get_option('woocommerce_weight_unit', 'lbs');
        $unit_map = array('kg' => 'KILOGRAM', 'g' => 'GRAM', 'lbs' => 'POUND_GB_US', 'oz' => 'OUNCE');
        return $unit_map[$wc_weight_unit] ?? null;
    }
}
