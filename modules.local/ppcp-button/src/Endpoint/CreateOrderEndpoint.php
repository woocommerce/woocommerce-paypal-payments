<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Button\Endpoint;

use Inpsyde\PayPalCommerce\ApiClient\Entity\Order;
use Inpsyde\PayPalCommerce\ApiClient\Entity\PaymentMethod;
use Inpsyde\PayPalCommerce\ApiClient\Factory\PayerFactory;
use Inpsyde\PayPalCommerce\Button\Exception\RuntimeException;
use Inpsyde\PayPalCommerce\ApiClient\Repository\CartRepository;
use Inpsyde\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use Inpsyde\PayPalCommerce\Onboarding\State;
use Inpsyde\PayPalCommerce\Session\SessionHandler;
use Inpsyde\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use Inpsyde\PayPalCommerce\WcGateway\Processor\OrderProcessor;
use Inpsyde\PayPalCommerce\WcGateway\Settings\Settings;

class CreateOrderEndpoint implements EndpointInterface
{

    public const ENDPOINT = 'ppc-create-order';

    private $requestData;
    private $repository;
    private $apiEndpoint;
    private $payerFactory;
    private $sessionHandler;
    private $settings;
    private $state;
    private $orderProcessor;
    public function __construct(
        RequestData $requestData,
        CartRepository $repository,
        OrderEndpoint $apiEndpoint,
        PayerFactory $payerFactory,
        SessionHandler $sessionHandler,
        Settings $settings,
        State $state,
        OrderProcessor $orderProcessor
    ) {

        $this->requestData = $requestData;
        $this->repository = $repository;
        $this->apiEndpoint = $apiEndpoint;
        $this->payerFactory = $payerFactory;
        $this->sessionHandler = $sessionHandler;
        $this->settings = $settings;
        $this->state = $state;
        $this->orderProcessor = $orderProcessor;
    }

    public static function nonce(): string
    {
        return self::ENDPOINT;
    }

    public function handleRequest(): bool
    {
        try {
            $data = $this->requestData->readRequest($this->nonce());
            $purchaseUnits = $this->repository->all();
            $payer = null;
            if (isset($data['payer']) && $data['payer']) {
                if (isset($data['payer']['phone']['phone_number']['national_number'])) {
                    // make sure the phone number contains only numbers and is max 14. chars long.
                    $number = $data['payer']['phone']['phone_number']['national_number'];
                    $number = preg_replace("/[^0-9]/", "", $number);
                    $number = substr($number, 0, 14);
                    $data['payer']['phone']['phone_number']['national_number'] = $number;
                }
                $payer = $this->payerFactory->fromPayPalResponse(json_decode(json_encode($data['payer'])));
            }
            $bnCode = isset($data['bn_code']) ? (string) $data['bn_code'] : '';
            if ($bnCode) {
                $this->sessionHandler->replaceBnCode($bnCode);
                $this->apiEndpoint->withBnCode($bnCode);
            }
            $payeePreferred = $this->settings->has('payee_preferred')
            && $this->settings->get('payee_preferred') ?
                PaymentMethod::PAYEE_PREFERRED_IMMEDIATE_PAYMENT_REQUIRED
                : PaymentMethod::PAYEE_PREFERRED_UNRESTRICTED;
            $paymentMethod = new PaymentMethod($payeePreferred);
            $order = $this->apiEndpoint->createForPurchaseUnits(
                $purchaseUnits,
                $payer,
                null,
                $paymentMethod
            );
            if ($data['context'] === 'checkout') {
                    $this->validateForm($data['form'], $order);
            }
            wp_send_json_success($order->toArray());
            return true;
        } catch (\RuntimeException $error) {
            wp_send_json_error(
                __('Something went wrong. Please try again or choose another payment source.', 'woocommerce-paypal-commerce-gateway')
            );
            return false;
        }
    }

    private function validateForm(string $formValues, Order $order) {
        $parsedValues = wp_parse_args($formValues);
        $_POST = $parsedValues;
        $_REQUEST = $parsedValues;
        add_filter(
            'woocommerce_after_checkout_validation',
            function($data, \WP_Error $errors) use ($parsedValues, $order) {
                if (! $errors->errors) {

                    /**
                     * In case we are onboarded and everything is fine with the \WC_Order
                     * we want this order to be created. We will intercept it and leave it
                     * in the "Pending payment" status though, which than later will change
                     * during the "onApprove"-JS callback or the webhook listener.
                     */
                    if ($this->state->currentState() === State::STATE_ONBOARDED) {
                        $this->createOrder($parsedValues, $order);
                        return $data;
                    }
                    wp_send_json_success($order->toArray());
                }
                wp_send_json_error($errors->get_error_message());
            },
            10,
            2
        );
        $checkout = \WC()->checkout();
        $checkout->process_checkout();
    }

    private function createOrder(array $parsedValues, Order $order) {
        add_action(
            'woocommerce_checkout_order_processed',
            function($orderId) use ($order) {
                try {
                    $wcOrder = wc_get_order($orderId);
                    $wcOrder->update_meta_data(PayPalGateway::ORDER_ID_META_KEY, $order->id());
                    $wcOrder->update_meta_data(PayPalGateway::INTENT_META_KEY, $order->intent());
                    $wcOrder->save_meta_data();
                    WC()->session->set('order_awaiting_payment', $orderId);
                    $order = $this->orderProcessor->patchOrder($wcOrder, $order);
                    wp_send_json_success($order->toArray());
                }  catch (\RuntimeException $error) {
                    wp_send_json_error(
                        __('Something went wrong. Please try again or choose another payment source.', 'woocommerce-paypal-commerce-gateway')
                    );
                    return false;
                }
            }
        );
    }
}
