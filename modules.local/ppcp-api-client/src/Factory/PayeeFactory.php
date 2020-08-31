<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Factory;

use Inpsyde\PayPalCommerce\ApiClient\Entity\Payee;
use Inpsyde\PayPalCommerce\ApiClient\Exception\RuntimeException;

class PayeeFactory
{

    public function fromPayPalResponse(\stdClass $data): ?Payee
    {
        if (! isset($data->email_address)) {
            throw new RuntimeException(
                __("No email for payee given.", "woocommerce-paypal-commerce-gateway")
            );
        }

        $merchantId = (isset($data->merchant_id)) ? $data->merchant_id : '';
        return new Payee($data->email_address, $merchantId);
    }
}
