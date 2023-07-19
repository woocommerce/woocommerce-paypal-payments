<?php
/**
 * The Gateway module.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway;

use Psr\Log\LoggerInterface;
use Throwable;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\ApiClient\Helper\Cache;
use WooCommerce\PayPalCommerce\Subscription\Helper\SubscriptionHelper;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Container\ServiceProvider;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Modular\Module\ModuleInterface;
use WC_Order;
use WooCommerce\PayPalCommerce\AdminNotices\Repository\Repository;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Capture;
use WooCommerce\PayPalCommerce\ApiClient\Entity\OrderStatus;
use WooCommerce\PayPalCommerce\ApiClient\Helper\DccApplies;
use WooCommerce\PayPalCommerce\Onboarding\State;
use WooCommerce\PayPalCommerce\WcGateway\Admin\FeesRenderer;
use WooCommerce\PayPalCommerce\WcGateway\Admin\OrderTablePaymentStatusColumn;
use WooCommerce\PayPalCommerce\WcGateway\Admin\PaymentStatusOrderDetail;
use WooCommerce\PayPalCommerce\WcGateway\Admin\RenderAuthorizeAction;
use WooCommerce\PayPalCommerce\WcGateway\Assets\FraudNetAssets;
use WooCommerce\PayPalCommerce\WcGateway\Assets\SettingsPageAssets;
use WooCommerce\PayPalCommerce\WcGateway\Checkout\CheckoutPayPalAddressPreset;
use WooCommerce\PayPalCommerce\WcGateway\Checkout\DisableGateways;
use WooCommerce\PayPalCommerce\WcGateway\Endpoint\ReturnUrlEndpoint;
use WooCommerce\PayPalCommerce\WcGateway\Exception\NotFoundException;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcGateway\Helper\DCCProductStatus;
use WooCommerce\PayPalCommerce\WcGateway\Helper\PayUponInvoiceProductStatus;
use WooCommerce\PayPalCommerce\WcGateway\Helper\SettingsStatus;
use WooCommerce\PayPalCommerce\WcGateway\Notice\ConnectAdminNotice;
use WooCommerce\PayPalCommerce\WcGateway\Notice\GatewayWithoutPayPalAdminNotice;
use WooCommerce\PayPalCommerce\WcGateway\Notice\UnsupportedCurrencyAdminNotice;
use WooCommerce\PayPalCommerce\WcGateway\Processor\AuthorizedPaymentsProcessor;
use WooCommerce\PayPalCommerce\WcGateway\Settings\HeaderRenderer;
use WooCommerce\PayPalCommerce\WcGateway\Settings\SectionsRenderer;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
use WooCommerce\PayPalCommerce\WcGateway\Settings\SettingsListener;
use WooCommerce\PayPalCommerce\WcGateway\Settings\SettingsRenderer;
use WooCommerce\PayPalCommerce\Vendor\Interop\Container\ServiceProviderInterface;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

/**
 * Class WcGatewayModule
 */
