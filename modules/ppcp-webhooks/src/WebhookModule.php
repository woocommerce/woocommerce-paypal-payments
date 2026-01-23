<?php
/**
 * The webhook module.
 *
 * @package WooCommerce\PayPalCommerce\Webhooks
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Webhooks;

use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExecutableModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\FactoryModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ServiceModule;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\Webhooks\Endpoint\ResubscribeEndpoint;
use WooCommerce\PayPalCommerce\Webhooks\Endpoint\SimulateEndpoint;
use WooCommerce\PayPalCommerce\Webhooks\Endpoint\SimulationStateEndpoint;

/**
 * Class WebhookModule
 */
class WebhookModule implements ServiceModule, FactoryModule, ExecutableModule {
	use ModuleClassNameIdTrait;

	/**
	 * {@inheritDoc}
	 */
	public function services(): array {
		return require __DIR__ . '/../services.php';
	}

	/**
	 * {@inheritDoc}
	 */
	public function factories(): array {
		return require __DIR__ . '/../factories.php';
	}

	/**
	 * {@inheritDoc}
	 */
	public function run( ContainerInterface $container ): bool {

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

		add_action(
			'wc_ajax_' . ResubscribeEndpoint::ENDPOINT,
			static function () use ( $container ) {
				$endpoint = $container->get( 'webhook.endpoint.resubscribe' );
				assert( $endpoint instanceof ResubscribeEndpoint );

				$endpoint->handle_request();
			}
		);

		add_action(
			'wc_ajax_' . SimulateEndpoint::ENDPOINT,
			static function () use ( $container ) {
				$endpoint = $container->get( 'webhook.endpoint.simulate' );
				assert( $endpoint instanceof SimulateEndpoint );

				$endpoint->handle_request();
			}
		);
		add_action(
			'wc_ajax_' . SimulationStateEndpoint::ENDPOINT,
			static function () use ( $container ) {
				$endpoint = $container->get( 'webhook.endpoint.simulation-state' );
				assert( $endpoint instanceof SimulationStateEndpoint );

				$endpoint->handle_request();
			}
		);

		return true;
	}
}
