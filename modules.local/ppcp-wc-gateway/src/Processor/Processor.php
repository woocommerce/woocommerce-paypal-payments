<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\WcGateway\Processor;

class Processor
{
    protected $authorizedPaymentsProcessor;

    public function __construct(AuthorizedPaymentsProcessor $authorizedPaymentsProcessor)
    {
        $this->authorizedPaymentsProcessor = $authorizedPaymentsProcessor;
    }

    public function authorizedPayments(): AuthorizedPaymentsProcessor
    {

        return $this->authorizedPaymentsProcessor;
    }
}
