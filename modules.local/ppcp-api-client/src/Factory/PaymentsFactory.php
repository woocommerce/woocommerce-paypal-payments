<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Factory;

use Inpsyde\PayPalCommerce\ApiClient\Entity\Authorization;
use Inpsyde\PayPalCommerce\ApiClient\Entity\Payments;

class PaymentsFactory
{
    private $authorizationsFactory;

    public function __construct(
        AuthorizationFactory $authorizationsFactory
    ) {

        $this->authorizationsFactory = $authorizationsFactory;
    }

    public function fromPayPalResponse(\stdClass $data)
    {
        $authorizations = array_map(
            function (\stdClass $authorization): Authorization {
                return $this->authorizationsFactory->fromPayPalRequest($authorization);
            },
            $data->authorizations
        );
        $payments = new Payments($authorizations);
        return $payments;
    }
}
