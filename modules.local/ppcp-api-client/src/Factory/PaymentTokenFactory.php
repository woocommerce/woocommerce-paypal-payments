<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Factory;

use Inpsyde\PayPalCommerce\ApiClient\Entity\PaymentToken;
use Inpsyde\PayPalCommerce\ApiClient\Exception\RuntimeException;

class PaymentTokenFactory
{

    public function fromPayPalResponse(\stdClass $data): PaymentToken
    {
        if (! isset($data->id)) {
            throw new RuntimeException(
                __("No id for payment token given", "woocommerce-paypal-commerce-gateway")
            );
        }
        return new PaymentToken(
            $data->id,
            (isset($data->type)) ? $data->type : PaymentToken::TYPE_PAYMENT_METHOD_TOKEN
        );
    }

    public function fromArray(array $data): PaymentToken
    {
        return $this->fromPayPalResponse((object) $data);
    }
}
