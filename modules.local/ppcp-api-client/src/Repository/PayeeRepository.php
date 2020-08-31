<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Repository;

use Inpsyde\PayPalCommerce\ApiClient\Config\Config;
use Inpsyde\PayPalCommerce\ApiClient\Entity\Payee;

class PayeeRepository
{

    private $merchantEmail;
    private $merchantId;
    public function __construct(string $merchantEmail, string $merchantId)
    {
        $this->merchantEmail = $merchantEmail;
        $this->merchantId = $merchantId;
    }

    public function payee(): Payee
    {
        return new Payee(
            $this->merchantEmail,
            $this->merchantId
        );
    }
}
