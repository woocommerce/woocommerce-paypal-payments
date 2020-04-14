<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Repository;

use Inpsyde\PayPalCommerce\ApiClient\Config\Config;
use Inpsyde\PayPalCommerce\ApiClient\Entity\Payee;

class PayeeRepository
{

    private $config;
    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function payee() : Payee
    {
        $merchantEmail = $this->config->get('merchant_email');
        $merchantId = ($this->config->has('merchant_id')) ? $this->config->get('merchant_id') : '';

        return new Payee(
            $merchantEmail,
            $merchantId
        );
    }
}
