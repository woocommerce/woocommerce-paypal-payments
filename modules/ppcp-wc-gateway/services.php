<?php
/**
 * The services of the Gateway module.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway
 */

// phpcs:disable WordPress.Security.NonceVerification.Recommended

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway;

use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PayUponInvoiceOrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\ApplicationContext;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\ApiClient\Helper\Cache;
use WooCommerce\PayPalCommerce\ApiClient\Helper\DccApplies;
use WooCommerce\PayPalCommerce\Button\Helper\MessagesDisclaimers;
use WooCommerce\PayPalCommerce\Common\Pattern\SingletonDecorator;
use WooCommerce\PayPalCommerce\Onboarding\Environment;
use WooCommerce\PayPalCommerce\Onboarding\Render\OnboardingOptionsRenderer;
use WooCommerce\PayPalCommerce\Onboarding\State;
use WooCommerce\PayPalCommerce\Subscription\Helper\SubscriptionHelper;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Admin\FeesRenderer;
use WooCommerce\PayPalCommerce\WcGateway\Admin\OrderTablePaymentStatusColumn;
use WooCommerce\PayPalCommerce\WcGateway\Admin\PaymentStatusOrderDetail;
use WooCommerce\PayPalCommerce\WcGateway\Admin\RenderAuthorizeAction;
use WooCommerce\PayPalCommerce\WcGateway\Assets\FraudNetAssets;
use WooCommerce\PayPalCommerce\WcGateway\Checkout\CheckoutPayPalAddressPreset;
use WooCommerce\PayPalCommerce\WcGateway\Checkout\DisableGateways;
use WooCommerce\PayPalCommerce\WcGateway\Cli\SettingsCommand;
use WooCommerce\PayPalCommerce\WcGateway\Endpoint\ReturnUrlEndpoint;
use WooCommerce\PayPalCommerce\WcGateway\FraudNet\FraudNet;
use WooCommerce\PayPalCommerce\WcGateway\FraudNet\FraudNetSessionId;
use WooCommerce\PayPalCommerce\WcGateway\FraudNet\FraudNetSourceWebsiteId;
use WooCommerce\PayPalCommerce\WcGateway\FundingSource\FundingSourceRenderer;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CardButtonGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\GatewayRepository;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\OXXO\OXXO;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\OXXO\OXXOGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayUponInvoice\PaymentSourceFactory;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayUponInvoice\PayUponInvoice;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayUponInvoice\PayUponInvoiceGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\TransactionUrlProvider;
use WooCommerce\PayPalCommerce\WcGateway\Helper\CheckoutHelper;
use WooCommerce\PayPalCommerce\WcGateway\Helper\DCCProductStatus;
use WooCommerce\PayPalCommerce\WcGateway\Helper\DisplayManager;
use WooCommerce\PayPalCommerce\WcGateway\Helper\PayUponInvoiceHelper;
use WooCommerce\PayPalCommerce\WcGateway\Helper\PayUponInvoiceProductStatus;
use WooCommerce\PayPalCommerce\WcGateway\Helper\RefundFeesUpdater;
use WooCommerce\PayPalCommerce\WcGateway\Helper\SettingsStatus;
use WooCommerce\PayPalCommerce\WcGateway\Notice\AuthorizeOrderActionNotice;
use WooCommerce\PayPalCommerce\WcGateway\Notice\ConnectAdminNotice;
use WooCommerce\PayPalCommerce\WcGateway\Notice\GatewayWithoutPayPalAdminNotice;
use WooCommerce\PayPalCommerce\WcGateway\Notice\UnsupportedCurrencyAdminNotice;
use WooCommerce\PayPalCommerce\WcGateway\Processor\AuthorizedPaymentsProcessor;
use WooCommerce\PayPalCommerce\WcGateway\Processor\OrderProcessor;
use WooCommerce\PayPalCommerce\WcGateway\Processor\RefundProcessor;
use WooCommerce\PayPalCommerce\WcGateway\Settings\HeaderRenderer;
use WooCommerce\PayPalCommerce\WcGateway\Settings\SectionsRenderer;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
use WooCommerce\PayPalCommerce\WcGateway\Settings\SettingsListener;
use WooCommerce\PayPalCommerce\WcGateway\Settings\SettingsRenderer;

