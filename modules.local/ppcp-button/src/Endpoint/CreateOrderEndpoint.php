<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Button\Endpoint;

use Inpsyde\PayPalCommerce\ApiClient\Factory\PayerFactory;
use Inpsyde\PayPalCommerce\Button\Exception\RuntimeException;
use Inpsyde\PayPalCommerce\ApiClient\Repository\CartRepository;
use Inpsyde\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;

class CreateOrderEndpoint implements EndpointInterface
{

    const ENDPOINT = 'ppc-create-order';

    private $requestData;
    private $repository;
    private $apiEndpoint;
    private $payerFactory;
    public function __construct(
        RequestData $requestData,
        CartRepository $repository,
        OrderEndpoint $apiEndpoint,
        PayerFactory $payerFactory
    ) {

        $this->requestData = $requestData;
        $this->repository = $repository;
        $this->apiEndpoint = $apiEndpoint;
        $this->payerFactory = $payerFactory;
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
            $order = $this->apiEndpoint->createForPurchaseUnits(
                $purchaseUnits,
                $payer
            );
            wp_send_json_success($order->toArray());
            return true;
        } catch (\RuntimeException $error) {
            wp_send_json_error($error->getMessage());
            return false;
        }
    }
}
