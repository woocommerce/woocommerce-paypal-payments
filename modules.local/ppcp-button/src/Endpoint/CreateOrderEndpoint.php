<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Button\Endpoint;

use Inpsyde\PayPalCommerce\ApiClient\Entity\Order;
use Inpsyde\PayPalCommerce\ApiClient\Entity\PaymentMethod;
use Inpsyde\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use Inpsyde\PayPalCommerce\ApiClient\Factory\PayerFactory;
use Inpsyde\PayPalCommerce\Button\Exception\RuntimeException;
use Inpsyde\PayPalCommerce\ApiClient\Repository\CartRepository;
use Inpsyde\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use Inpsyde\PayPalCommerce\Button\Helper\EarlyOrderHandler;
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
    private $earlyOrderHandler;

    private $order;
    public function __construct(
        RequestData $requestData,
        CartRepository $repository,
        OrderEndpoint $apiEndpoint,
        PayerFactory $payerFactory,
        SessionHandler $sessionHandler,
        Settings $settings,
        EarlyOrderHandler $earlyOrderHandler
    ) {

        $this->requestData = $requestData;
        $this->repository = $repository;
        $this->apiEndpoint = $apiEndpoint;
        $this->payerFactory = $payerFactory;
        $this->sessionHandler = $sessionHandler;
        $this->settings = $settings;
        $this->earlyOrderHandler = $earlyOrderHandler;
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
                [
                    'name' => is_a($error, PayPalApiException::class) ? $error->name() : '',
                    'message' => $error->getMessage(),
                    'code' => $error->getCode(),
                    'details' => is_a($error, PayPalApiException::class) ? $error->details() : [],
                ]
            );
            return false;
        }
    }

    private function validateForm(string $formValues, Order $order)
    {
        $this->order = $order;
        $parsedValues = wp_parse_args($formValues);
        $_POST = $parsedValues;
        $_REQUEST = $parsedValues;

        add_filter(
            'woocommerce_after_checkout_validation',
            [
                $this,
                'afterCheckoutValidation',
            ],
            10,
            2
        );
        $checkout = \WC()->checkout();
        $checkout->process_checkout();
    }

    public function afterCheckoutValidation(array $data, \WP_Error $errors): array
    {

        $order = $this->order;
        if (! $errors->errors) {

            /**
             * In case we are onboarded and everything is fine with the \WC_Order
             * we want this order to be created. We will intercept it and leave it
             * in the "Pending payment" status though, which than later will change
             * during the "onApprove"-JS callback or the webhook listener.
             */
            if (! $this->earlyOrderHandler->shouldCreateEarlyOrder()) {
                wp_send_json_success($order->toArray());
            }
            $this->earlyOrderHandler->registerForOrder($order);
            return $data;
        }

        wp_send_json_error(
            [
                'name' => '',
                'message' => $errors->get_error_message(),
                'code' => (int) $errors->get_error_code(),
                'details' => [],
            ]
        );
        return $data;
    }
}
