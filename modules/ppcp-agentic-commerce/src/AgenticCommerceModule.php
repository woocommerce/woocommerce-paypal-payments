<?php
/**
 * The agentic commerce module.
 *
 * @package WooCommerce\PayPalCommerce\AgenticCommerce
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce;

use WooCommerce\PayPalCommerce\AgenticCommerce\Ingestion\IngestionManager;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExecutableModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ServiceModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\AgenticCommerce\Endpoint\AgenticRestEndpoint;
use WooCommerce\PayPalCommerce\AgenticCommerce\Setting\AgenticSettingsModule;
use WooCommerce\PayPalCommerce\AgenticCommerce\Registration\RegistrationService;
use WooCommerce\PayPalCommerce\AgenticCommerce\Registration\RegistrationEligibility;
use WooCommerce\PayPalCommerce\AgenticCommerce\Setting\AgenticSettingsDataModel;

/**
 * Entry point that integrates agentic commerce logic with the plugin's DI system.
 * This module handles the initialization and execution of the agentic commerce functionality.
 */
class AgenticCommerceModule implements ServiceModule, ExecutableModule {
	use ModuleClassNameIdTrait;

	/**
	 * Returns the services provided by this module.
	 *
	 * @return array The array of services.
	 * A list of all REST services that this module needs to register on init.
	 */
	private const REST_ENDPOINT_SERVICES = array(
		'agentic.rest.create_cart',
		'agentic.rest.get_cart',
		'agentic.rest.update_cart',
		'agentic.rest.replace_cart',
	);

	public function services(): array {
		return require __DIR__ . '/../services.php';
	}

	/**
	 * Runs the module initialization.
	 *
	 * @param ContainerInterface $container The dependency injection container.
	 * @return bool True if the module was initialized successfully.
	 */
	public function run( ContainerInterface $container ): bool {
		$agentic_settings = $container->get( 'agentic.settings.model' );
		assert( $agentic_settings instanceof AgenticSettingsDataModel );

		$registration_handler = $container->get( 'agentic.registration.handler' );
		assert( $registration_handler instanceof RegistrationService );

		$eligibility_check = $container->get( 'agentic.registration.eligibility' );
		assert( $eligibility_check instanceof RegistrationEligibility );

		// Settings extension always available (merchants need to see the toggle).
		$settings_module = $container->get( 'agentic.settings.module' );
		assert( $settings_module instanceof AgenticSettingsModule );
		$settings_module->init();

		// Uninstall logic always registered.
		$this->add_uninstall_action( $registration_handler );

		// Sync eligibility cache on init (when WC is available).
		$this->sync_eligibility_cache( $agentic_settings, $eligibility_check );

		// Early exit if features should not be initialized.
		if ( ! $agentic_settings->should_initialize_features() ) {
			$this->ensure_deregistered( $registration_handler );

			// todo: also remove scheduled action?

			return true;
		}

		// Feature is active and merchant is eligible: Initialize everything.

		$this->ensure_registered( $registration_handler );

		// Public REST endpoints.
		add_action(
			'rest_api_init',
			static function () use ( $container ): void {
				foreach ( self::REST_ENDPOINT_SERVICES as $service_id ) {
					$endpoint = $container->get( $service_id );
					assert( $endpoint instanceof AgenticRestEndpoint );
					$endpoint->register_routes();
				}
			}
		);

		// Product ingestion.
		add_action(
			'init',
			static function () use ( $container ) {
				$ingestion_manager = $container->get( 'agentic.ingestion-manager' );
				assert( $ingestion_manager instanceof IngestionManager );
				$ingestion_manager->init();
			}
		);

		return true;
	}

	/**
	 * Intentionally a separate method to make uninstall logic stand out.
	 */
	private function add_uninstall_action( RegistrationService $registration_service ): void {
		add_action(
			'woocommerce_paypal_payments_uninstall',
			static fn() => $registration_service->deregister()
		);
	}

	private function sync_eligibility_cache( AgenticSettingsDataModel $settings, RegistrationEligibility $eligibility_check ): void {
		add_action(
			'init',
			static function () use ( $settings, $eligibility_check ) {
				if ( $settings->is_eligible() === $eligibility_check->is_eligible() ) {
					return;
				}

				$settings->set_eligible( $eligibility_check->is_eligible() );
				$settings->save();
			}
		);
	}

	private function ensure_registered( RegistrationService $registration_service ): void {
		if ( $registration_service->is_registered() ) {
			return;
		}
		add_action( 'init', static fn() => $registration_service->register() );
	}

	private function ensure_deregistered( RegistrationService $registration_service ): void {
		if ( ! $registration_service->is_registered() ) {
			return;
		}
		add_action( 'init', static fn() => $registration_service->deregister() );
	}
}
