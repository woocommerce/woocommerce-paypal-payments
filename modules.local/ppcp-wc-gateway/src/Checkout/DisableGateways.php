<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\WcGateway\Checkout;

use Inpsyde\PayPalCommerce\Session\SessionHandler;
use Inpsyde\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use Inpsyde\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use Psr\Container\ContainerInterface;

class DisableGateways
{

    private $sessionHandler;
    private $settings;
    public function __construct(
        SessionHandler $sessionHandler,
        ContainerInterface $settings
    ) {

        $this->sessionHandler = $sessionHandler;
        $this->settings = $settings;
    }

    public function handler(array $methods): array
    {
        if (! isset($methods[PayPalGateway::ID])) {
            return $methods;
        }
        if (
            ! $this->settings->has('merchant_email')
            || ! is_email($this->settings->get('merchant_email'))
        ) {
            unset($methods[PayPalGateway::ID]);
            unset($methods[CreditCardGateway::ID]);
            return $methods;
        }

        if (! $this->settings->has('client_id') || empty($this->settings->get('client_id'))) {
            unset($methods[CreditCardGateway::ID]);
        }


        if (! $this->needsToDisableGateways()) {
            return $methods;
        }

        return [PayPalGateway::ID => $methods[PayPalGateway::ID]];
    }

    private function needsToDisableGateways(): bool
    {
        return $this->sessionHandler->order() !== null;
    }
}
