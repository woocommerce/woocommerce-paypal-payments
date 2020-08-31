<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Factory;

use Inpsyde\PayPalCommerce\ApiClient\Entity\CardAuthenticationResult;
use Inpsyde\PayPalCommerce\ApiClient\Entity\PaymentSource;
use Inpsyde\PayPalCommerce\ApiClient\Entity\PaymentSourceCard;

class PaymentSourceFactory
{

    public function fromPayPalResponse(\stdClass $data): PaymentSource
    {

        $card = null;
        $wallet = null;
        if (isset($data->card)) {
            $authenticationResult = null;
            if (isset($data->card->authentication_result)) {
                $authenticationResult = new CardAuthenticationResult(
                    isset($data->card->authentication_result->liability_shift) ?
                        (string) $data->card->authentication_result->liability_shift : '',
                    isset($data->card->authentication_result->three_d_secure->enrollment_status) ?
                        (string) $data->card->authentication_result->three_d_secure->enrollment_status : '',
                    isset($data->card->authentication_result->three_d_secure->authentication_result) ?
                        (string) $data->card->authentication_result->three_d_secure->authentication_result : ''
                );
            }
            $card = new PaymentSourceCard(
                isset($data->card->last_digits) ? (string) $data->card->last_digits : '',
                isset($data->card->brand) ? (string) $data->card->brand : '',
                isset($data->card->type) ? (string) $data->card->type : '',
                $authenticationResult
            );
        }
        return new PaymentSource($card, $wallet);
    }
}