class WCGatewayModule implements ModuleInterface {

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
	public function run( ContainerInterface $c ): void {
		$this->register_payment_gateways( $c );
		$this->register_order_functionality( $c );
		$this->register_columns( $c );
		$this->register_checkout_paypal_address_preset( $c );

		add_action(
			'woocommerce_sections_checkout',
			function() use ( $c ) {
				$header_renderer = $c->get( 'wcgateway.settings.header-renderer' );
				assert( $header_renderer instanceof HeaderRenderer );

				$section_renderer = $c->get( 'wcgateway.settings.sections-renderer' );
				assert( $section_renderer instanceof SectionsRenderer );

				// phpcs:ignore WordPress.Security.EscapeOutput
				echo $header_renderer->render() . $section_renderer->render();
			},
			20
		);

		add_action(
			'woocommerce_paypal_payments_order_captured',
			function ( WC_Order $wc_order, Capture $capture ) {
				$breakdown = $capture->seller_receivable_breakdown();
				if ( $breakdown ) {
					$wc_order->update_meta_data( PayPalGateway::FEES_META_KEY, $breakdown->to_array() );
					$paypal_fee = $breakdown->paypal_fee();
					if ( $paypal_fee ) {
						$wc_order->update_meta_data( 'PayPal Transaction Fee', (string) $paypal_fee->value() );
					}

					$wc_order->save_meta_data();
				}

				$fraud = $capture->fraud_processor_response();
				if ( $fraud ) {
					$fraud_responses               = $fraud->to_array();
					$avs_response_order_note_title = __( 'Address Verification Result', 'woocommerce-paypal-payments' );
					/* translators: %1$s is AVS order note title, %2$s is AVS order note result markup */
					$avs_response_order_note_format        = __( '%1$s %2$s', 'woocommerce-paypal-payments' );
					$avs_response_order_note_result_format = '<ul class="ppcp_avs_result">
                                                                <li>%1$s</li>
                                                                <ul class="ppcp_avs_result_inner">
                                                                    <li>%2$s</li>
                                                                    <li>%3$s</li>
                                                                </ul>
                                                            </ul>';
					$avs_response_order_note_result        = sprintf(
						$avs_response_order_note_result_format,
						/* translators: %s is fraud AVS code */
						sprintf( __( 'AVS: %s', 'woocommerce-paypal-payments' ), esc_html( $fraud_responses['avs_code'] ) ),
						/* translators: %s is fraud AVS address match */
						sprintf( __( 'Address Match: %s', 'woocommerce-paypal-payments' ), esc_html( $fraud_responses['address_match'] ) ),
						/* translators: %s is fraud AVS postal match */
						sprintf( __( 'Postal Match: %s', 'woocommerce-paypal-payments' ), esc_html( $fraud_responses['postal_match'] ) )
					);
					$avs_response_order_note = sprintf(
						$avs_response_order_note_format,
						esc_html( $avs_response_order_note_title ),
						wp_kses_post( $avs_response_order_note_result )
					);
					$wc_order->add_order_note( $avs_response_order_note );

					$cvv_response_order_note_format = '<ul class="ppcp_cvv_result"><li>%1$s</li></ul>';
					$cvv_response_order_note        = sprintf(
						$cvv_response_order_note_format,
						/* translators: %s is fraud CVV match */
						sprintf( __( 'CVV2 Match: %s', 'woocommerce-paypal-payments' ), esc_html( $fraud_responses['cvv_match'] ) )
					);
					$wc_order->add_order_note( $cvv_response_order_note );
				}
			},
			10,
			2
		);

		$fees_renderer = $c->get( 'wcgateway.admin.fees-renderer' );
		assert( $fees_renderer instanceof FeesRenderer );

		add_action(
			'woocommerce_admin_order_totals_after_total',
			function ( int $order_id ) use ( $fees_renderer ) {
				$wc_order = wc_get_order( $order_id );
				if ( ! $wc_order instanceof WC_Order ) {
					return;
				}
				/**
				 * The filter can be used to remove the rows with PayPal fees in WC orders.
				 */
				if ( ! apply_filters( 'woocommerce_paypal_payments_show_fees_on_order_admin_page', true, $wc_order ) ) {
					return;
				}

				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $fees_renderer->render( $wc_order );
			}
		);

		if ( $c->has( 'wcgateway.url' ) ) {
			$settings_status = $c->get( 'wcgateway.settings.status' );
			assert( $settings_status instanceof SettingsStatus );

			$settings = $c->get( 'wcgateway.settings' );
			assert( $settings instanceof Settings );

			$assets = new SettingsPageAssets(
				$c->get( 'wcgateway.url' ),
				$c->get( 'ppcp.asset-version' ),
				$c->get( 'subscription.helper' ),
				$c->get( 'button.client_id_for_admin' ),
				$c->get( 'api.shop.currency' ),
				$c->get( 'api.shop.country' ),
				$c->get( 'onboarding.environment' ),
				$settings_status->is_pay_later_button_enabled(),
				$settings->has( 'disable_funding' ) ? $settings->get( 'disable_funding' ) : array(),
				$c->get( 'wcgateway.settings.funding-sources' )
			);
			$assets->register_assets();
		}

		add_filter(
			Repository::NOTICES_FILTER,
			static function ( $notices ) use ( $c ): array {
				$notice = $c->get( 'wcgateway.notice.connect' );
				assert( $notice instanceof ConnectAdminNotice );
				$connect_message = $notice->connect_message();
				if ( $connect_message ) {
					$notices[] = $connect_message;
				}

				$notice = $c->get( 'wcgateway.notice.currency-unsupported' );
				assert( $notice instanceof UnsupportedCurrencyAdminNotice );
				$unsupported_currency_message = $notice->unsupported_currency_message();
				if ( $unsupported_currency_message ) {
					$notices[] = $unsupported_currency_message;
				}

				foreach ( array(
					$c->get( 'wcgateway.notice.dcc-without-paypal' ),
					$c->get( 'wcgateway.notice.card-button-without-paypal' ),
				) as $gateway_without_paypal_notice ) {
					assert( $gateway_without_paypal_notice instanceof GatewayWithoutPayPalAdminNotice );
					$message = $gateway_without_paypal_notice->message();
					if ( $message ) {
						$notices[] = $message;
					}
				}

				$authorize_order_action = $c->get( 'wcgateway.notice.authorize-order-action' );
				$authorized_message     = $authorize_order_action->message();
				if ( $authorized_message ) {
					$notices[] = $authorized_message;
				}

				$settings_renderer = $c->get( 'wcgateway.settings.render' );
				assert( $settings_renderer instanceof SettingsRenderer );
				$messages = $settings_renderer->messages();
				$notices  = array_merge( $notices, $messages );

				return $notices;
			}
		);
		add_action(
			'woocommerce_paypal_commerce_gateway_deactivate',
			static function () use ( $c ) {
				delete_option( Settings::KEY );
				delete_option( 'woocommerce_' . PayPalGateway::ID . '_settings' );
				delete_option( 'woocommerce_' . CreditCardGateway::ID . '_settings' );
			}
		);

		add_action(
			'wc_ajax_' . ReturnUrlEndpoint::ENDPOINT,
			static function () use ( $c ) {
				$endpoint = $c->get( 'wcgateway.endpoint.return-url' );
				/**
				 * The Endpoint.
				 *
				 * @var ReturnUrlEndpoint $endpoint
				 */
				$endpoint->handle_request();
			}
		);

		add_action(
			'woocommerce_paypal_payments_gateway_migrate',
			static function () use ( $c ) {
				delete_option( 'ppcp-request-ids' );

				$settings = $c->get( 'wcgateway.settings' );
				assert( $settings instanceof Settings );

				try {
					if ( $settings->has( '3d_secure_contingency' ) && $settings->get( '3d_secure_contingency' ) === '3D_SECURE' ) {
						$settings->set( '3d_secure_contingency', 'SCA_ALWAYS' );
						$settings->persist();
					}
				} catch ( NotFoundException $exception ) {
					return;
				}
			}
		);

		add_action(
			'woocommerce_paypal_payments_gateway_migrate_on_update',
			static function() use ( $c ) {
				$dcc_status_cache = $c->get( 'dcc.status-cache' );
				assert( $dcc_status_cache instanceof Cache );
				$pui_status_cache = $c->get( 'pui.status-cache' );
				assert( $pui_status_cache instanceof Cache );

				$dcc_status_cache->delete( DCCProductStatus::DCC_STATUS_CACHE_KEY );
				$pui_status_cache->delete( PayUponInvoiceProductStatus::PUI_STATUS_CACHE_KEY );

				$settings = $c->get( 'wcgateway.settings' );
				$settings->set( 'products_dcc_enabled', false );
				$settings->set( 'products_pui_enabled', false );
				$settings->persist();

				// Update caches.
				$dcc_status = $c->get( 'wcgateway.helper.dcc-product-status' );
				assert( $dcc_status instanceof DCCProductStatus );
				$dcc_status->dcc_is_active();

				$pui_status = $c->get( 'wcgateway.pay-upon-invoice-product-status' );
				assert( $pui_status instanceof PayUponInvoiceProductStatus );
				$pui_status->pui_is_active();
			}
		);

		add_action(
			'wp_loaded',
			function () use ( $c ) {
				if ( 'DE' === $c->get( 'api.shop.country' ) ) {
					( $c->get( 'wcgateway.pay-upon-invoice' ) )->init();
				}

				( $c->get( 'wcgateway.oxxo' ) )->init();

				$fraudnet_assets = $c->get( 'wcgateway.fraudnet-assets' );
				assert( $fraudnet_assets instanceof FraudNetAssets );
				$fraudnet_assets->register_assets();
			}
		);

		add_action(
			'woocommerce_paypal_payments_check_pui_payment_captured',
			function ( int $wc_order_id, string $order_id ) use ( $c ) {
				$order_endpoint = $c->get( 'api.endpoint.order' );
				$logger         = $c->get( 'woocommerce.logger.woocommerce' );
				$order          = $order_endpoint->order( $order_id );
				$order_status   = $order->status();
				$logger->info( "Checking payment captured webhook for WC order #{$wc_order_id}, PayPal order status: " . $order_status->name() );

				$wc_order = wc_get_order( $wc_order_id );
				if ( ! is_a( $wc_order, WC_Order::class ) || $wc_order->get_status() !== 'on-hold' ) {
					return;
				}

				if ( $order_status->name() !== OrderStatus::COMPLETED ) {
					$message = __(
						'Could not process WC order because PAYMENT.CAPTURE.COMPLETED webhook not received.',
						'woocommerce-paypal-payments'
					);
					$logger->error( $message );
					$wc_order->update_status( 'failed', $message );
				}
			},
			10,
			2
		);

		add_action(
			'woocommerce_order_status_changed',
			static function ( int $order_id, string $from, string $to ) use ( $c ) {
				$wc_order = wc_get_order( $order_id );
				if ( ! $wc_order instanceof WC_Order ) {
					return;
				}

				$settings = $c->get( 'wcgateway.settings' );
				assert( $settings instanceof ContainerInterface );

				if ( ! $settings->has( 'capture_on_status_change' ) || ! $settings->get( 'capture_on_status_change' ) ) {
					return;
				}

				$intent   = strtoupper( (string) $wc_order->get_meta( PayPalGateway::INTENT_META_KEY ) );
				$captured = wc_string_to_bool( $wc_order->get_meta( AuthorizedPaymentsProcessor::CAPTURED_META_KEY ) );
				if ( $intent !== 'AUTHORIZE' || $captured ) {
					return;
				}

				/**
				 * The filter returning the WC order statuses which trigger capturing of payment authorization.
				 */
				$capture_statuses = apply_filters( 'woocommerce_paypal_payments_auto_capture_statuses', array( 'processing', 'completed' ), $wc_order );
				if ( ! in_array( $to, $capture_statuses, true ) ) {
					return;
				}

				$authorized_payment_processor = $c->get( 'wcgateway.processor.authorized-payments' );
				assert( $authorized_payment_processor instanceof AuthorizedPaymentsProcessor );

				try {
					if ( $authorized_payment_processor->capture_authorized_payment( $wc_order ) ) {
						return;
					}
				} catch ( Throwable $error ) {
					$logger = $c->get( 'woocommerce.logger.woocommerce' );
					assert( $logger instanceof LoggerInterface );
					$logger->error( "Capture failed. {$error->getMessage()} {$error->getFile()}:{$error->getLine()}" );
				}

				$wc_order->update_status(
					'failed',
					__( 'Could not capture the payment.', 'woocommerce-paypal-payments' )
				);
			},
			10,
			3
		);

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command(
				'pcp settings',
				$c->get( 'wcgateway.cli.settings.command' )
			);
		}
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
				$paypal_gateway = $container->get( 'wcgateway.paypal-gateway' );
				assert( $paypal_gateway instanceof \WC_Payment_Gateway );

