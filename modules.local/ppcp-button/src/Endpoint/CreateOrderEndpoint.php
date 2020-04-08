<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Button\Endpoint;

use Inpsyde\PayPalCommerce\Button\Exception\RuntimeException;
use Inpsyde\PayPalCommerce\ApiClient\Repository\CartRepository;
use Inpsyde\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;

class CreateOrderEndpoint implements EndpointInterface
{

    const ENDPOINT = 'ppc-create-order';

    private $requestData;
    private $repository;
    private $apiEndpoint;
    public function __construct(
        RequestData $requestData,
        CartRepository $repository,
        OrderEndpoint $apiEndpoint
    ) {

        $this->requestData = $requestData;
        $this->repository = $repository;
        $this->apiEndpoint = $apiEndpoint;
    }

    public static function nonce() : string
    {
        return self::ENDPOINT . get_current_user_id();
    }

    public function handleRequest() : bool
    {
        try {
            $this->requestData->readRequest($this->nonce());
            $purchaseUnits = $this->repository->all();
            $order = $this->apiEndpoint->createForPurchaseUnits(
                ...$purchaseUnits
            );
            wp_send_json_success($order->toArray());
            return true;
        } catch (\RuntimeException $error) {
            wp_send_json_error($error->getMessage());
            return false;
        }
    }
}
