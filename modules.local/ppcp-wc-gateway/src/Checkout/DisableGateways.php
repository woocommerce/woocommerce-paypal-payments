<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\WcGateway\Checkout;

use Inpsyde\PayPalCommerce\Session\SessionHandler;
use Inpsyde\PayPalCommerce\WcGateway\Gateway\WcGateway;
use Psr\Container\ContainerInterface;

class DisableGateways
{

    private $sessionHandler;
    private $settings;
    private $subscriptionDisable;
    public function __construct(
        SessionHandler $sessionHandler,
        ContainerInterface $settings,
        bool $subscriptionDisable
    ) {

        $this->sessionHandler = $sessionHandler;
        $this->settings = $settings;
        $this->subscriptionDisable = $subscriptionDisable;
    }

    public function handler(array $methods): array
    {
        if (! isset($methods[WcGateway::ID])) {
            return $methods;
        }
        if (
            ! $this->settings->has('merchant_email')
            || ! is_email($this->settings->get('merchant_email'))
        ) {
            unset($methods[WcGateway::ID]);
            return $methods;
        }

        if ($this->subscriptionDisable) {
            unset($methods[WcGateway::ID]);
            return $methods;
        }

        if (! $this->needsToDisableGateways()) {
            return $methods;
        }

        return [WcGateway::ID => $methods[WcGateway::ID]];
    }

    private function needsToDisableGateways(): bool
    {
        return $this->sessionHandler->order() !== null;
    }
}