				$paypal_gateway_enabled = wc_string_to_bool( $paypal_gateway->get_option( 'enabled' ) );

				$methods[] = $paypal_gateway;

				$onboarding_state = $container->get( 'onboarding.state' );
				assert( $onboarding_state instanceof State );

				$settings = $container->get( 'wcgateway.settings' );
				assert( $settings instanceof ContainerInterface );

				$is_our_page           = $container->get( 'wcgateway.is-ppcp-settings-page' );
				$is_gateways_list_page = $container->get( 'wcgateway.is-wc-gateways-list-page' );

				if ( $onboarding_state->current_state() !== State::STATE_ONBOARDED ) {
					return $methods;
				}

				$dcc_applies = $container->get( 'api.helpers.dccapplies' );
				assert( $dcc_applies instanceof DccApplies );

				$dcc_product_status = $container->get( 'wcgateway.helper.dcc-product-status' );
				assert( $dcc_product_status instanceof DCCProductStatus );

				if ( $dcc_applies->for_country_currency() &&
					// Show only if allowed in PayPal account, except when on our settings pages.
					// Performing the full DCCProductStatus check only when on the gateway list page
					// to avoid sending the API requests all the time.
					( $is_our_page ||
						( $is_gateways_list_page && $dcc_product_status->dcc_is_active() ) ||
						( $settings->has( 'products_dcc_enabled' ) && $settings->get( 'products_dcc_enabled' ) )
					)
				) {
					$methods[] = $container->get( 'wcgateway.credit-card-gateway' );
				}

