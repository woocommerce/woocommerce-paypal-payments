<?php
/**
 * The webhook module factories.
 *
 * @package WooCommerce\PayPalCommerce\Webhooks
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Webhooks;

use WooCommerce\PayPalCommerce\ApiClient\Endpoint\WebhookEndpoint;
use WooCommerce\PayPalCommerce\Onboarding\State;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

return array(
	'webhook.status.registered-webhooks' => function( ContainerInterface $container ) : array {
		$endpoint = $container->get( 'api.endpoint.webhook' );
		assert( $endpoint instanceof WebhookEndpoint );

		$state = $container->get( 'onboarding.state' );
		if ( $state->current_state() >= State::STATE_ONBOARDED ) {
			return $endpoint->list();
		}

		return array();
	},
);
