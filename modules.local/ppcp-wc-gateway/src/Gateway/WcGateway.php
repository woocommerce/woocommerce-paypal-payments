<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\WcGateway\Gateway;

use Inpsyde\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use Inpsyde\PayPalCommerce\ApiClient\Endpoint\PaymentsEndpoint;
use Inpsyde\PayPalCommerce\ApiClient\Entity\Authorization;
use Inpsyde\PayPalCommerce\ApiClient\Entity\AuthorizationStatus;
use Inpsyde\PayPalCommerce\ApiClient\Entity\Order;
use Inpsyde\PayPalCommerce\ApiClient\Entity\OrderStatus;
use Inpsyde\PayPalCommerce\ApiClient\Exception\RuntimeException;
use Inpsyde\PayPalCommerce\ApiClient\Factory\OrderFactory;
use Inpsyde\PayPalCommerce\ApiClient\Repository\CartRepository;
use Inpsyde\PayPalCommerce\Session\SessionHandler;
use Inpsyde\PayPalCommerce\WcGateway\Notice\AuthorizeOrderActionNotice;
use Inpsyde\PayPalCommerce\WcGateway\Settings\SettingsFields;

//phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
//phpcs:disable Inpsyde.CodeQuality.ArgumentTypeDeclaration.NoArgumentType
class WcGateway extends WcGatewayBase implements WcGatewayInterface
{
    private $isSandbox = true;
    private $sessionHandler;
    private $orderEndpoint;
    private $cartRepository;
    private $orderFactory;
    private $settingsFields;
    /**
     * @var PaymentsEndpoint
     */
    private $paymentsEndpoint;

    public function __construct(
        SessionHandler $sessionHandler,
        CartRepository $cartRepository,
        OrderEndpoint $orderEndpoint,
        PaymentsEndpoint $paymentsEndpoint,
        OrderFactory $orderFactory,
        SettingsFields $settingsFields
    ) {

        $this->sessionHandler = $sessionHandler;
        $this->cartRepository = $cartRepository;
        $this->orderEndpoint = $orderEndpoint;
        $this->paymentsEndpoint = $paymentsEndpoint;
        $this->orderFactory = $orderFactory;
        $this->settingsFields = $settingsFields;

        $this->method_title = __('PayPal Payments', 'woocommerce-paypal-gateway');
        $this->method_description = __(
            'Provide your customers with the PayPal payment system',
            'woocommerce-paypal-gateway'
        );

        parent::__construct();

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

    public function process_payment($orderId): ?array
    {
        global $woocommerce;
        $wcOrder = new \WC_Order($orderId);

        //ToDo: We need to fetch the order from paypal again to get it with the new status.

        $order = $this->sessionHandler->order();
        update_post_meta($orderId, '_paypal_order_id', $order->id());

        $errorMessage = null;
        if (!$order || !$order->status()->is(OrderStatus::APPROVED)) {
            $errorMessage = 'not approve yet';
        }
        $errorMessage = null;
        if ($errorMessage) {
            wc_add_notice(__('Payment error:', 'woocommerce-paypal-gateway') . $errorMessage, 'error');
            return null;
        }

        $order = $this->patchOrder($wcOrder, $order);
        if ($order->intent() === 'CAPTURE') {
            $order = $this->orderEndpoint->capture($order);
        }

        if ($order->intent() === 'AUTHORIZE') {
            $order = $this->orderEndpoint->authorize($order);
        }

        $wcOrder->update_status('on-hold', __('Awaiting payment.', 'woocommerce-paypal-gateway'));
        if ($order->status()->is(OrderStatus::COMPLETED) && $order->intent() === 'CAPTURE') {
            $wcOrder->update_status('processing', __('Payment received.', 'woocommerce-paypal-gateway'));
        }
        $woocommerce->cart->empty_cart();
        $this->sessionHandler->destroySessionData();

        return [
            'result' => 'success',
            'redirect' => $this->get_return_url($wcOrder),
        ];
    }

    public function captureAuthorizedPayment(\WC_Order $wcOrder)
    {
        $payPalOrderId = get_post_meta($wcOrder->get_id(), '_paypal_order_id', true);

        try {
            $order = $this->orderEndpoint->order($payPalOrderId);
        } catch (RuntimeException $exception) {
            AuthorizeOrderActionNotice::displayMessage(AuthorizeOrderActionNotice::NO_INFO);
            return;
        }

        $allAuthorizations = [];
        foreach ($order->purchaseUnits() as $purchaseUnit) {
            foreach ($purchaseUnit->payments()->authorizations() as $authorization) {
                $allAuthorizations[] = $authorization;
            }
        }
        $authorizationsWithCreatedStatus = array_filter(
            $allAuthorizations,
            function (Authorization $authorization) {
                return $authorization->status()->is(AuthorizationStatus::CREATED);
            }
        );
        $authorizationsWithCapturedStatus = array_filter(
            $allAuthorizations,
            function (Authorization $authorization) {
                return $authorization->status()->is(AuthorizationStatus::CAPTURED);
            }
        );

        if (count($authorizationsWithCapturedStatus) === count($allAuthorizations)) {
            if ($wcOrder->get_status() === 'on-hold') {
                $wcOrder->add_order_note(
                    __(
                        'Payment successfully authorized.',
                        'woocommerce-paypal-gateway'
                    )
                );

                $wcOrder->update_status('processing');
            }
            AuthorizeOrderActionNotice::displayMessage(AuthorizeOrderActionNotice::ALREADY_AUTHORIZED);
            return;
        }

        foreach ($authorizationsWithCreatedStatus as $authorization) {
            try {
                /**
                 * @var Authorization $authorization
                 */
                $result = $this->paymentsEndpoint->capture($authorization->id());
            } catch (RuntimeException $exception) {
                AuthorizeOrderActionNotice::displayMessage(AuthorizeOrderActionNotice::FAILED);
                return;
            }
        }

        AuthorizeOrderActionNotice::displayMessage(AuthorizeOrderActionNotice::SUCCESS);

        $wcOrder->add_order_note(
            __(
                'Payment successfully authorized.',
                'woocommerce-paypal-gateway'
            )
        );

        $wcOrder->update_status('processing');
    }

    private function patchOrder(\WC_Order $wcOrder, Order $order): Order
    {
        $updatedOrder = $this->orderFactory->fromWcOrder($wcOrder, $order);
        $order = $this->orderEndpoint->patchOrderWith($order, $updatedOrder);
        return $order;
    }
}
