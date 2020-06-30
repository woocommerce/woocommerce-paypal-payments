<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Onboarding;


use Psr\Container\ContainerInterface;

class Environment
{

    public const PRODUCTION = 'production';
    public const SANDBOX = 'sandbox';
    public const VALID_ENVIRONMENTS = [
        self::PRODUCTION,
        self::SANDBOX,
    ];

    public const OPTION_KEY = 'ppcp-env';

    private $settings;
    public function __construct(ContainerInterface $settings)
    {
        $this->settings = $settings;
    }

    public function currentEnvironment() : string
    {
        return (
            $this->settings->has('sandbox_on') && wc_string_to_bool($this->settings->get('sandbox_on'))
        ) ? self::SANDBOX : self::PRODUCTION;
    }

    public function currentEnvironmentIs(string $environment) : bool {
        return $this->currentEnvironment() === $environment;
    }

    public function changeEnvironmentTo(string $environment) : bool
    {
        if (! in_array($environment, self::VALID_ENVIRONMENTS, true)) {
            return false;
        }
        update_option(self::OPTION_KEY, $environment);
        return true;
    }
}