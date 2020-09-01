<?php
/**
 * The Gateway module.
 *
 * @package Inpsyde\PayPalCommerce\WcGateway
 */

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\WcGateway;

use Dhii\Container\ServiceProvider;
use Dhii\Modular\Module\ModuleInterface;
use Inpsyde\PayPalCommerce\AdminNotices\Repository\Repository;
use Inpsyde\PayPalCommerce\ApiClient\Helper\DccApplies;
use Inpsyde\PayPalCommerce\ApiClient\Repository\PayPalRequestIdRepository;
use Inpsyde\PayPalCommerce\WcGateway\Admin\OrderTablePaymentStatusColumn;
use Inpsyde\PayPalCommerce\WcGateway\Admin\PaymentStatusOrderDetail;
use Inpsyde\PayPalCommerce\WcGateway\Checkout\CheckoutPayPalAddressPreset;
use Inpsyde\PayPalCommerce\WcGateway\Checkout\DisableGateways;
use Inpsyde\PayPalCommerce\WcGateway\Endpoint\ReturnUrlEndpoint;
use Inpsyde\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use Inpsyde\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use Inpsyde\PayPalCommerce\WcGateway\Notice\ConnectAdminNotice;
use Inpsyde\PayPalCommerce\WcGateway\Settings\Settings;
use Inpsyde\PayPalCommerce\WcGateway\Settings\SettingsRenderer;
use Interop\Container\ServiceProviderInterface;
use Psr\Container\ContainerInterface;

/**
 * Class WcGatewayModule
 */
class WcGatewayModule implements ModuleInterface {

	/**
	 * Setup the module.
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
	 * Runs the module.
	 *
	 * @param ContainerInterface $container The container.
	 */
	public function run( ContainerInterface $container ) {
		$this->register_payment_gateways( $container );
		$this->register_order_functionality( $container );
		$this->register_columns( $container );
		$this->register_checkout_paypal_address_preset( $container );
		$this->ajax_gateway_enabler( $container );

		add_filter(
			Repository::NOTICES_FILTER,
			static function ( $notices ) use ( $container ): array {
				$notice = $container->get( 'wcgateway.notice.connect' );
				/**
				 * The Connect Admin Notice object.
				 *
				 * @var ConnectAdminNotice $notice
				 */
				$connect_message = $notice->connect_message();
				if ( $connect_message ) {
					$notices[] = $connect_message;
				}
				$authorize_order_action = $container->get( 'wcgateway.notice.authorize-order-action' );
				$authorized_message     = $authorize_order_action->message();
				if ( $authorized_message ) {
					$notices[] = $authorized_message;
				}

				return $notices;
			}
		);
		add_action(
			'woocommerce-paypal-commerce-gateway.deactivate',
			static function () use ( $container ) {
				delete_option( Settings::KEY );
				delete_option( PayPalRequestIdRepository::KEY );
				delete_option( 'woocommerce_' . PayPalGateway::ID . '_settings' );
				delete_option( 'woocommerce_' . CreditCardGateway::ID . '_settings' );
			}
		);

		add_action(
			'wc_ajax_' . ReturnUrlEndpoint::ENDPOINT,
			static function () use ( $container ) {
				$endpoint = $container->get( 'wcgateway.endpoint.return-url' );
				/**
				 * The Endpoint.
				 *
				 * @var ReturnUrlEndpoint $endpoint
				 */
				$endpoint->handle_request();
			}
		);
	}

	/**
	 * Adds the functionality to listen to the ajax enable gateway switch.
	 *
	 * @param ContainerInterface $container The container.
	 */
	private function ajax_gateway_enabler( ContainerInterface $container ) {
		add_action(
			'wp_ajax_woocommerce_toggle_gateway_enabled',
			static function () use ( $container ) {
				if (
					! current_user_can( 'manage_woocommerce' )
					|| ! check_ajax_referer(
						'woocommerce-toggle-payment-gateway-enabled',
						'security'
					)
					|| ! isset( $_POST['gateway_id'] )
				) {
					return;
				}

				/**
				 * The settings.
				 *
				 * @var Settings $settings
				 */
				$settings = $container->get( 'wcgateway.settings' );
				$key      = PayPalGateway::ID === $_POST['gateway_id'] ? 'enabled' : '';
				if ( CreditCardGateway::ID === $_POST['gateway_id'] ) {
					$key = 'dcc_gateway_enabled';
				}
				if ( ! $key ) {
					return;
				}
				$enabled = $settings->has( $key ) ? $settings->get( $key ) : false;
				if ( ! $enabled ) {
					return;
				}
				$settings->set( $key, false );
				$settings->persist();
			},
			9
		);
	}