				if ( $paypal_gateway_enabled && $container->get( 'wcgateway.settings.allow_card_button_gateway' ) ) {
					$methods[] = $container->get( 'wcgateway.card-button-gateway' );
				}

				$pui_product_status = $container->get( 'wcgateway.pay-upon-invoice-product-status' );
				assert( $pui_product_status instanceof PayUponInvoiceProductStatus );

				$shop_country = $container->get( 'api.shop.country' );

				if ( 'DE' === $shop_country &&
					( $is_our_page ||
						( $is_gateways_list_page && $pui_product_status->pui_is_active() ) ||
						( $settings->has( 'products_pui_enabled' ) && $settings->get( 'products_pui_enabled' ) )
					)
				) {
					$methods[] = $container->get( 'wcgateway.pay-upon-invoice-gateway' );
				}

				if ( 'MX' === $shop_country ) {
					$methods[] = $container->get( 'wcgateway.oxxo-gateway' );
				}

				return (array) $methods;
			}
		);

		add_action(
			'woocommerce_settings_save_checkout',
			static function () use ( $container ) {
				$listener = $container->get( 'wcgateway.settings.listener' );

				/**
				 * The settings listener.
				 *
				 * @var SettingsListener $listener
				 */
				$listener->listen();
			}
		);
		add_action(
			'admin_init',
			static function () use ( $container ) {
				$listener = $container->get( 'wcgateway.settings.listener' );
				assert( $listener instanceof SettingsListener );

				$listener->listen_for_merchant_id();

				try {
					$listener->listen_for_vaulting_enabled();
					$listener->listen_for_tracking_enabled();
				} catch ( RuntimeException $exception ) {
					add_action(
						'admin_notices',
						function () use ( $exception ) {
							printf(
								'<div class="notice notice-error"><p>%1$s</p><p>%2$s</p></div>',
								esc_html__( 'Authentication with PayPal failed: ', 'woocommerce-paypal-payments' ) . esc_attr( $exception->getMessage() ),
								wp_kses_post(
									__(
										'Please verify your API Credentials and try again to connect your PayPal business account. Visit the <a href="https://docs.woocommerce.com/document/woocommerce-paypal-payments/" target="_blank">plugin documentation</a> for more information about the setup.',
										'woocommerce-paypal-payments'
									)
								)
							);
						}
					);
				}
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
				$field = $renderer->render_heading( $field, $key, $args, $value );
				$field = $renderer->render_table( $field, $key, $args, $value );
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
			static function ( $order_actions ) use ( $container ): array {
				global $theorder;

				if ( ! is_a( $theorder, WC_Order::class ) ) {
					return $order_actions;
				}

				$render = $container->get( 'wcgateway.admin.render-authorize-action' );
				/**
				 * Renders the authorize action in the select field.
				 *
				 * @var RenderAuthorizeAction $render
				 */
				return $render->render( $order_actions, $theorder );
			}
		);

		add_action(
			'woocommerce_order_action_ppcp_authorize_order',
			static function ( WC_Order $wc_order ) use ( $container ) {

				/**
				 * The authorized payments processor.
				 *
				 * @var AuthorizedPaymentsProcessor $authorized_payments_processor
				 */
				$authorized_payments_processor = $container->get( 'wcgateway.processor.authorized-payments' );
				$authorized_payments_processor->capture_authorized_payment( $wc_order );
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
				$payment_status_column->render( (string) $column, intval( $wc_order_id ) );
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
	private function register_checkout_paypal_address_preset( ContainerInterface $container ) {
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


	/**
	 * Returns the key for the module.
	 *
	 * @return string|void
	 */
	public function getKey() {
	}
}
