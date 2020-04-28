<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\WcGateway\Gateway;

use Inpsyde\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use Inpsyde\PayPalCommerce\ApiClient\Endpoint\PaymentsEndpoint;
use Inpsyde\PayPalCommerce\ApiClient\Entity\Order;
use Inpsyde\PayPalCommerce\ApiClient\Entity\OrderStatus;
use Inpsyde\PayPalCommerce\ApiClient\Factory\OrderFactory;
use Inpsyde\PayPalCommerce\ApiClient\Repository\CartRepository;
use Inpsyde\PayPalCommerce\Session\SessionHandler;
use Inpsyde\PayPalCommerce\WcGateway\Notice\AuthorizeOrderActionNotice;
use Inpsyde\PayPalCommerce\WcGateway\Processor\AuthorizedPaymentsProcessor;
use Inpsyde\PayPalCommerce\WcGateway\Processor\Processor;
use Inpsyde\PayPalCommerce\WcGateway\Settings\SettingsFields;

//phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
//phpcs:disable Inpsyde.CodeQuality.ArgumentTypeDeclaration.NoArgumentType
class WcGateway extends WcGatewayBase implements WcGatewayInterface
{

    public const CAPTURED_META_KEY = '_ppcp_paypal_captured';
    public const INTENT_META_KEY = '_ppcp_paypal_intent';
    public const ORDER_ID_META_KEY = '_ppcp_paypal_order_id';

    private $isSandbox = true;
    private $sessionHandler;
    private $orderEndpoint;
    private $cartRepository;
    private $orderFactory;
    private $settingsFields;
    private $paymentsEndpoint;
    private $processor;
    private $notice;

    public function __construct(
        SessionHandler $sessionHandler,
        CartRepository $cartRepository,
        OrderEndpoint $orderEndpoint,
        PaymentsEndpoint $paymentsEndpoint,
        OrderFactory $orderFactory,
        SettingsFields $settingsFields,
        Processor $processor,
        AuthorizeOrderActionNotice $notice
    ) {

        $this->sessionHandler = $sessionHandler;
        $this->cartRepository = $cartRepository;
        $this->orderEndpoint = $orderEndpoint;
        $this->paymentsEndpoint = $paymentsEndpoint;
        $this->orderFactory = $orderFactory;
        $this->settingsFields = $settingsFields;
        $this->processor = $processor;
        $this->notice = $notice;

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

        $order = $this->sessionHandler->order();
        $wcOrder->update_meta_data(self::ORDER_ID_META_KEY, $order->id());
        $wcOrder->update_meta_data(self::INTENT_META_KEY, $order->intent());

        $errorMessage = null;
        if (!$order || !$order->status()->is(OrderStatus::APPROVED)) {
            $errorMessage = __('The payment has not been approved yet.', 'woocommerce-paypal-gateway');
        }
        if ($errorMessage) {
            $notice = sprintf(
                // translators %s is the message of the error.
                __('Payment error: %s', 'woocommerce-paypal-gateway'),
                $errorMessage
            );
            wc_add_notice($notice, 'error');
            return null;
        }

        $order = $this->patchOrder($wcOrder, $order);
        if ($order->intent() === 'CAPTURE') {
            $order = $this->orderEndpoint->capture($order);
        }

        if ($order->intent() === 'AUTHORIZE') {
            $order = $this->orderEndpoint->authorize($order);
            $wcOrder->update_meta_data(self::CAPTURED_META_KEY, 'false');
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

    public function captureAuthorizedPayment(\WC_Order $wcOrder): void
    {
        $result = $this->processor->authorizedPayments()->process($wcOrder);

        if ($result === AuthorizedPaymentsProcessor::INACCESSIBLE) {
            $this->notice->displayMessage(AuthorizeOrderActionNotice::NO_INFO);
        }
        if ($result === AuthorizedPaymentsProcessor::NOT_FOUND) {
            $this->notice->displayMessage(AuthorizeOrderActionNotice::NOT_FOUND);
        }

        if ($result === AuthorizedPaymentsProcessor::ALREADY_CAPTURED) {
            if ($wcOrder->get_status() === 'on-hold') {
                $wcOrder->add_order_note(
                    __(
                        'Payment successfully captured.',
                        'woocommerce-paypal-gateway'
                    )
                );
                $wcOrder->update_status('processing');
                $wcOrder->update_meta_data(self::CAPTURED_META_KEY, 'true');
                // TODO investigate why save has to be called
                $wcOrder->save();
            }

            $this->notice->displayMessage(AuthorizeOrderActionNotice::ALREADY_CAPTURED);
        }

        if ($result === AuthorizedPaymentsProcessor::FAILED) {
            $this->notice->displayMessage(AuthorizeOrderActionNotice::FAILED);
        }

        if ($result === AuthorizedPaymentsProcessor::SUCCESSFUL) {
            $wcOrder->add_order_note(
                __(
                    'Payment successfully captured.',
                    'woocommerce-paypal-gateway'
                )
            );

            $wcOrder->update_status('processing');
            $wcOrder->update_meta_data(self::CAPTURED_META_KEY, 'true');
            // TODO investigate why save has to be called
            $wcOrder->save();

            $this->notice->displayMessage(AuthorizeOrderActionNotice::SUCCESS);
        }
    }

    private function patchOrder(\WC_Order $wcOrder, Order $order): Order
    {
        $updatedOrder = $this->orderFactory->fromWcOrder($wcOrder, $order);
        $order = $this->orderEndpoint->patchOrderWith($order, $updatedOrder);
        return $order;
    }
}