	/**
	 * Registers the payment gateways.
	 *
	 * @param ContainerInterface $container The container.
	 */
	private function register_payment_gateways( ContainerInterface $container ) {

		add_filter(
			'woocommerce_payment_gateways',
			static function ( $methods ) use ( $container ): array {
				$methods[]   = $container->get( 'wcgateway.paypal-gateway' );
				$dcc_applies = $container->get( 'api.helpers.dccapplies' );
				/**
				 * The DCC Applies object.
				 *
				 * @var DccApplies $dcc_applies
				 */
				if ( $dcc_applies->for_country_currency() ) {
					$methods[] = $container->get( 'wcgateway.credit-card-gateway' );
				}
				return (array) $methods;
			}
		);

		add_action(
			'woocommerce_settings_save_checkout',
			static function () use ( $container ) {
				$listener = $container->get( 'wcgateway.settings.listener' );
				$listener->listen();
			}
		);

		add_filter(
			'woocommerce_form_field',
			static function ( $field, $key, $args, $value ) use ( $container ) {
				$renderer = $container->get( 'wcgateway.settings.render' );
				/**
				 * The Settings Renderer object.
				 *
				 * @var SettingsRenderer $renderer
				 */
				$field = $renderer->render_multiselect( $field, $key, $args, $value );
				$field = $renderer->render_password( $field, $key, $args, $value );
				$field = $renderer->render_text_input( $field, $key, $args, $value );
				$field = $renderer->render_heading( $field, $key, $args, $value );
				return $field;
			},
			10,
			4
		);

		add_filter(
			'woocommerce_available_payment_gateways',
			static function ( $methods ) use ( $container ): array {
				$disabler = $container->get( 'wcgateway.disabler' );
				/**
				 * The Gateay disabler.
				 *
				 * @var DisableGateways $disabler
				 */
				return $disabler->handler( (array) $methods );
			}
		);
	}

	/**
	 * Registers the authorize order functionality.
	 *
	 * @param ContainerInterface $container The container.
	 */
	private function register_order_functionality( ContainerInterface $container ) {
		add_filter(
			'woocommerce_order_actions',
			static function ( $order_actions ): array {
				$order_actions['ppcp_authorize_order'] = __(
					'Capture authorized PayPal payment',
					'woocommerce-paypal-commerce-gateway'
				);
				return $order_actions;
			}
		);

		add_action(
			'woocommerce_order_action_ppcp_authorize_order',
			static function ( \WC_Order $wc_order ) use ( $container ) {
				/**
				 * The PayPal Gateway.
				 *
				 * @var PayPalGateway $gateway
				 */
				$gateway = $container->get( 'wcgateway.paypal-gateway' );
				$gateway->capture_authorized_payment( $wc_order );
			}
		);
	}

	/**
	 * Registers the additional columns on the order list page.
	 *
	 * @param ContainerInterface $container The container.
	 */
	private function register_columns( ContainerInterface $container ) {
		add_action(
			'woocommerce_order_actions_start',
			static function ( $wc_order_id ) use ( $container ) {
				/**
				 * The Payment Status Order Detail.
				 *
				 * @var PaymentStatusOrderDetail $class
				 */
				$class = $container->get( 'wcgateway.admin.order-payment-status' );
				$class->render( intval( $wc_order_id ) );
			}
		);

		add_filter(
			'manage_edit-shop_order_columns',
			static function ( $columns ) use ( $container ) {
				/**
				 * The Order Table Payment Status object.
				 *
				 * @var OrderTablePaymentStatusColumn $payment_status_column
				 */
				$payment_status_column = $container->get( 'wcgateway.admin.orders-payment-status-column' );
				return $payment_status_column->register( $columns );
			}
		);

		add_action(
			'manage_shop_order_posts_custom_column',
			static function ( $column, $wc_order_id ) use ( $container ) {
				/**
				 * The column object.
				 *
				 * @var OrderTablePaymentStatusColumn $payment_status_column
				 */
				$payment_status_column = $container->get( 'wcgateway.admin.orders-payment-status-column' );
				$payment_status_column->render( $column, intval( $wc_order_id ) );
			},
			10,
			2
		);
	}

	/**
	 * Registers the PayPal Address preset to overwrite Shipping in checkout.
	 *
	 * @param ContainerInterface $container The container.
	 */
	private function register_checkout_paypal_address_preset( ContainerInterface $container ): void {
		add_filter(
			'woocommerce_checkout_get_value',
			static function ( ...$args ) use ( $container ) {

				/**
				 * Its important to not instantiate the service too early as it
				 * depends on SessionHandler and WooCommerce Session.
				 */

				/**
				 * The CheckoutPayPalAddressPreset object.
				 *
				 * @var CheckoutPayPalAddressPreset $service
				 */
				$service = $container->get( 'wcgateway.checkout.address-preset' );

				return $service->filter_checkout_field( ...$args );
			},
			10,
			2
		);
	}
}
