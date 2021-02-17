<?php
/**
 * The webhook module.
 *
 * @package WooCommerce\PayPalCommerce\Webhooks
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Webhooks;

use Dhii\Container\ServiceProvider;
use Dhii\Modular\Module\ModuleInterface;
use Interop\Container\ServiceProviderInterface;
use Psr\Container\ContainerInterface;

/**
 * Class WebhookModule
 */
class WebhookModule implements ModuleInterface {

	/**
	 * Setup the Webhook module.
	 *
	 * @return ServiceProviderInterface
	 */
	public function setup(): ServiceProviderInterface {
		return new ServiceProvider(
			require __DIR__ . '/../services.php',
			require __DIR__ . '/../extensions.php'
		);
	}

	/**
	 * Run the Webhook module.
	 *
	 * @param ContainerInterface|null $container The Container.
	 */
	public function run( ContainerInterface $container = null ) {
		add_action(
			'rest_api_init',
			static function () use ( $container ) {
				$endpoint = $container->get( 'webhook.endpoint.controller' );
				/**
				 * The Incoming Webhook Endpoint.
				 *
				 * @var IncomingWebhookEndpoint $endpoint
				 */
				$endpoint->register();
			}
		);

		add_action(
			WebhookRegistrar::EVENT_HOOK,
			static function () use ( $container ) {
				$registrar = $container->get( 'webhook.registrar' );
				/**
				 * The Webhook Registrar.
				 *
				 * @var WebhookRegistrar $endpoint
				 */
				$registrar->register();
			}
		);

		add_action(
			'woocommerce_paypal_payments_gateway_deactivate',
			static function () use ( $container ) {
				$registrar = $container->get( 'webhook.registrar' );
				/**
				 * The Webhook Registrar.
				 *
				 * @var WebhookRegistrar $endpoint
				 */
				$registrar->unregister();
			}
		);
	}

	/**
	 * Returns the key for the module.
	 *
	 * @return string|void
	 */
	public function getKey() {
	}
}
