<?php

/**
 * The factories of the API client.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\ApiClient;

use WooCommerce\PayPalCommerce\ApiClient\Factory\ExperienceContextBuilder;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
return array('wcgateway.builder.experience-context' => static function (ContainerInterface $container): ExperienceContextBuilder {
    return new ExperienceContextBuilder($container->get('wcgateway.settings'), $container->get('wcgateway.shipping.callback.factory.url'));
});