return array(
	'wcgateway.paypal-gateway'                             => static function ( ContainerInterface $container ): PayPalGateway {
		$order_processor     = $container->get( 'wcgateway.order-processor' );
		$settings_renderer   = $container->get( 'wcgateway.settings.render' );
		$funding_source_renderer   = $container->get( 'wcgateway.funding-source.renderer' );
		$settings            = $container->get( 'wcgateway.settings' );
		$session_handler     = $container->get( 'session.handler' );
		$refund_processor    = $container->get( 'wcgateway.processor.refunds' );
		$state               = $container->get( 'onboarding.state' );
		$transaction_url_provider = $container->get( 'wcgateway.transaction-url-provider' );
		$subscription_helper = $container->get( 'subscription.helper' );
		$page_id             = $container->get( 'wcgateway.current-ppcp-settings-page-id' );
		$payment_token_repository = $container->get( 'vaulting.repository.payment-token' );
		$environment         = $container->get( 'onboarding.environment' );
		$logger              = $container->get( 'woocommerce.logger.woocommerce' );
		$api_shop_country = $container->get( 'api.shop.country' );
		return new PayPalGateway(
			$settings_renderer,
			$funding_source_renderer,
			$order_processor,
			$settings,
			$session_handler,
			$refund_processor,
			$state,
			$transaction_url_provider,
			$subscription_helper,
			$page_id,
			$environment,
			$payment_token_repository,
			$logger,
			$api_shop_country,
			$container->get( 'api.endpoint.order' )
		);
	},
	'wcgateway.credit-card-gateway'                        => static function ( ContainerInterface $container ): CreditCardGateway {
		$order_processor     = $container->get( 'wcgateway.order-processor' );
		$settings_renderer   = $container->get( 'wcgateway.settings.render' );
		$settings            = $container->get( 'wcgateway.settings' );
		$module_url          = $container->get( 'wcgateway.url' );
		$session_handler     = $container->get( 'session.handler' );
		$refund_processor    = $container->get( 'wcgateway.processor.refunds' );
		$state               = $container->get( 'onboarding.state' );
		$transaction_url_provider = $container->get( 'wcgateway.transaction-url-provider' );
		$subscription_helper = $container->get( 'subscription.helper' );
		$payments_endpoint = $container->get( 'api.endpoint.payments' );
		$logger = $container->get( 'woocommerce.logger.woocommerce' );
		$vaulted_credit_card_handler = $container->get( 'vaulting.credit-card-handler' );
		return new CreditCardGateway(
			$settings_renderer,
			$order_processor,
			$settings,
			$module_url,
			$session_handler,
			$refund_processor,
			$state,
			$transaction_url_provider,
			$subscription_helper,
			$logger,
			$payments_endpoint,
			$vaulted_credit_card_handler
		);
	},
	'wcgateway.card-button-gateway'                        => static function ( ContainerInterface $container ): CardButtonGateway {
		return new CardButtonGateway(
			$container->get( 'wcgateway.settings.render' ),
			$container->get( 'wcgateway.order-processor' ),
			$container->get( 'wcgateway.settings' ),
			$container->get( 'session.handler' ),
			$container->get( 'wcgateway.processor.refunds' ),
			$container->get( 'onboarding.state' ),
			$container->get( 'wcgateway.transaction-url-provider' ),
			$container->get( 'subscription.helper' ),
			$container->get( 'wcgateway.settings.allow_card_button_gateway.default' ),
			$container->get( 'onboarding.environment' ),
			$container->get( 'vaulting.repository.payment-token' ),
			$container->get( 'woocommerce.logger.woocommerce' )
		);
	},
	'wcgateway.disabler'                                   => static function ( ContainerInterface $container ): DisableGateways {
		$session_handler = $container->get( 'session.handler' );
		$settings       = $container->get( 'wcgateway.settings' );
		$settings_status = $container->get( 'wcgateway.settings.status' );
		$subscription_helper = $container->get( 'subscription.helper' );
		return new DisableGateways( $session_handler, $settings, $settings_status, $subscription_helper );
	},

	'wcgateway.is-wc-payments-page'                        => static function ( ContainerInterface $container ): bool {
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		$tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : '';
		return 'wc-settings' === $page && 'checkout' === $tab;
	},
	'wcgateway.is-wc-gateways-list-page'                   => static function ( ContainerInterface $container ): bool {
		return $container->get( 'wcgateway.is-wc-payments-page' ) && ! isset( $_GET['section'] );
	},

	'wcgateway.is-ppcp-settings-page'                      => static function ( ContainerInterface $container ): bool {
		if ( ! $container->get( 'wcgateway.is-wc-payments-page' ) ) {
			return false;
		}

		$section = isset( $_GET['section'] ) ? sanitize_text_field( wp_unslash( $_GET['section'] ) ) : '';
		return in_array(
			$section,
			array(
				Settings::CONNECTION_TAB_ID,
				PayPalGateway::ID,
				CreditCardGateway::ID,
				PayUponInvoiceGateway::ID,
				CardButtonGateway::ID,
				OXXOGateway::ID,
				Settings::PAY_LATER_TAB_ID,
			),
			true
		);
	},

	'wcgateway.current-ppcp-settings-page-id'              => static function ( ContainerInterface $container ): string {
		if ( ! $container->get( 'wcgateway.is-ppcp-settings-page' ) ) {
			return '';
		}

		$section = isset( $_GET['section'] ) ? sanitize_text_field( wp_unslash( $_GET['section'] ) ) : '';
		$ppcp_tab = isset( $_GET[ SectionsRenderer::KEY ] ) ? sanitize_text_field( wp_unslash( $_GET[ SectionsRenderer::KEY ] ) ) : '';

		$state = $container->get( 'onboarding.state' );
		assert( $state instanceof State );

		if ( ! $ppcp_tab && PayPalGateway::ID === $section && $state->current_state() !== State::STATE_ONBOARDED ) {
			return Settings::CONNECTION_TAB_ID;
		}

		return $ppcp_tab ? $ppcp_tab : $section;
	},

	'wcgateway.settings'                                   => SingletonDecorator::make(
		static function ( ContainerInterface $container ): Settings {
			return new Settings(
				$container->get( 'wcgateway.button.default-locations' ),
				$container->get( 'wcgateway.settings.dcc-gateway-title.default' )
			);
		}
	),
	'wcgateway.notice.connect'                             => static function ( ContainerInterface $container ): ConnectAdminNotice {
		$state    = $container->get( 'onboarding.state' );
		$settings = $container->get( 'wcgateway.settings' );
		return new ConnectAdminNotice( $state, $settings );
	},
	'wcgateway.notice.currency-unsupported'                => static function ( ContainerInterface $container ): UnsupportedCurrencyAdminNotice {
		$state                    = $container->get( 'onboarding.state' );
		$shop_currency            = $container->get( 'api.shop.currency' );
		$supported_currencies     = $container->get( 'api.supported-currencies' );
		$is_wc_gateways_list_page = $container->get( 'wcgateway.is-wc-gateways-list-page' );
		$is_ppcp_settings_page    = $container->get( 'wcgateway.is-ppcp-settings-page' );
		return new UnsupportedCurrencyAdminNotice(
			$state,
			$shop_currency,
			$supported_currencies,
			$is_wc_gateways_list_page,
			$is_ppcp_settings_page
		);
	},
	'wcgateway.notice.dcc-without-paypal'                  => static function ( ContainerInterface $container ): GatewayWithoutPayPalAdminNotice {
		return new GatewayWithoutPayPalAdminNotice(
			CreditCardGateway::ID,
			$container->get( 'onboarding.state' ),
			$container->get( 'wcgateway.settings' ),
			$container->get( 'wcgateway.is-wc-payments-page' ),
			$container->get( 'wcgateway.is-ppcp-settings-page' )
		);
	},
	'wcgateway.notice.card-button-without-paypal'          => static function ( ContainerInterface $container ): GatewayWithoutPayPalAdminNotice {
		return new GatewayWithoutPayPalAdminNotice(
			CardButtonGateway::ID,
			$container->get( 'onboarding.state' ),
			$container->get( 'wcgateway.settings' ),
			$container->get( 'wcgateway.is-wc-payments-page' ),
			$container->get( 'wcgateway.is-ppcp-settings-page' ),
			$container->get( 'wcgateway.settings.status' )
		);
	},
	'wcgateway.notice.authorize-order-action'              =>
		static function ( ContainerInterface $container ): AuthorizeOrderActionNotice {
			return new AuthorizeOrderActionNotice();
		},
	'wcgateway.settings.sections-renderer'                 => static function ( ContainerInterface $container ): SectionsRenderer {
		return new SectionsRenderer(
			$container->get( 'wcgateway.current-ppcp-settings-page-id' ),
			$container->get( 'onboarding.state' ),
			$container->get( 'wcgateway.helper.dcc-product-status' ),
			$container->get( 'api.helpers.dccapplies' ),
			$container->get( 'button.helper.messages-apply' ),
			$container->get( 'wcgateway.pay-upon-invoice-product-status' )
		);
	},
	'wcgateway.settings.header-renderer'                   => static function ( ContainerInterface $container ): HeaderRenderer {
		return new HeaderRenderer(
			$container->get( 'wcgateway.current-ppcp-settings-page-id' ),
			$container->get( 'wcgateway.url' )
		);
	},
	'wcgateway.settings.status'                            => static function ( ContainerInterface $container ): SettingsStatus {
		$settings      = $container->get( 'wcgateway.settings' );
		return new SettingsStatus( $settings );
	},
	'wcgateway.settings.render'                            => static function ( ContainerInterface $container ): SettingsRenderer {
		$settings      = $container->get( 'wcgateway.settings' );
		$state         = $container->get( 'onboarding.state' );
		$fields        = $container->get( 'wcgateway.settings.fields' );
		$dcc_applies    = $container->get( 'api.helpers.dccapplies' );
		$messages_apply = $container->get( 'button.helper.messages-apply' );
		$dcc_product_status = $container->get( 'wcgateway.helper.dcc-product-status' );
		$settings_status = $container->get( 'wcgateway.settings.status' );
		$page_id         = $container->get( 'wcgateway.current-ppcp-settings-page-id' );
		$api_shop_country = $container->get( 'api.shop.country' );
		return new SettingsRenderer(
			$settings,
			$state,
			$fields,
			$dcc_applies,
			$messages_apply,
			$dcc_product_status,
			$settings_status,
			$page_id,
			$api_shop_country
		);
	},
	'wcgateway.settings.listener'                          => static function ( ContainerInterface $container ): SettingsListener {
		$settings         = $container->get( 'wcgateway.settings' );
		$fields           = $container->get( 'wcgateway.settings.fields' );
		$webhook_registrar = $container->get( 'webhook.registrar' );
		$state            = $container->get( 'onboarding.state' );
		$cache = new Cache( 'ppcp-paypal-bearer' );
		$bearer = $container->get( 'api.bearer' );
		$page_id = $container->get( 'wcgateway.current-ppcp-settings-page-id' );
		$signup_link_cache = $container->get( 'onboarding.signup-link-cache' );
		$signup_link_ids = $container->get( 'onboarding.signup-link-ids' );
		$pui_status_cache = $container->get( 'pui.status-cache' );
		$dcc_status_cache = $container->get( 'dcc.status-cache' );
		$logger = $container->get( 'woocommerce.logger.woocommerce' );
		return new SettingsListener(
			$settings,
			$fields,
			$webhook_registrar,
			$cache,
			$state,
			$bearer,
			$page_id,
			$signup_link_cache,
			$signup_link_ids,
			$pui_status_cache,
			$dcc_status_cache,
			$container->get( 'http.redirector' ),
			$container->get( 'api.partner_merchant_id-production' ),
			$container->get( 'api.partner_merchant_id-sandbox' ),
			$logger
		);
	},
	'wcgateway.order-processor'                            => static function ( ContainerInterface $container ): OrderProcessor {

		$session_handler              = $container->get( 'session.handler' );
		$order_endpoint               = $container->get( 'api.endpoint.order' );
		$order_factory                = $container->get( 'api.factory.order' );
		$threed_secure                = $container->get( 'button.helper.three-d-secure' );
		$authorized_payments_processor = $container->get( 'wcgateway.processor.authorized-payments' );
		$settings                      = $container->get( 'wcgateway.settings' );
		$environment                   = $container->get( 'onboarding.environment' );
		$logger                        = $container->get( 'woocommerce.logger.woocommerce' );
		$subscription_helper = $container->get( 'subscription.helper' );
		$order_helper = $container->get( 'api.order-helper' );
		return new OrderProcessor(
			$session_handler,
			$order_endpoint,
			$order_factory,
			$threed_secure,
			$authorized_payments_processor,
			$settings,
			$logger,
			$environment,
			$subscription_helper,
			$order_helper
		);
	},
	'wcgateway.processor.refunds'                          => static function ( ContainerInterface $container ): RefundProcessor {
		$order_endpoint      = $container->get( 'api.endpoint.order' );
		$payments_endpoint   = $container->get( 'api.endpoint.payments' );
		$refund_fees_updater = $container->get( 'wcgateway.helper.refund-fees-updater' );
		$logger              = $container->get( 'woocommerce.logger.woocommerce' );
		return new RefundProcessor( $order_endpoint, $payments_endpoint, $refund_fees_updater, $logger );
	},
	'wcgateway.processor.authorized-payments'              => static function ( ContainerInterface $container ): AuthorizedPaymentsProcessor {
		$order_endpoint    = $container->get( 'api.endpoint.order' );
		$payments_endpoint = $container->get( 'api.endpoint.payments' );
		$logger = $container->get( 'woocommerce.logger.woocommerce' );
		$notice              = $container->get( 'wcgateway.notice.authorize-order-action' );
		$settings            = $container->get( 'wcgateway.settings' );
		$subscription_helper = $container->get( 'subscription.helper' );
		return new AuthorizedPaymentsProcessor(
			$order_endpoint,
			$payments_endpoint,
			$logger,
			$notice,
			$settings,
			$subscription_helper
		);
	},
	'wcgateway.admin.render-authorize-action'              => static function ( ContainerInterface $container ): RenderAuthorizeAction {
		$column = $container->get( 'wcgateway.admin.orders-payment-status-column' );
		return new RenderAuthorizeAction( $column );
	},
	'wcgateway.admin.order-payment-status'                 => static function ( ContainerInterface $container ): PaymentStatusOrderDetail {
		$column = $container->get( 'wcgateway.admin.orders-payment-status-column' );
		return new PaymentStatusOrderDetail( $column );
	},
	'wcgateway.admin.orders-payment-status-column'         => static function ( ContainerInterface $container ): OrderTablePaymentStatusColumn {
		$settings = $container->get( 'wcgateway.settings' );
		return new OrderTablePaymentStatusColumn( $settings );
	},
	'wcgateway.admin.fees-renderer'                        => static function ( ContainerInterface $container ): FeesRenderer {
		return new FeesRenderer();
	},

	'wcgateway.settings.should-render-settings'            => static function ( ContainerInterface $container ): bool {

		$sections = array(
			Settings::CONNECTION_TAB_ID => __( 'Connection', 'woocommerce-paypal-payments' ),
			PayPalGateway::ID           => __( 'Standard Payments', 'woocommerce-paypal-payments' ),
			Settings::PAY_LATER_TAB_ID  => __( 'Pay Later', 'woocommerce-paypal-payments' ),
			CreditCardGateway::ID       => __( 'Advanced Card Processing', 'woocommerce-paypal-payments' ),
			CardButtonGateway::ID       => __( 'Standard Card Button', 'woocommerce-paypal-payments' ),
		);

		$current_page_id = $container->get( 'wcgateway.current-ppcp-settings-page-id' );

		return array_key_exists( $current_page_id, $sections );
	},

	'wcgateway.settings.fields.subscriptions_mode'         => static function ( ContainerInterface $container ): array {
		return array(
			'title'        => __( 'Subscriptions Mode', 'woocommerce-paypal-payments' ),
			'type'         => 'select',
			'class'        => array(),
			'input_class'  => array( 'wc-enhanced-select' ),
			'desc_tip'     => true,
			'description'  => __( 'Utilize PayPal Vaulting for flexible subscription processing with saved payment methods, create “PayPal Subscriptions” to bill customers at regular intervals, or disable PayPal for subscription-type products.', 'woocommerce-paypal-payments' ),
			'default'      => 'vaulting_api',
			'options'      => array(
				'vaulting_api'                 => __( 'PayPal Vaulting', 'woocommerce-paypal-payments' ),
				'subscriptions_api'            => __( 'PayPal Subscriptions', 'woocommerce-paypal-payments' ),
				'disable_paypal_subscriptions' => __( 'Disable PayPal for subscriptions', 'woocommerce-paypal-payments' ),
			),
			'screens'      => array(
				State::STATE_ONBOARDED,
			),
			'requirements' => array(),
			'gateway'      => 'paypal',
		);
	},

	'wcgateway.settings.fields'                            => static function ( ContainerInterface $container ): array {

		$should_render_settings = $container->get( 'wcgateway.settings.should-render-settings' );

		if ( ! $should_render_settings ) {
			return array();
		}

		$state = $container->get( 'onboarding.state' );
		assert( $state instanceof State );

		$dcc_applies = $container->get( 'api.helpers.dccapplies' );
		assert( $dcc_applies instanceof DccApplies );

		$onboarding_options_renderer = $container->get( 'onboarding.render-options' );
		assert( $onboarding_options_renderer instanceof OnboardingOptionsRenderer );

		$subscription_helper = $container->get( 'subscription.helper' );
		assert( $subscription_helper instanceof SubscriptionHelper );

		$fields              = array(
			'checkout_settings_heading'   => array(
				'heading'      => __( 'Standard Payments Settings', 'woocommerce-paypal-payments' ),
				'type'         => 'ppcp-heading',
				'screens'      => array(
					State::STATE_START,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'title'                       => array(
				'title'        => __( 'Title', 'woocommerce-paypal-payments' ),
				'type'         => 'text',
				'description'  => __(
					'This controls the title which the user sees during checkout.',
					'woocommerce-paypal-payments'
				),
				'default'      => __( 'PayPal', 'woocommerce-paypal-payments' ),
				'desc_tip'     => true,
				'screens'      => array(
					State::STATE_START,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'dcc_enabled'                 => array(
				'title'        => __( 'Enable/Disable', 'woocommerce-paypal-payments' ),
				'desc_tip'     => true,
				'description'  => __( 'Once enabled, the Credit Card option will show up in the checkout.', 'woocommerce-paypal-payments' ),
				'label'        => __( 'Enable Advanced Card Processing', 'woocommerce-paypal-payments' ),
				'type'         => 'checkbox',
				'default'      => false,
				'gateway'      => 'dcc',
				'requirements' => array(
					'dcc',
				),
				'screens'      => array(
					State::STATE_ONBOARDED,
				),
			),
			'dcc_gateway_title'           => array(
				'title'        => __( 'Title', 'woocommerce-paypal-payments' ),
				'type'         => 'text',
				'description'  => __(
					'This controls the title which the user sees during checkout.',
					'woocommerce-paypal-payments'
				),
				'default'      => $container->get( 'wcgateway.settings.dcc-gateway-title.default' ),
				'desc_tip'     => true,
				'screens'      => array(
					State::STATE_ONBOARDED,
				),
				'requirements' => array(
					'dcc',
				),
				'gateway'      => 'dcc',
			),
			'description'                 => array(
				'title'        => __( 'Description', 'woocommerce-paypal-payments' ),
				'type'         => 'text',
				'desc_tip'     => true,
				'description'  => __(
					'This controls the description which the user sees during checkout.',
					'woocommerce-paypal-payments'
				),
				'default'      => __(
					'Pay via PayPal; you can pay with your credit card if you don\'t have a PayPal account.',
					'woocommerce-paypal-payments'
				),
				'screens'      => array(
					State::STATE_START,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'intent'                      => array(
				'title'        => __( 'Intent', 'woocommerce-paypal-payments' ),
				'type'         => 'select',
				'class'        => array(),
				'input_class'  => array( 'wc-enhanced-select' ),
				'default'      => 'capture',
				'desc_tip'     => true,
				'description'  => __(
					'The intent to either capture payment immediately or authorize a payment for an order after order creation.',
					'woocommerce-paypal-payments'
				),
				'options'      => array(
					'capture'   => __( 'Capture', 'woocommerce-paypal-payments' ),
					'authorize' => __( 'Authorize', 'woocommerce-paypal-payments' ),
				),
				'screens'      => array(
					State::STATE_START,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'capture_on_status_change'    => array(
				'title'        => __( 'Capture On Status Change', 'woocommerce-paypal-payments' ),
				'type'         => 'checkbox',
				'default'      => false,
				'desc_tip'     => true,
				'description'  => __(
					'The transaction will be captured automatically when the order status changes to Processing or Completed.',
					'woocommerce-paypal-payments'
				),
				'label'        => __( 'Capture On Status Change', 'woocommerce-paypal-payments' ),
				'screens'      => array(
					State::STATE_START,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'capture_for_virtual_only'    => array(
				'title'        => __( 'Capture Virtual-Only Orders ', 'woocommerce-paypal-payments' ),
				'type'         => 'checkbox',
				'default'      => false,
				'desc_tip'     => true,
				'description'  => __(
					'If the order contains exclusively virtual items, enable this to immediately capture, rather than authorize, the transaction.',
					'woocommerce-paypal-payments'
				),
				'label'        => __( 'Capture Virtual-Only Orders', 'woocommerce-paypal-payments' ),
				'screens'      => array(
					State::STATE_START,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'payee_preferred'             => array(
				'title'        => __( 'Instant Payments ', 'woocommerce-paypal-payments' ),
				'type'         => 'checkbox',
				'default'      => false,
				'desc_tip'     => true,
				'description'  => __(
					'If you enable this setting, PayPal will be instructed not to allow the buyer to use funding sources that take additional time to complete (for example, eChecks). Instead, the buyer will be required to use an instant funding source, such as an instant transfer, a credit/debit card, or Pay Later.',
					'woocommerce-paypal-payments'
				),
				'label'        => __( 'Require Instant Payment', 'woocommerce-paypal-payments' ),
				'screens'      => array(
					State::STATE_START,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'brand_name'                  => array(
				'title'        => __( 'Brand Name', 'woocommerce-paypal-payments' ),
				'type'         => 'text',
				'default'      => get_bloginfo( 'name' ),
				'desc_tip'     => true,
				'description'  => __(
					'Control the name of your shop, customers will see in the PayPal process.',
					'woocommerce-paypal-payments'
				),
				'screens'      => array(
					State::STATE_START,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'landing_page'                => array(
				'title'        => __( 'Landing Page', 'woocommerce-paypal-payments' ),
				'type'         => 'select',
				'class'        => array(),
				'input_class'  => array( 'wc-enhanced-select' ),
				'default'      => ApplicationContext::LANDING_PAGE_LOGIN,
				'desc_tip'     => true,
				'description'  => __(
					'Type of PayPal page to display.',
					'woocommerce-paypal-payments'
				),
				'options'      => array(
					ApplicationContext::LANDING_PAGE_LOGIN => __( 'Login (PayPal account login)', 'woocommerce-paypal-payments' ),
					ApplicationContext::LANDING_PAGE_BILLING => __( 'Billing (Non-PayPal account)', 'woocommerce-paypal-payments' ),
				),
				'screens'      => array(
					State::STATE_START,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'alternative_payment_methods' => array(
				'heading'      => __( 'Alternative Payment Methods', 'woocommerce-paypal-payments' ),
				'description'  => sprintf(
				// translators: %1$s, %2$s, %3$s and %4$s are a link tags.
					__( '%1$sAlternative Payment Methods%2$s allow you to accept payments from customers around the globe who use their credit cards, bank accounts, wallets, and local payment methods. When a buyer pays in a currency different than yours, PayPal handles currency conversion for you and presents conversion information to the buyer during checkout.', 'woocommerce-paypal-payments' ),
					'<a href="https://woocommerce.com/document/woocommerce-paypal-payments/#alternative-payment-methods" target="_blank">',
					'</a>'
				),
				'type'         => 'ppcp-heading',
				'screens'      => array(
					State::STATE_START,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'disable_funding'             => array(
				'title'        => __( 'Disable Alternative Payment Methods', 'woocommerce-paypal-payments' ),
				'type'         => 'ppcp-multiselect',
				'class'        => array(),
				'input_class'  => array( 'wc-enhanced-select' ),
				'default'      => array(),
				'desc_tip'     => false,
				'description'  => sprintf(
				// translators: %1$s and %2$s are the opening and closing of HTML <a> tag.
					__( 'Choose to hide specific %1$sAlternative Payment Methods%2$s such as Credit Cards, Venmo, or others.', 'woocommerce-paypal-payments' ),
					'<a
						href="https://developer.paypal.com/docs/checkout/apm/"
						target="_blank"
					>',
					'</a>'
				),
				'options'      => $container->get( 'wcgateway.settings.funding-sources' ),
				'screens'      => array(
					State::STATE_START,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'card_billing_data_mode'      => array(
				'title'        => __( 'Send checkout billing data to card fields', 'woocommerce-paypal-payments' ),
				'type'         => 'select',
				'class'        => array(),
				'input_class'  => array( 'wc-enhanced-select' ),
				'desc_tip'     => true,
				'description'  => __( 'Using the WC form data increases convenience for the customers, but can cause issues if card details do not match the billing data in the checkout form.', 'woocommerce-paypal-payments' ),
				'default'      => $container->get( 'wcgateway.settings.card_billing_data_mode.default' ),
				'options'      => array(
					CardBillingMode::USE_WC        => __( 'Use WC checkout form data (do not show any address fields)', 'woocommerce-paypal-payments' ),
					CardBillingMode::MINIMAL_INPUT => __( 'Request only name and postal code', 'woocommerce-paypal-payments' ),
					CardBillingMode::NO_WC         => __( 'Do not use WC checkout form data (request all address fields)', 'woocommerce-paypal-payments' ),
				),
				'screens'      => array(
					State::STATE_START,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => array( 'paypal', CardButtonGateway::ID ),
			),
			'allow_card_button_gateway'   => array(
				'title'        => __( 'Create gateway for Standard Card Button', 'woocommerce-paypal-payments' ),
				'type'         => 'checkbox',
				'desc_tip'     => true,
				'label'        => __( 'Moves the Standard Card Button from the PayPal gateway into its own dedicated gateway.', 'woocommerce-paypal-payments' ),
				'description'  => __( 'By default, the Debit or Credit Card button is displayed in the Standard Payments payment gateway. This setting creates a second gateway for the Card button.', 'woocommerce-paypal-payments' ),
				'default'      => $container->get( 'wcgateway.settings.allow_card_button_gateway.default' ),
				'screens'      => array(
					State::STATE_START,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'disable_cards'               => array(
				'title'        => __( 'Disable specific credit cards', 'woocommerce-paypal-payments' ),
				'type'         => 'ppcp-multiselect',
				'class'        => array(),
				'input_class'  => array( 'wc-enhanced-select' ),
				'default'      => array(),
				'desc_tip'     => true,
				'description'  => __(
					'By default all possible credit cards will be accepted. You can disable some cards, if you wish.',
					'woocommerce-paypal-payments'
				),
				'options'      => array(
					'visa'       => _x( 'Visa', 'Name of credit card', 'woocommerce-paypal-payments' ),
					'mastercard' => _x( 'Mastercard', 'Name of credit card', 'woocommerce-paypal-payments' ),
					'amex'       => _x( 'American Express', 'Name of credit card', 'woocommerce-paypal-payments' ),
					'discover'   => _x( 'Discover', 'Name of credit card', 'woocommerce-paypal-payments' ),
					'jcb'        => _x( 'JCB', 'Name of credit card', 'woocommerce-paypal-payments' ),
					'elo'        => _x( 'Elo', 'Name of credit card', 'woocommerce-paypal-payments' ),
					'hiper'      => _x( 'Hiper', 'Name of credit card', 'woocommerce-paypal-payments' ),
				),
				'screens'      => array(
					State::STATE_ONBOARDED,
				),
				'requirements' => array(
					'dcc',
				),
				'gateway'      => 'dcc',
			),
			'card_icons'                  => array(
				'title'        => __( 'Show logo of the following credit cards', 'woocommerce-paypal-payments' ),
				'type'         => 'ppcp-multiselect',
				'class'        => array(),
				'input_class'  => array( 'wc-enhanced-select' ),
				'default'      => array(),
				'desc_tip'     => true,
				'description'  => __(
					'Define which cards you want to display in your checkout.',
					'woocommerce-paypal-payments'
				),
				'options'      => array(
					'visa'            => _x( 'Visa (light)', 'Name of credit card', 'woocommerce-paypal-payments' ),
					'visa-dark'       => _x( 'Visa (dark)', 'Name of credit card', 'woocommerce-paypal-payments' ),
					'mastercard'      => _x( 'Mastercard (light)', 'Name of credit card', 'woocommerce-paypal-payments' ),
					'mastercard-dark' => _x( 'Mastercard (dark)', 'Name of credit card', 'woocommerce-paypal-payments' ),
					'amex'            => _x( 'American Express', 'Name of credit card', 'woocommerce-paypal-payments' ),
					'discover'        => _x( 'Discover', 'Name of credit card', 'woocommerce-paypal-payments' ),
					'jcb'             => _x( 'JCB', 'Name of credit card', 'woocommerce-paypal-payments' ),
					'elo'             => _x( 'Elo', 'Name of credit card', 'woocommerce-paypal-payments' ),
					'hiper'           => _x( 'Hiper', 'Name of credit card', 'woocommerce-paypal-payments' ),
				),
				'screens'      => array(
					State::STATE_ONBOARDED,
				),
				'requirements' => array(
					'dcc',
				),
				'gateway'      => 'dcc',
			),
			'vault_enabled_dcc'           => array(
				'title'        => __( 'Vaulting', 'woocommerce-paypal-payments' ),
				'type'         => 'checkbox',
				'desc_tip'     => true,
				'label'        => sprintf(
				// translators: %1$s and %2$s are the opening and closing of HTML <a> tag.
					__( 'Securely store your customers’ credit cards for a seamless checkout experience and subscription features. Payment methods are saved in the secure %1$sPayPal Vault%2$s.', 'woocommerce-paypal-payments' ),
					'<a href="https://woocommerce.com/document/woocommerce-paypal-payments/#vaulting-saving-a-payment-method" target="_blank">',
					'</a>'
				),
				'description'  => __( 'Allow registered buyers to save Credit Card payments.', 'woocommerce-paypal-payments' ),
				'default'      => false,
				'screens'      => array(
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'dcc',
				'input_class'  => $container->get( 'wcgateway.helper.vaulting-scope' ) ? array() : array( 'ppcp-disabled-checkbox' ),
			),
			'3d_secure_heading'           => array(
				'heading'      => __( '3D Secure', 'woocommerce-paypal-payments' ),
				'type'         => 'ppcp-heading',
				'description'  => wp_kses_post(
					sprintf(
					// translators: %1$s and %2$s is a link tag.
						__(
							'3D Secure benefits cardholders and merchants by providing
                                  an additional layer of verification using Verified by Visa,
                                  MasterCard SecureCode and American Express SafeKey.
                                  %1$sLearn more about 3D Secure.%2$s',
							'woocommerce-paypal-payments'
						),
						'<a
                            rel="noreferrer noopener"
                            href="https://woocommerce.com/posts/introducing-strong-customer-authentication-sca/"
                            >',
						'</a>'
					)
				),
				'screens'      => array(
					State::STATE_ONBOARDED,
				),
				'requirements' => array(
					'dcc',
				),
				'gateway'      => 'dcc',
			),
			'3d_secure_contingency'       => array(
				'title'        => __( 'Contingency for 3D Secure', 'woocommerce-paypal-payments' ),
				'type'         => 'select',
				'description'  => sprintf(
				// translators: %1$s and %2$s opening and closing ul tag, %3$s and %4$s opening and closing li tag.
					__( '%1$s%3$sNo 3D Secure will cause transactions to be denied if 3D Secure is required by the bank of the cardholder.%4$s%3$sSCA_WHEN_REQUIRED returns a 3D Secure contingency when it is a mandate in the region where you operate.%4$s%3$sSCA_ALWAYS triggers 3D Secure for every transaction, regardless of SCA requirements.%4$s%2$s', 'woocommerce-paypal-payments' ),
					'<ul>',
					'</ul>',
					'<li>',
					'</li>'
				),
				'class'        => array(),
				'input_class'  => array( 'wc-enhanced-select' ),
				'default'      => $container->get( 'api.shop.is-psd2-country' ) ? 'SCA_WHEN_REQUIRED' : 'NO_3D_SECURE',
				'desc_tip'     => true,
				'options'      => array(
					'NO_3D_SECURE'      => __( 'No 3D Secure (transaction will be denied if 3D Secure is required)', 'woocommerce-paypal-payments' ),
					'SCA_WHEN_REQUIRED' => __( '3D Secure when required', 'woocommerce-paypal-payments' ),
					'SCA_ALWAYS'        => __( 'Always trigger 3D Secure', 'woocommerce-paypal-payments' ),
				),
				'screens'      => array(
					State::STATE_ONBOARDED,
				),
				'requirements' => array(
					'dcc',
				),
				'gateway'      => 'dcc',
			),
			'paypal_saved_payments'       => array(
				'heading'      => __( 'Saved payments', 'woocommerce-paypal-payments' ),
				'description'  => sprintf(
				// translators: %1$s, %2$s, %3$s and %4$s are a link tags.
					__( 'PayPal can securely store your customers\' payment methods for %1$sfuture payments%2$s and %3$ssubscriptions%4$s, simplifying the checkout process and enabling recurring transactions on your website.', 'woocommerce-paypal-payments' ),
					'<a href="https://woocommerce.com/document/woocommerce-paypal-payments/#vaulting-saving-a-payment-method" target="_blank">',
					'</a>',
					'<a href="https://woocommerce.com/document/woocommerce-paypal-payments/#subscriptions-faq" target="_blank">',
					'</a>'
				),
				'type'         => 'ppcp-heading',
				'screens'      => array(
					State::STATE_START,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'subscriptions_mode'          => $container->get( 'wcgateway.settings.fields.subscriptions_mode' ),
			'vault_enabled'               => array(
				'title'        => __( 'Vaulting', 'woocommerce-paypal-payments' ),
				'type'         => 'checkbox',
				'desc_tip'     => true,
				'label'        => sprintf(
					// translators: %1$s and %2$s are the opening and closing of HTML <a> tag.
					__( 'Securely store your customers’ PayPal accounts for a seamless checkout experience. Payment methods are saved in the secure %1$sPayPal Vault%2$s.', 'woocommerce-paypal-payments' ),
					'<a href="https://woocommerce.com/document/woocommerce-paypal-payments/#vaulting-saving-a-payment-method" target="_blank">',
					'</a>'
				) . $container->get( 'button.helper.vaulting-label' ),
				'description'  => __( 'Allow registered buyers to save PayPal payments.', 'woocommerce-paypal-payments' ),
				'default'      => false,
				'screens'      => array(
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
				'input_class'  => $container->get( 'wcgateway.helper.vaulting-scope' ) ? array() : array( 'ppcp-disabled-checkbox' ),
			),
		);

		if ( ! $subscription_helper->plugin_is_active() ) {
			unset( $fields['subscriptions_mode'] );
		}

		$billing_agreements_endpoint = $container->get( 'api.endpoint.billing-agreements' );
		if ( ! $billing_agreements_endpoint->reference_transaction_enabled() ) {
			unset( $fields['vault_enabled'] );
		}

		/**
		 * Depending on your store location, some credit cards can't be used.
		 * Here, we filter them out.
		 */
		$card_options = $fields['disable_cards']['options'];
		$card_icons = $fields['card_icons']['options'];
		$dark_versions = array();
		foreach ( $card_options as $card => $label ) {
			if ( $dcc_applies->can_process_card( $card ) ) {
				if ( 'visa' === $card || 'mastercard' === $card ) {
					$dark_versions = array(
						'visa-dark'       => $card_icons['visa-dark'],
						'mastercard-dark' => $card_icons['mastercard-dark'],
					);
				}
				continue;
			}
			unset( $card_options[ $card ] );
		}

		$fields['disable_cards']['options'] = $card_options;
		$fields['card_icons']['options'] = array_merge( $dark_versions, $card_options );

		return $fields;
	},

	'wcgateway.all-funding-sources'                        => static function( ContainerInterface $container ): array {
		return array(
			'card'        => _x( 'Credit or debit cards', 'Name of payment method', 'woocommerce-paypal-payments' ),
			'sepa'        => _x( 'SEPA-Lastschrift', 'Name of payment method', 'woocommerce-paypal-payments' ),
			'bancontact'  => _x( 'Bancontact', 'Name of payment method', 'woocommerce-paypal-payments' ),
			'blik'        => _x( 'BLIK', 'Name of payment method', 'woocommerce-paypal-payments' ),
			'eps'         => _x( 'eps', 'Name of payment method', 'woocommerce-paypal-payments' ),
			'giropay'     => _x( 'giropay', 'Name of payment method', 'woocommerce-paypal-payments' ),
			'ideal'       => _x( 'iDEAL', 'Name of payment method', 'woocommerce-paypal-payments' ),
			'mercadopago' => _x( 'Mercado Pago', 'Name of payment method', 'woocommerce-paypal-payments' ),
			'mybank'      => _x( 'MyBank', 'Name of payment method', 'woocommerce-paypal-payments' ),
			'p24'         => _x( 'Przelewy24', 'Name of payment method', 'woocommerce-paypal-payments' ),
			'sofort'      => _x( 'Sofort', 'Name of payment method', 'woocommerce-paypal-payments' ),
			'venmo'       => _x( 'Venmo', 'Name of payment method', 'woocommerce-paypal-payments' ),
			'trustly'     => _x( 'Trustly', 'Name of payment method', 'woocommerce-paypal-payments' ),
			'paylater'    => _x( 'Pay Later', 'Name of payment method', 'woocommerce-paypal-payments' ),
		);
	},

	'wcgateway.extra-funding-sources'                      => static function( ContainerInterface $container ): array {
		return array(
			'googlepay' => _x( 'Google Pay', 'Name of payment method', 'woocommerce-paypal-payments' ),
			'applepay'  => _x( 'Apple Pay', 'Name of payment method', 'woocommerce-paypal-payments' ),
		);
	},

	/**
	 * The sources that do not cause issues about redirecting (on mobile, ...) and sometimes not returning back.
	 */
	'wcgateway.funding-sources-without-redirect'           => static function( ContainerInterface $container ): array {
		return array( 'paypal', 'paylater', 'venmo', 'card' );
	},
	'wcgateway.settings.funding-sources'                   => static function( ContainerInterface $container ): array {
		return array_diff_key(
			$container->get( 'wcgateway.all-funding-sources' ),
			array_flip(
				array(
					'paylater',
				)
			)
		);
	},

	'wcgateway.checkout.address-preset'                    => static function( ContainerInterface $container ): CheckoutPayPalAddressPreset {

		return new CheckoutPayPalAddressPreset(
			$container->get( 'session.handler' )
		);
	},
	'wcgateway.url'                                        => static function ( ContainerInterface $container ): string {
		return plugins_url(
			$container->get( 'wcgateway.relative-path' ),
			dirname( realpath( __FILE__ ), 3 ) . '/woocommerce-paypal-payments.php'
		);
	},
	'wcgateway.relative-path'                              => static function( ContainerInterface $container ): string {
		return 'modules/ppcp-wc-gateway/';
	},
	'wcgateway.absolute-path'                              => static function( ContainerInterface $container ): string {
		return plugin_dir_path(
			dirname( realpath( __FILE__ ), 3 ) . '/woocommerce-paypal-payments.php'
		) .
			$container->get( 'wcgateway.relative-path' );
	},
	'wcgateway.endpoint.return-url'                        => static function ( ContainerInterface $container ) : ReturnUrlEndpoint {
		$gateway  = $container->get( 'wcgateway.paypal-gateway' );
		$endpoint = $container->get( 'api.endpoint.order' );
		return new ReturnUrlEndpoint(
			$gateway,
			$endpoint,
			$container->get( 'session.handler' ),
			$container->get( 'woocommerce.logger.woocommerce' )
		);
	},

	'wcgateway.transaction-url-sandbox'                    => static function ( ContainerInterface $container ): string {
		return 'https://www.sandbox.paypal.com/cgi-bin/webscr?cmd=_view-a-trans&id=%s';
	},

	'wcgateway.transaction-url-live'                       => static function ( ContainerInterface $container ): string {
		return 'https://www.paypal.com/cgi-bin/webscr?cmd=_view-a-trans&id=%s';
	},

	'wcgateway.soft-descriptor'                            => static function ( ContainerInterface $container ): string {
		$settings = $container->get( 'wcgateway.settings' );
		assert( $settings instanceof Settings );
		if ( $settings->has( 'soft_descriptor' ) ) {
			return $settings->get( 'soft_descriptor' );
		}
		return '';
	},

	'wcgateway.transaction-url-provider'                   => static function ( ContainerInterface $container ): TransactionUrlProvider {
		$sandbox_url_base = $container->get( 'wcgateway.transaction-url-sandbox' );
		$live_url_base    = $container->get( 'wcgateway.transaction-url-live' );

		return new TransactionUrlProvider( $sandbox_url_base, $live_url_base );
	},

	'wcgateway.helper.dcc-product-status'                  => static function ( ContainerInterface $container ) : DCCProductStatus {

		$settings         = $container->get( 'wcgateway.settings' );
		$partner_endpoint = $container->get( 'api.endpoint.partners' );
		return new DCCProductStatus(
			$settings,
			$partner_endpoint,
			$container->get( 'dcc.status-cache' ),
			$container->get( 'api.helpers.dccapplies' ),
			$container->get( 'onboarding.state' ),
			$container->get( 'api.helper.failure-registry' )
		);
	},

	'wcgateway.helper.refund-fees-updater'                 => static function ( ContainerInterface $container ): RefundFeesUpdater {
		$order_endpoint    = $container->get( 'api.endpoint.order' );
		$logger            = $container->get( 'woocommerce.logger.woocommerce' );
		return new RefundFeesUpdater( $order_endpoint, $logger );
	},

	'button.helper.messages-disclaimers'                   => static function ( ContainerInterface $container ): MessagesDisclaimers {
		return new MessagesDisclaimers(
			$container->get( 'api.shop.country' )
		);
	},

	'wcgateway.funding-source.renderer'                    => function ( ContainerInterface $container ) : FundingSourceRenderer {
		return new FundingSourceRenderer(
			$container->get( 'wcgateway.settings' ),
			array_merge(
				$container->get( 'wcgateway.all-funding-sources' ),
				$container->get( 'wcgateway.extra-funding-sources' )
			)
		);
	},

	'wcgateway.checkout-helper'                            => static function ( ContainerInterface $container ): CheckoutHelper {
		return new CheckoutHelper();
	},
	'wcgateway.pay-upon-invoice-order-endpoint'            => static function ( ContainerInterface $container ): PayUponInvoiceOrderEndpoint {
		return new PayUponInvoiceOrderEndpoint(
			$container->get( 'api.host' ),
			$container->get( 'api.bearer' ),
			$container->get( 'api.factory.order' ),
			$container->get( 'wcgateway.fraudnet' ),
			$container->get( 'woocommerce.logger.woocommerce' )
		);
	},
	'wcgateway.pay-upon-invoice-payment-source-factory'    => static function ( ContainerInterface $container ): PaymentSourceFactory {
		return new PaymentSourceFactory();
	},
	'wcgateway.pay-upon-invoice-gateway'                   => static function ( ContainerInterface $container ): PayUponInvoiceGateway {
		return new PayUponInvoiceGateway(
			$container->get( 'wcgateway.pay-upon-invoice-order-endpoint' ),
			$container->get( 'api.factory.purchase-unit' ),
			$container->get( 'wcgateway.pay-upon-invoice-payment-source-factory' ),
			$container->get( 'onboarding.environment' ),
			$container->get( 'wcgateway.transaction-url-provider' ),
			$container->get( 'woocommerce.logger.woocommerce' ),
			$container->get( 'wcgateway.pay-upon-invoice-helper' ),
			$container->get( 'wcgateway.checkout-helper' ),
			$container->get( 'onboarding.state' ),
			$container->get( 'wcgateway.processor.refunds' )
		);
	},
	'wcgateway.fraudnet-session-id'                        => static function ( ContainerInterface $container ): FraudNetSessionId {
		return new FraudNetSessionId();
	},
	'wcgateway.fraudnet-source-website-id'                 => static function ( ContainerInterface $container ): FraudNetSourceWebsiteId {
		return new FraudNetSourceWebsiteId( $container->get( 'api.merchant_id' ) );
	},
	'wcgateway.fraudnet'                                   => static function ( ContainerInterface $container ): FraudNet {
		$session_id = $container->get( 'wcgateway.fraudnet-session-id' );
		$source_website_id = $container->get( 'wcgateway.fraudnet-source-website-id' );
		return new FraudNet(
			(string) $session_id(),
			(string) $source_website_id()
		);
	},
	'wcgateway.pay-upon-invoice-helper'                    => static function( ContainerInterface $container ): PayUponInvoiceHelper {
		return new PayUponInvoiceHelper(
			$container->get( 'wcgateway.checkout-helper' ),
			$container->get( 'api.shop.country' )
		);
	},
	'wcgateway.pay-upon-invoice-product-status'            => static function( ContainerInterface $container ): PayUponInvoiceProductStatus {
		return new PayUponInvoiceProductStatus(
			$container->get( 'wcgateway.settings' ),
			$container->get( 'api.endpoint.partners' ),
			$container->get( 'pui.status-cache' ),
			$container->get( 'onboarding.state' ),
			$container->get( 'api.helper.failure-registry' )
		);
	},
	'wcgateway.pay-upon-invoice'                           => static function ( ContainerInterface $container ): PayUponInvoice {
		return new PayUponInvoice(
			$container->get( 'wcgateway.pay-upon-invoice-order-endpoint' ),
			$container->get( 'woocommerce.logger.woocommerce' ),
			$container->get( 'wcgateway.settings' ),
			$container->get( 'onboarding.state' ),
			$container->get( 'wcgateway.current-ppcp-settings-page-id' ),
			$container->get( 'wcgateway.pay-upon-invoice-product-status' ),
			$container->get( 'wcgateway.pay-upon-invoice-helper' ),
			$container->get( 'wcgateway.checkout-helper' ),
			$container->get( 'api.factory.capture' )
		);
	},
	'wcgateway.oxxo'                                       => static function( ContainerInterface $container ): OXXO {
		return new OXXO(
			$container->get( 'wcgateway.checkout-helper' ),
			$container->get( 'wcgateway.url' ),
			$container->get( 'ppcp.asset-version' )
		);
	},
	'wcgateway.oxxo-gateway'                               => static function( ContainerInterface $container ): OXXOGateway {
		return new OXXOGateway(
			$container->get( 'api.endpoint.order' ),
			$container->get( 'api.factory.purchase-unit' ),
			$container->get( 'api.factory.shipping-preference' ),
			$container->get( 'wcgateway.url' ),
			$container->get( 'wcgateway.transaction-url-provider' ),
			$container->get( 'onboarding.environment' ),
			$container->get( 'woocommerce.logger.woocommerce' )
		);
	},
	'wcgateway.logging.is-enabled'                         => function ( ContainerInterface $container ) : bool {
		$settings = $container->get( 'wcgateway.settings' );

		/**
		 * Whether the logging of the plugin errors/events is enabled.
		 */
		return apply_filters(
			'woocommerce_paypal_payments_is_logging_enabled',
			$settings->has( 'logging_enabled' ) && $settings->get( 'logging_enabled' )
		);
	},

	'wcgateway.helper.vaulting-scope'                      => static function ( ContainerInterface $container ): bool {
		try {
			$token = $container->get( 'api.bearer' )->bearer();
			return $token->vaulting_available();
		} catch ( RuntimeException $exception ) {
			return false;
		}
	},

	'button.helper.vaulting-label'                         => static function ( ContainerInterface $container ): string {
		$vaulting_label = '';
		if ( ! $container->get( 'wcgateway.helper.vaulting-scope' ) ) {
			$vaulting_label .= sprintf(
				// translators: %1$s and %2$s are the opening and closing of HTML <a> tag.
				__( ' To use vaulting features, you must %1$senable vaulting on your account%2$s.', 'woocommerce-paypal-payments' ),
				'<a
					href="https://docs.woocommerce.com/document/woocommerce-paypal-payments/#enable-vaulting-on-your-live-account"
					target="_blank"
				>',
				'</a>'
			);
		}

		$vaulting_label .= '<p class="description">';
		$vaulting_label .= sprintf(
		// translators: %1$s, %2$s, %3$s and %4$s are the opening and closing of HTML <a> tag.
			__( 'This will disable all %1$sPay Later%2$s features and %3$sAlternative Payment Methods%4$s on your site.', 'woocommerce-paypal-payments' ),
			'<a
					href="https://woocommerce.com/document/woocommerce-paypal-payments/#pay-later"
					target="_blank"
				>',
			'</a>',
			'<a
					href="https://woocommerce.com/document/woocommerce-paypal-payments/#alternative-payment-methods"
					target="_blank"
				>',
			'</a>'
		);
		$vaulting_label .= '</p>';

		return $vaulting_label;
	},

	'wcgateway.settings.dcc-gateway-title.default'         => static function ( ContainerInterface $container ): string {
		return __( 'Debit & Credit Cards', 'woocommerce-paypal-payments' );
	},

	'wcgateway.settings.card_billing_data_mode.default'    => static function ( ContainerInterface $container ): string {
		return $container->get( 'api.shop.is-latin-america' ) ? CardBillingMode::MINIMAL_INPUT : CardBillingMode::USE_WC;
	},
	'wcgateway.settings.card_billing_data_mode'            => static function ( ContainerInterface $container ): string {
		$settings = $container->get( 'wcgateway.settings' );
		assert( $settings instanceof ContainerInterface );

		return $settings->has( 'card_billing_data_mode' ) ?
			(string) $settings->get( 'card_billing_data_mode' ) :
			$container->get( 'wcgateway.settings.card_billing_data_mode.default' );
	},

	'wcgateway.settings.allow_card_button_gateway.default' => static function ( ContainerInterface $container ): bool {
		return $container->get( 'api.shop.is-latin-america' );
	},
	'wcgateway.settings.allow_card_button_gateway'         => static function ( ContainerInterface $container ): bool {
		$settings = $container->get( 'wcgateway.settings' );
		assert( $settings instanceof ContainerInterface );

		return $settings->has( 'allow_card_button_gateway' ) ?
			(bool) $settings->get( 'allow_card_button_gateway' ) :
			$container->get( 'wcgateway.settings.allow_card_button_gateway.default' );
	},
	'wcgateway.settings.has_enabled_separate_button_gateways' => static function ( ContainerInterface $container ): bool {
		return (bool) $container->get( 'wcgateway.settings.allow_card_button_gateway' );
	},
	'wcgateway.settings.should-disable-fraudnet-checkbox'  => static function( ContainerInterface $container ): bool {
		$pui_helper = $container->get( 'wcgateway.pay-upon-invoice-helper' );
		assert( $pui_helper instanceof PayUponInvoiceHelper );

		if ( $pui_helper->is_pui_gateway_enabled() ) {
			return true;
		}

		return false;
	},
	'wcgateway.settings.fraudnet-label'                    => static function ( ContainerInterface $container ): string {
		$label = sprintf(
			// translators: %1$s and %2$s are the opening and closing of HTML <a> tag.
			__( 'Manage online risk with %1$sFraudNet%2$s.', 'woocommerce-paypal-payments' ),
			'<a href="https://woocommerce.com/document/woocommerce-paypal-payments/#fraudnet" target="_blank">',
			'</a>'
		);

		if ( 'DE' === $container->get( 'api.shop.country' ) ) {
			$label .= '<br/>' . sprintf(
				// translators: %1$s and %2$s are the opening and closing of HTML <a> tag.
				__( 'Required when %1$sPay upon Invoice%2$s is used.', 'woocommerce-paypal-payments' ),
				'<a href="https://woocommerce.com/document/woocommerce-paypal-payments/#pay-upon-invoice-PUI" target="_blank">',
				'</a>'
			);
		}

		return $label;
	},
	'wcgateway.enable-dcc-url-sandbox'                     => static function ( ContainerInterface $container ): string {
		return 'https://www.sandbox.paypal.com/bizsignup/entry/product/ppcp';
	},
	'wcgateway.enable-dcc-url-live'                        => static function ( ContainerInterface $container ): string {
		return 'https://www.paypal.com/bizsignup/entry/product/ppcp';
	},
	'wcgateway.enable-pui-url-sandbox'                     => static function ( ContainerInterface $container ): string {
		return 'https://www.sandbox.paypal.com/bizsignup/entry?country.x=DE&product=payment_methods&capabilities=PAY_UPON_INVOICE';
	},
	'wcgateway.enable-pui-url-live'                        => static function ( ContainerInterface $container ): string {
		return 'https://www.paypal.com/bizsignup/entry?country.x=DE&product=payment_methods&capabilities=PAY_UPON_INVOICE';
	},
	'wcgateway.settings.connection.dcc-status-text'        => static function ( ContainerInterface $container ): string {
		$state = $container->get( 'onboarding.state' );
		if ( $state->current_state() < State::STATE_ONBOARDED ) {
			return '';
		}

		$dcc_product_status = $container->get( 'wcgateway.helper.dcc-product-status' );
		assert( $dcc_product_status instanceof DCCProductStatus );

		$environment = $container->get( 'onboarding.environment' );
		assert( $environment instanceof Environment );

		$dcc_enabled = $dcc_product_status->dcc_is_active();

		$enabled_status_text  = esc_html__( 'Status: Available', 'woocommerce-paypal-payments' );
		$disabled_status_text = esc_html__( 'Status: Not yet enabled', 'woocommerce-paypal-payments' );

		$dcc_button_text = $dcc_enabled
			? esc_html__( 'Settings', 'woocommerce-paypal-payments' )
			: esc_html__( 'Enable Advanced Card Payments', 'woocommerce-paypal-payments' );

		$enable_dcc_url = $environment->current_environment_is( Environment::PRODUCTION )
			? $container->get( 'wcgateway.enable-dcc-url-live' )
			: $container->get( 'wcgateway.enable-dcc-url-sandbox' );

		$dcc_button_url = $dcc_enabled
			? admin_url( 'admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway&ppcp-tab=ppcp-credit-card-gateway' )
			: $enable_dcc_url;

		return sprintf(
			'<p>%1$s %2$s</p><p><a target="%3$s" href="%4$s" class="button">%5$s</a></p>',
			$dcc_enabled ? $enabled_status_text : $disabled_status_text,
			$dcc_enabled ? '<span class="dashicons dashicons-yes"></span>' : '<span class="dashicons dashicons-no"></span>',
			$dcc_enabled ? '_self' : '_blank',
			esc_url( $dcc_button_url ),
			esc_html( $dcc_button_text )
		);
	},
	'wcgateway.settings.connection.pui-status-text'        => static function ( ContainerInterface $container ): string {
		$state = $container->get( 'onboarding.state' );
		if ( $state->current_state() < State::STATE_ONBOARDED ) {
			return '';
		}

		$pui_product_status = $container->get( 'wcgateway.pay-upon-invoice-product-status' );
		assert( $pui_product_status instanceof PayUponInvoiceProductStatus );

		$environment = $container->get( 'onboarding.environment' );
		assert( $environment instanceof Environment );

		$pui_enabled = $pui_product_status->pui_is_active();

		$enabled_status_text  = esc_html__( 'Status: Available', 'woocommerce-paypal-payments' );
		$disabled_status_text = esc_html__( 'Status: Not yet enabled', 'woocommerce-paypal-payments' );

		$enable_pui_url = $environment->current_environment_is( Environment::PRODUCTION )
			? $container->get( 'wcgateway.enable-pui-url-live' )
			: $container->get( 'wcgateway.enable-pui-url-sandbox' );

		$pui_button_url = $pui_enabled
			? admin_url( 'admin.php?page=wc-settings&tab=checkout&section=ppcp-pay-upon-invoice-gateway' )
			: $enable_pui_url;

		$pui_button_text = $pui_enabled
			? esc_html__( 'Settings', 'woocommerce-paypal-payments' )
			: esc_html__( 'Enable Pay upon Invoice', 'woocommerce-paypal-payments' );

		return sprintf(
			'<p>%1$s %2$s</p><p><a target="%3$s" href="%4$s" class="button">%5$s</a></p>',
			$pui_enabled ? $enabled_status_text : $disabled_status_text,
			$pui_enabled ? '<span class="dashicons dashicons-yes"></span>' : '<span class="dashicons dashicons-no"></span>',
			$pui_enabled ? '_self' : '_blank',
			esc_url( $pui_button_url ),
			esc_html( $pui_button_text )
		);
	},
	'pui.status-cache'                                     => static function( ContainerInterface $container ): Cache {
		return new Cache( 'ppcp-paypal-pui-status-cache' );
	},
	'dcc.status-cache'                                     => static function( ContainerInterface $container ): Cache {
		return new Cache( 'ppcp-paypal-dcc-status-cache' );
	},
	'wcgateway.button.locations'                           => static function( ContainerInterface $container ): array {
		return array(
			'product'   => 'Single Product',
			'cart'      => 'Cart',
			'checkout'  => 'Checkout',
			'mini-cart' => 'Mini Cart',
		);
	},
	'wcgateway.settings.pay-later.messaging-locations'     => static function( ContainerInterface $container ): array {
		$button_locations = $container->get( 'wcgateway.button.locations' );
		unset( $button_locations['mini-cart'] );
		return array_merge(
			$button_locations,
			array(
				'shop' => __( 'Shop', 'woocommerce-paypal-payments' ),
				'home' => __( 'Home', 'woocommerce-paypal-payments' ),
			)
		);
	},
	'wcgateway.button.default-locations'                   => static function( ContainerInterface $container ): array {
		return array_keys( $container->get( 'wcgateway.settings.pay-later.messaging-locations' ) );
	},
	'wcgateway.settings.pay-later.button-locations'        => static function( ContainerInterface $container ): array {
		$settings = $container->get( 'wcgateway.settings' );
		assert( $settings instanceof Settings );

		$button_locations = $container->get( 'wcgateway.button.locations' );

		$smart_button_selected_locations = $settings->has( 'smart_button_locations' ) ? $settings->get( 'smart_button_locations' ) : array();

		return array_intersect_key( $button_locations, array_flip( $smart_button_selected_locations ) );
	},
	'wcgateway.ppcp-gateways'                              => static function ( ContainerInterface $container ): array {
		return array(
			PayPalGateway::ID,
			CreditCardGateway::ID,
			PayUponInvoiceGateway::ID,
			CardButtonGateway::ID,
			OXXOGateway::ID,
		);
	},
	'wcgateway.gateway-repository'                         => static function ( ContainerInterface $container ): GatewayRepository {
		return new GatewayRepository(
			$container->get( 'wcgateway.ppcp-gateways' )
		);
	},
	'wcgateway.is-fraudnet-enabled'                        => static function ( ContainerInterface $container ): bool {
		$settings      = $container->get( 'wcgateway.settings' );
		assert( $settings instanceof Settings );

		return $settings->has( 'fraudnet_enabled' ) && $settings->get( 'fraudnet_enabled' );
	},
	'wcgateway.fraudnet-assets'                            => function( ContainerInterface $container ) : FraudNetAssets {
		return new FraudNetAssets(
			$container->get( 'wcgateway.url' ),
			$container->get( 'ppcp.asset-version' ),
			$container->get( 'wcgateway.fraudnet' ),
			$container->get( 'onboarding.environment' ),
			$container->get( 'wcgateway.settings' ),
			$container->get( 'wcgateway.gateway-repository' ),
			$container->get( 'session.handler' ),
			$container->get( 'wcgateway.is-fraudnet-enabled' )
		);
	},
	'wcgateway.cli.settings.command'                       => function( ContainerInterface $container ) : SettingsCommand {
		return new SettingsCommand(
			$container->get( 'wcgateway.settings' )
		);
	},
	'wcgateway.display-manager'                            => SingletonDecorator::make(
		static function( ContainerInterface $container ): DisplayManager {
			$settings = $container->get( 'wcgateway.settings' );
			return new DisplayManager( $settings );
		}
	),
);
