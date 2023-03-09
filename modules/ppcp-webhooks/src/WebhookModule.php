<?php
/**
 * The webhook module.
 *
 * @package WooCommerce\PayPalCommerce\Webhooks
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Webhooks;

use WooCommerce\PayPalCommerce\Vendor\Dhii\Container\ServiceProvider;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Modular\Module\ModuleInterface;
use Exception;
use WooCommerce\PayPalCommerce\Vendor\Interop\Container\ServiceProviderInterface;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
use WooCommerce\PayPalCommerce\Webhooks\Endpoint\ResubscribeEndpoint;
use WooCommerce\PayPalCommerce\Webhooks\Endpoint\SimulateEndpoint;
use WooCommerce\PayPalCommerce\Webhooks\Endpoint\SimulationStateEndpoint;
use WooCommerce\PayPalCommerce\Webhooks\Status\Assets\WebhooksStatusPageAssets;

/**
 * Class WebhookModule
 */
class WebhookModule implements ModuleInterface {

	/**
	 * {@inheritDoc}
	 */
	public function setup(): ServiceProviderInterface {
		return new ServiceProvider(
			require __DIR__ . '/../services.php',
			require __DIR__ . '/../extensions.php'
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function run( ContainerInterface $container ): void {
		$logger = $container->get( 'woocommerce.logger.woocommerce' );
		assert( $logger instanceof LoggerInterface );

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

		$page_id = $container->get( 'wcgateway.current-ppcp-settings-page-id' );
		if ( Settings::CONNECTION_TAB_ID === $page_id ) {
			$asset_loader = $container->get( 'webhook.status.assets' );
			assert( $asset_loader instanceof WebhooksStatusPageAssets );
			add_action(
				'init',
				array( $asset_loader, 'register' )
			);
			add_action(
				'admin_enqueue_scripts',
				array( $asset_loader, 'enqueue' )
			);

			try {
				$webhooks = $container->get( 'webhook.status.registered-webhooks' );

				if ( empty( $webhooks ) ) {
					$registrar = $container->get( 'webhook.registrar' );
					assert( $registrar instanceof WebhookRegistrar );

					// Looks like we cannot call rest_url too early.
					add_action(
						'init',
						function () use ( $registrar ) {
							$registrar->register();
						}
					);
				}
			} catch ( Exception $exception ) {
				$logger->error( 'Failed to load webhooks list: ' . $exception->getMessage() );
			}
		}

		add_action(
			'woocommerce_paypal_payments_gateway_migrate',
			static function () use ( $container ) {
				$registrar = $container->get( 'webhook.registrar' );
				assert( $registrar instanceof WebhookRegistrar );
				add_action(
					'init',
					function () use ( $registrar ) {
						$registrar->register();
					}
				);
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
