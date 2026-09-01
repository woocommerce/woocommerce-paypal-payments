<?php
/**
 * The factories of the API client.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient;

use WooCommerce\PayPalCommerce\ApiClient\Endpoint\WebhookEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Factory\ExperienceContextBuilder;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Helper\Environment;

return array(

	'wcgateway.builder.experience-context' => static function ( ContainerInterface $container ): ExperienceContextBuilder {
		return new ExperienceContextBuilder(
			$container->get( 'settings.settings-provider' ),
			$container->get( 'wcgateway.shipping.callback.factory.url' )
		);
	},
	'api.host'                              => static function ( ContainerInterface $container ): string {
		$environment = $container->get( 'settings.environment' );
		assert( $environment instanceof Environment );

		if ( $environment->is_sandbox() ) {
			return (string) $container->get( 'api.sandbox-host' );
		}

		return (string) $container->get( 'api.production-host' );
	},
	'api.endpoint.webhook'                  => static function ( ContainerInterface $container ): WebhookEndpoint {
		return new WebhookEndpoint(
			$container->get( 'api.host' ),
			$container->get( 'api.bearer' ),
			$container->get( 'api.factory.webhook' ),
			$container->get( 'api.factory.webhook-event' ),
			$container->get( 'woocommerce.logger.woocommerce' )
		);
	},
);
