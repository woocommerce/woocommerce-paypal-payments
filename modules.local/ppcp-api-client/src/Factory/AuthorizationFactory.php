<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Factory;

use Inpsyde\PayPalCommerce\ApiClient\Entity\Authorization;
use Inpsyde\PayPalCommerce\ApiClient\Entity\AuthorizationStatus;
use Inpsyde\PayPalCommerce\ApiClient\Exception\RuntimeException;

class AuthorizationFactory
{
    public function fromPayPalRequest(\stdClass $data): Authorization
    {
        if (!isset($data->id)) {
            throw new RuntimeException(
                __(
                    'Does not contain an id.',
                    'woocommerce-paypal-commerce-gateway'
                )
            );
        }

        if (!isset($data->status)) {
            throw new RuntimeException(
                __(
                    'Does not contain status.',
                    'woocommerce-paypal-commerce-gateway'
                )
            );
        }

        return new Authorization(
            $data->id,
            new AuthorizationStatus($data->status)
        );
    }
}
