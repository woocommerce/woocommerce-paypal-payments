<?php

/**
 * The FeesUpdater helper.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Helper;
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\WcGateway\Helper;

use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use WC_Order;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\Orders;
use WooCommerce\PayPalCommerce\ApiClient\Factory\CaptureFactory;
use WooCommerce\PayPalCommerce\Button\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
/**
 * Class FeesUpdater
 */
class FeesUpdater
{
    /**
     * The orders' endpoint.
     *
     * @var Orders
     */
    private $orders_endpoint;
    /**
     * The capture factory.
     *
     * @var CaptureFactory
     */
    private $capture_factory;
    /**
     * The logger.
     *
     * @var LoggerInterface
     */
    private $logger;
    /**
     * FeesUpdater constructor.
     *
     * @param Orders          $orders_endpoint The orders' endpoint.
     * @param CaptureFactory  $capture_factory The capture factory.
     * @param LoggerInterface $logger The logger.
     */
    public function __construct(Orders $orders_endpoint, CaptureFactory $capture_factory, LoggerInterface $logger)
    {
        $this->orders_endpoint = $orders_endpoint;
        $this->capture_factory = $capture_factory;
        $this->logger = $logger;
    }
    /**
     * Updates the fees meta for a given order.
     *
     * @param string   $order_id PayPal order ID.
     * @param WC_Order $wc_order WC order.
     * @return void
     */
    public function update(string $order_id, WC_Order $wc_order): void
    {
        try {
            $order = $this->orders_endpoint->order($order_id);
        } catch (RuntimeException $exception) {
            $this->logger->warning(sprintf('Could not get PayPal order %1$s when trying to update fees for WC order #%2$s', $order_id, $wc_order->get_id()));
            return;
        }
        $body = json_decode($order['body']);
        $capture = $this->capture_factory->from_paypal_response($body->purchase_units[0]->payments->captures[0]);
        $breakdown = $capture->seller_receivable_breakdown();
        if ($breakdown) {
            $wc_order->update_meta_data(PayPalGateway::FEES_META_KEY, $breakdown->to_array());
            $paypal_fee = $breakdown->paypal_fee();
            if ($paypal_fee) {
                $wc_order->update_meta_data('PayPal Transaction Fee', (string) $paypal_fee->value());
            }
            $wc_order->save_meta_data();
        }
    }
}
