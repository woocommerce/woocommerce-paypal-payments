<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Config;

use Inpsyde\PayPalCommerce\ApiClient\Exception\NotFoundException;
use Psr\Container\ContainerInterface;

// phpcs:disable Inpsyde.CodeQuality.ReturnTypeDeclaration.NoReturnType
// phpcs:disable Inpsyde.CodeQuality.ArgumentTypeDeclaration.NoArgumentType

/**
 * Class Config
 * This container contains settings, which are not handled by the gateway's userinterface.
 *
 * ToDo: We need to read the configuration from somewhere.
 * @package Inpsyde\PayPalCommerce\ApiClient\Config
 */
class Config implements ContainerInterface
{

    private $config = [
        'merchant_email' => 'payment-facilitator@websupporter.net',
        'merchant_id' => '939Y32KZSLC8G',
    ];

    public function get($id)
    {
        if (! $this->has($id)) {
            throw new NotFoundException();
        }
        return $this->config[$id];
    }

    public function has($id)
    {
        return array_key_exists($id, $this->config);
    }
}
