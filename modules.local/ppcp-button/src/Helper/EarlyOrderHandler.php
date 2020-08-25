<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Button\Helper;

use Inpsyde\PayPalCommerce\ApiClient\Entity\Order;
use Inpsyde\PayPalCommerce\Onboarding\State;
use Inpsyde\PayPalCommerce\Session\SessionHandler;
use Inpsyde\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use Inpsyde\PayPalCommerce\WcGateway\Processor\OrderProcessor;
use Inpsyde\PayPalCommerce\WcGateway\Settings\Settings;
use Inpsyde\PayPalCommerce\Webhooks\Handler\PrefixTrait;

class EarlyOrderHandler
{
    use PrefixTrait;

    private $state;
    private $orderProcessor;
    private $sessionHandler;

    public function __construct(
        State $state,
        OrderProcessor $orderProcessor,
        SessionHandler $sessionHandler,
        string $prefix
    ) {

        $this->state = $state;
        $this->orderProcessor = $orderProcessor;
        $this->sessionHandler = $sessionHandler;
        $this->prefix = $prefix;
    }

    public function shouldCreateEarlyOrder(): bool
    {
        return $this->state->currentState() === State::STATE_ONBOARDED;
    }

    //phpcs:disable WordPress.Security.NonceVerification.Recommended
    public function determineWcOrderId(int $value = null): ?int
    {

        if (! isset($_REQUEST['ppcp-resume-order'])) {
            return $value;
        }

        $resumeOrderId = (int) WC()->session->get('order_awaiting_payment');

        $order = $this->sessionHandler->order();
        if (! $order) {
            return $value;
        }

        $orderId = false;
        foreach ($order->purchaseUnits() as $purchaseUnit) {
            if ($purchaseUnit->customId() === sanitize_text_field(wp_unslash($_REQUEST['ppcp-resume-order']))) {
                $orderId = (int) $this->sanitizeCustomId($purchaseUnit->customId());
            }
        }
        if ($orderId === $resumeOrderId) {
            $value = $orderId;
        }
        return $value;
    }
    //phpcs:enable WordPress.Security.NonceVerification.Recommended

    public function registerForOrder(Order $order): bool
    {

        $success = (bool) add_action(
            'woocommerce_checkout_order_processed',
            function ($orderId) use ($order) {
                try {
                    $order = $this->configureSessionAndOrder((int) $orderId, $order);
                    wp_send_json_success($order->toArray());
                } catch (\RuntimeException $error) {
                    wp_send_json_error(
                        __(
                            'Something went wrong. Please try again or choose another payment source.',
                            'woocommerce-paypal-commerce-gateway'
                        )
                    );
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
