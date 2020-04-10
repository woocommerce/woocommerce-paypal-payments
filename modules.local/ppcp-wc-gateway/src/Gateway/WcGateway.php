<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\WcGateway\Gateway;

use Inpsyde\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use Inpsyde\PayPalCommerce\ApiClient\Entity\Order;
use Inpsyde\PayPalCommerce\ApiClient\Entity\OrderStatus;
use Inpsyde\PayPalCommerce\ApiClient\Factory\OrderFactory;
use Inpsyde\PayPalCommerce\ApiClient\Repository\CartRepository;
use Inpsyde\PayPalCommerce\Session\SessionHandler;
use Inpsyde\PayPalCommerce\WcGateway\Settings\SettingsFields;

//phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
//phpcs:disable Inpsyde.CodeQuality.ArgumentTypeDeclaration.NoArgumentType
class WcGateway extends \WC_Payment_Gateway
{

    const ID = 'ppcp-gateway';

    private $isSandbox = true;
    private $sessionHandler;
    private $endpoint;
    private $cartRepository;
    private $orderFactory;
    private $settingsFields;

    public function __construct(
        SessionHandler $sessionHandler,
        CartRepository $cartRepository,
        OrderEndpoint $endpoint,
        OrderFactory $orderFactory,
        SettingsFields $settingsFields
    ) {

        $this->sessionHandler = $sessionHandler;
        $this->cartRepository = $cartRepository;
        $this->endpoint = $endpoint;
        $this->orderFactory = $orderFactory;
        $this->settingsFields = $settingsFields;
        $this->id = self::ID;

        $this->method_title = __('PayPal Payments', 'woocommerce-paypal-gateway');
        $this->method_description = __(
            'Provide your customers with the PayPal payment system',
            'woocommerce-paypal-gateway'
        );

        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');

        $this->init_form_fields();
        $this->init_settings();

        add_action(
            'woocommerce_update_options_payment_gateways_' . $this->id,
            [
                $this,
                'process_admin_options',
            ]
        );
    }

    public function init_form_fields()
    {
        $this->form_fields = $this->settingsFields->fields();
    }

    public function process_payment($orderId) : ?array
    {
        global $woocommerce;
        $wcOrder = new \WC_Order($orderId);

        //ToDo: We need to fetch the order from paypal again to get it with the new status.
        $order = $this->sessionHandler->order();
        $errorMessage = null;
        if (! $order || ! $order->status()->is(OrderStatus::APPROVED)) {
            $errorMessage = 'not approve yet';
        }
        $errorMessage = null;
        if ($errorMessage) {
            wc_add_notice(__('Payment error:', 'woocommerce-paypal-gateway') . $errorMessage, 'error');
            return null;
        }

        $order = $this->patchOrder($wcOrder, $order);
        $order = $this->endpoint->capture($order);

        $wcOrder->update_status('on-hold', __('Awaiting payment.', 'woocommerce-paypal-gateway'));
        if ($order->status()->is(OrderStatus::COMPLETED)) {
            $wcOrder->update_status('processing', __('Payment received.', 'woocommerce-paypal-gateway'));
        }
        $woocommerce->cart->empty_cart();
        $this->sessionHandler->destroySessionData();

        return [
            'result' => 'success',
            'redirect' => $this->get_return_url($wcOrder),
        ];
    }

    private function patchOrder(\WC_Order $wcOrder, Order $order) : Order
    {
        $updatedOrder = $this->orderFactory->fromWcOrder($wcOrder, $order);
        $order = $this->endpoint->patchOrderWith($order, $updatedOrder);
        return $order;
    }
}
