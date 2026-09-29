<?php

/**
 * The Order factory.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Factory
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\ApiClient\Factory;

use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\OrderStatus;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentSource;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PurchaseUnit;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
/**
 * Class OrderFactory
 */
class OrderFactory
{
    /**
     * The PurchaseUnit factory.
     *
     * @var PurchaseUnitFactory
     */
    private $purchase_unit_factory;
    /**
     * The Payer factory.
     *
     * @var PayerFactory
     */
    private $payer_factory;
    /**
     * OrderFactory constructor.
     *
     * @param PurchaseUnitFactory $purchase_unit_factory The PurchaseUnit factory.
     * @param PayerFactory        $payer_factory The Payer factory.
     */
    public function __construct(\WooCommerce\PayPalCommerce\ApiClient\Factory\PurchaseUnitFactory $purchase_unit_factory, \WooCommerce\PayPalCommerce\ApiClient\Factory\PayerFactory $payer_factory)
    {
        $this->purchase_unit_factory = $purchase_unit_factory;
        $this->payer_factory = $payer_factory;
    }
    /**
     * Creates an Order object based off a WooCommerce order and another Order object.
     *
     * @param \WC_Order $wc_order The WooCommerce order.
     * @param Order     $order The order object.
     *
     * @return Order
     */
    public function from_wc_order(\WC_Order $wc_order, Order $order): Order
    {
        $purchase_units = array($this->purchase_unit_factory->from_wc_order($wc_order));
        return new Order($order->id(), $purchase_units, $order->status(), $order->payment_source(), $order->payer(), $order->intent(), $order->create_time(), $order->update_time(), $order->links());
    }
    /**
     * Returns an Order object based off a PayPal Response.
     *
     * @param \stdClass $order_data The JSON object.
     *
     * @return Order
     * @throws RuntimeException When JSON object is malformed.
     */
    public function from_paypal_response(\stdClass $order_data): Order
    {
        $this->validate_order_id($order_data);
        $purchase_units = $this->create_purchase_units($order_data);
        $status = $this->create_order_status($order_data);
        $intent = $this->get_intent($order_data);
        $timestamps = $this->create_timestamps($order_data);
        $payer = $this->create_payer($order_data);
        $payment_source = $this->create_payment_source($order_data);
        $links = $order_data->links ?? null;
        return new Order($order_data->id, $purchase_units, $status, $payment_source, $payer, $intent, $timestamps['create_time'], $timestamps['update_time'], $links);
    }
    /**
     * Validates that the order data contains a required ID.
     *
     * @param \stdClass $order_data The order data.
     *
     * @throws RuntimeException When ID is missing.
     */
    private function validate_order_id(\stdClass $order_data): void
    {
        if (!isset($order_data->id)) {
            throw new RuntimeException('Order does not contain an id.');
        }
    }
    /**
     * Creates purchase units from order data.
     *
     * @param \stdClass $order_data The order data.
     *
     * @return array Array of PurchaseUnit objects.
     */
    private function create_purchase_units(\stdClass $order_data): array
    {
        if (!isset($order_data->purchase_units) || !is_array($order_data->purchase_units)) {
            return array();
        }
        $purchase_units = array();
        foreach ($order_data->purchase_units as $data) {
            $purchase_unit = $this->purchase_unit_factory->from_paypal_response($data);
            if (null !== $purchase_unit) {
                $purchase_units[] = $purchase_unit;
            }
        }
        return $purchase_units;
    }
    /**
     * Creates order status from order data.
     *
     * @param \stdClass $order_data The order data.
     *
     * @return OrderStatus
     */
    private function create_order_status(\stdClass $order_data): OrderStatus
    {
        $status_value = $order_data->status ?? 'PAYER_ACTION_REQUIRED';
        return new OrderStatus($status_value);
    }
    /**
     * Gets the intent from order data.
     *
     * @param \stdClass $order_data The order data.
     *
     * @return string
     */
    private function get_intent(\stdClass $order_data): string
    {
        return $order_data->intent ?? 'CAPTURE';
    }
    /**
     * Creates timestamps from order data.
     *
     * @param \stdClass $order_data The order data.
     *
     * @return array Array with 'create_time' and 'update_time' keys.
     */
    private function create_timestamps(\stdClass $order_data): array
    {
        $create_time = isset($order_data->create_time) ? \DateTime::createFromFormat('Y-m-d\TH:i:sO', $order_data->create_time) : null;
        $update_time = isset($order_data->update_time) ? \DateTime::createFromFormat('Y-m-d\TH:i:sO', $order_data->update_time) : null;
        return array('create_time' => $create_time, 'update_time' => $update_time);
    }
    /**
     * Creates payer from order data.
     *
     * @param \stdClass $order_data The order data.
     *
     * @return mixed Payer object or null.
     */
    private function create_payer(\stdClass $order_data)
    {
        return isset($order_data->payer) ? $this->payer_factory->from_paypal_response($order_data->payer) : null;
    }
    /**
     * Creates payment source from order data.
     *
     * @param \stdClass $order_data The order data.
     *
     * @return PaymentSource|null
     */
    private function create_payment_source(\stdClass $order_data): ?PaymentSource
    {
        if (!isset($order_data->payment_source)) {
            return null;
        }
        $json_encoded_payment_source = wp_json_encode($order_data->payment_source);
        if (!$json_encoded_payment_source) {
            return null;
        }
        $payment_source_as_array = json_decode($json_encoded_payment_source, \true);
        if (!$payment_source_as_array) {
            return null;
        }
        $source_name = array_key_first($payment_source_as_array);
        if (!$source_name) {
            return null;
        }
        return new PaymentSource($source_name, $order_data->payment_source->{$source_name});
    }
}
