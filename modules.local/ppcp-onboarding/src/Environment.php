<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Onboarding;

use Psr\Container\ContainerInterface;

class Environment
{

    public const PRODUCTION = 'production';
    public const SANDBOX = 'sandbox';

    private $settings;
    public function __construct(ContainerInterface $settings)
    {
        $this->settings = $settings;
    }

    public function currentEnvironment(): string
    {
        return (
            $this->settings->has('sandbox_on') && $this->settings->get('sandbox_on')
        ) ? self::SANDBOX : self::PRODUCTION;
    }

    public function currentEnvironmentIs(string $environment): bool
    {
        return $this->currentEnvironment() === $environment;
    }
}
