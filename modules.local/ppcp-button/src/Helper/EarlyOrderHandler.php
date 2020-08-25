<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Button\Helper;

use Inpsyde\PayPalCommerce\ApiClient\Entity\Order;
use Inpsyde\PayPalCommerce\Onboarding\State;
use Inpsyde\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use Inpsyde\PayPalCommerce\WcGateway\Processor\OrderProcessor;
use Inpsyde\PayPalCommerce\WcGateway\Settings\Settings;

class EarlyOrderHandler
{

    private $state;
    private $orderProcessor;


    public function __construct(
        State $state,
        OrderProcessor $orderProcessor
    ) {

        $this->state = $state;
        $this->orderProcessor = $orderProcessor;
    }

    public function shouldCreateEarlyOrder() : bool
    {
        return $this->state->currentState() === State::STATE_ONBOARDED;
    }

    public function registerForOrder(Order $order): bool {

        $success = (bool) add_action(
            'woocommerce_checkout_order_processed',
            function($orderId) use ($order) {
                try {
                    $order = $this->configureSessionAndOrder((int) $orderId, $order);
                    wp_send_json_success($order->toArray());
                    return true;
                }  catch (\RuntimeException $error) {
                    wp_send_json_error(
                        __('Something went wrong. Please try again or choose another payment source.', 'woocommerce-paypal-commerce-gateway')
                    );
                    return false;
                }
            }
        );

        return $success;
    }

    public function configureSessionAndOrder(int $orderId, Order $order): Order
    {

        /**
         * Set the order id in our session in order for
         * us to resume this order in checkout.
         */
        WC()->session->set('order_awaiting_payment', $orderId);

        $wcOrder = wc_get_order($orderId);
        $wcOrder->update_meta_data(PayPalGateway::ORDER_ID_META_KEY, $order->id());
        $wcOrder->update_meta_data(PayPalGateway::INTENT_META_KEY, $order->intent());
        $wcOrder->save_meta_data();

        /**
         * Patch Order so we have the \WC_Order id added.
         */
        return $this->orderProcessor->patchOrder($wcOrder, $order);
    }
}
