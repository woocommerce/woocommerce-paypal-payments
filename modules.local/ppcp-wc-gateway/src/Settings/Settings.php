<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\WcGateway\Settings;

use Inpsyde\PayPalCommerce\WcGateway\Exception\NotFoundException;
use Inpsyde\PayPalCommerce\WcGateway\Gateway\WcGateway;
use Psr\Container\ContainerInterface;

class Settings implements ContainerInterface
{
    private $gateway;
    private $formFields;

    public function __construct(WcGateway $gateway, SettingsFields $formFields)
    {
        $this->gateway = $gateway;
        $this->formFields = $formFields;
    }

    // phpcs:disable Inpsyde.CodeQuality.ReturnTypeDeclaration.NoReturnType
    // phpcs:disable Inpsyde.CodeQuality.ArgumentTypeDeclaration.NoArgumentType
    public function get($id)
    {
        if (!$this->has($id)) {
            throw new NotFoundException();
        }
        return $this->gateway->get_option($id);
    }

    public function has($id)
    {
        return array_key_exists($id, $this->formFields->fields());
    }
}
