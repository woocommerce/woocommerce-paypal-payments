<?php
/**
 * The Axo Block module.
 *
 * @package WooCommerce\PayPalCommerce\AxoBlock
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\AxoBlock;

use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\SdkClientToken;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\Blocks\Endpoint\UpdateShippingEndpoint;
use WooCommerce\PayPalCommerce\Button\Assets\SmartButtonInterface;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExecutableModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExtendingModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ServiceModule;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

/**
 * Class AxoBlockModule
 */
class AxoBlockModule implements ServiceModule, ExtendingModule, ExecutableModule {
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
	public function extensions(): array {
		return require __DIR__ . '/../extensions.php';
	}

	/**
	 * {@inheritDoc}
	 */
	public function run( ContainerInterface $c ): bool {
		if (
			! class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' )
			|| ! function_exists( 'woocommerce_store_api_register_payment_requirements' )
		) {
			add_action(
				'admin_notices',
				function () {
					printf(
						'<div class="notice notice-error"><p>%1$s</p></div>',
						wp_kses_post(
							__(
								'Fastlane checkout block initialization failed, possibly old WooCommerce version or disabled WooCommerce Blocks plugin.',
								'woocommerce-paypal-payments'
							)
						)
					);
				}
			);
		}

		add_action(
			'wp_loaded',
			function () use ( $c ) {
				add_filter(
					'woocommerce_paypal_payments_localized_script_data',
					function( array $localized_script_data ) use ( $c ) {
						$module = $this;
						$api    = $c->get( 'api.sdk-client-token' );
						assert( $api instanceof SdkClientToken );

						$logger = $c->get( 'woocommerce.logger.woocommerce' );
						assert( $logger instanceof LoggerInterface );

						return $module->add_sdk_client_token_to_script_data( $api, $logger, $localized_script_data );
					}
				);

				/**
				 * Param types removed to avoid third-party issues.
				 *
				 * @psalm-suppress MissingClosureParamType
				 */
				add_filter(
					'woocommerce_paypal_payments_sdk_components_hook',
					function( $components ) {
						$components[] = 'fastlane';
						return $components;
					}
				);
			}
		);

		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			function( PaymentMethodRegistry $payment_method_registry ) use ( $c ): void {
				$payment_method_registry->register( $c->get( 'axoblock.method' ) );
			}
		);

		// Enqueue frontend scripts.
		add_action(
			'wp_enqueue_scripts',
			static function () use ( $c ) {
				if ( ! has_block( 'woocommerce/checkout' ) && ! has_block( 'woocommerce/cart' ) ) {
					return;
				}

				$module_url    = $c->get( 'axoblock.url' );
				$asset_version = $c->get( 'ppcp.asset-version' );

				wp_register_style(
					'wc-ppcp-axo-block',
					untrailingslashit( $module_url ) . '/assets/css/gateway.css',
					array(),
					$asset_version
				);
				wp_enqueue_style( 'wc-ppcp-axo-block' );
			}
		);
		return true;
	}

	/**
	 * Adds id token to localized script data.
	 *
	 * @param SdkClientToken  $api User id token api.
	 * @param LoggerInterface $logger The logger.
	 * @param array           $localized_script_data The localized script data.
	 * @return array
	 */
	private function add_sdk_client_token_to_script_data(
		SdkClientToken $api,
		LoggerInterface $logger,
		array $localized_script_data
	): array {
		try {
			$sdk_client_token             = $api->sdk_client_token();
			$localized_script_data['axo'] = array(
				'sdk_client_token' => $sdk_client_token,
			);

		} catch ( RuntimeException $exception ) {
			$error = $exception->getMessage();
			if ( is_a( $exception, PayPalApiException::class ) ) {
				$error = $exception->get_details( $error );
			}

			$logger->error( $error );
		}

		return $localized_script_data;
	}
}
