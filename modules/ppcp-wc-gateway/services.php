<?php
/**
 * The services of the Gateway module.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway
 */

// phpcs:disable WordPress.Security.NonceVerification.Recommended

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway;

use WooCommerce\PayPalCommerce\ApiClient\Entity\ApplicationContext;
use WooCommerce\PayPalCommerce\ApiClient\Helper\Cache;
use WooCommerce\PayPalCommerce\ApiClient\Helper\DccApplies;
use WooCommerce\PayPalCommerce\Button\Helper\MessagesDisclaimers;
use WooCommerce\PayPalCommerce\Onboarding\Environment;
use WooCommerce\PayPalCommerce\Onboarding\State;
use WooCommerce\PayPalCommerce\WcGateway\Admin\OrderTablePaymentStatusColumn;
use WooCommerce\PayPalCommerce\WcGateway\Admin\PaymentStatusOrderDetail;
use WooCommerce\PayPalCommerce\WcGateway\Admin\RenderAuthorizeAction;
use WooCommerce\PayPalCommerce\WcGateway\Checkout\CheckoutPayPalAddressPreset;
use WooCommerce\PayPalCommerce\WcGateway\Checkout\DisableGateways;
use WooCommerce\PayPalCommerce\WcGateway\Endpoint\ReturnUrlEndpoint;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\TransactionUrlProvider;
use Woocommerce\PayPalCommerce\WcGateway\Helper\DccProductStatus;
use Woocommerce\PayPalCommerce\WcGateway\Helper\SettingsStatus;
use WooCommerce\PayPalCommerce\WcGateway\Notice\AuthorizeOrderActionNotice;
use WooCommerce\PayPalCommerce\WcGateway\Notice\ConnectAdminNotice;
use WooCommerce\PayPalCommerce\WcGateway\Notice\DccWithoutPayPalAdminNotice;
use WooCommerce\PayPalCommerce\WcGateway\Processor\AuthorizedPaymentsProcessor;
use WooCommerce\PayPalCommerce\WcGateway\Processor\OrderProcessor;
use WooCommerce\PayPalCommerce\WcGateway\Processor\RefundProcessor;
use WooCommerce\PayPalCommerce\WcGateway\Settings\SectionsRenderer;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
use WooCommerce\PayPalCommerce\WcGateway\Settings\SettingsListener;
use WooCommerce\PayPalCommerce\WcGateway\Settings\SettingsRenderer;
use WooCommerce\PayPalCommerce\Webhooks\Status\WebhooksStatusPage;

return array(
	'wcgateway.paypal-gateway'                     => static function ( $container ): PayPalGateway {
		$order_processor     = $container->get( 'wcgateway.order-processor' );
		$settings_renderer   = $container->get( 'wcgateway.settings.render' );
		$authorized_payments = $container->get( 'wcgateway.processor.authorized-payments' );
		$notice              = $container->get( 'wcgateway.notice.authorize-order-action' );
		$settings            = $container->get( 'wcgateway.settings' );
		$session_handler     = $container->get( 'session.handler' );
		$refund_processor    = $container->get( 'wcgateway.processor.refunds' );
		$state               = $container->get( 'onboarding.state' );
		$transaction_url_provider = $container->get( 'wcgateway.transaction-url-provider' );
		$subscription_helper = $container->get( 'subscription.helper' );
		$page_id             = $container->get( 'wcgateway.current-ppcp-settings-page-id' );
		$environment         = $container->get( 'onboarding.environment' );
		return new PayPalGateway(
			$settings_renderer,
			$order_processor,
			$authorized_payments,
			$notice,
			$settings,
			$session_handler,
			$refund_processor,
			$state,
			$transaction_url_provider,
			$subscription_helper,
			$page_id,
			$environment
		);
	},
	'wcgateway.credit-card-gateway'                => static function ( $container ): CreditCardGateway {
		$order_processor     = $container->get( 'wcgateway.order-processor' );
		$settings_renderer   = $container->get( 'wcgateway.settings.render' );
		$authorized_payments = $container->get( 'wcgateway.processor.authorized-payments' );
		$notice              = $container->get( 'wcgateway.notice.authorize-order-action' );
		$settings            = $container->get( 'wcgateway.settings' );
		$module_url          = $container->get( 'wcgateway.url' );
		$session_handler     = $container->get( 'session.handler' );
		$refund_processor    = $container->get( 'wcgateway.processor.refunds' );
		$state               = $container->get( 'onboarding.state' );
		$transaction_url_provider = $container->get( 'wcgateway.transaction-url-provider' );
		$payment_token_repository = $container->get( 'vaulting.repository.payment-token' );
		$purchase_unit_factory = $container->get( 'api.factory.purchase-unit' );
		$payer_factory = $container->get( 'api.factory.payer' );
		$order_endpoint = $container->get( 'api.endpoint.order' );
		$subscription_helper = $container->get( 'subscription.helper' );
		$logger = $container->get( 'woocommerce.logger.woocommerce' );
		$environment = $container->get( 'onboarding.environment' );
		return new CreditCardGateway(
			$settings_renderer,
			$order_processor,
			$authorized_payments,
			$notice,
			$settings,
			$module_url,
			$session_handler,
			$refund_processor,
			$state,
			$transaction_url_provider,
			$payment_token_repository,
			$purchase_unit_factory,
			$payer_factory,
			$order_endpoint,
			$subscription_helper,
			$logger,
			$environment
		);
	},
	'wcgateway.disabler'                           => static function ( $container ): DisableGateways {
		$session_handler = $container->get( 'session.handler' );
		$settings       = $container->get( 'wcgateway.settings' );
		return new DisableGateways( $session_handler, $settings );
	},

	'wcgateway.is-wc-payments-page'                => static function ( $container ): bool {
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		$tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : '';
		return 'wc-settings' === $page && 'checkout' === $tab;
	},

	'wcgateway.is-ppcp-settings-page'              => static function ( $container ): bool {
		if ( ! $container->get( 'wcgateway.is-wc-payments-page' ) ) {
			return false;
		}

		$section = isset( $_GET['section'] ) ? sanitize_text_field( wp_unslash( $_GET['section'] ) ) : '';
		return in_array( $section, array( PayPalGateway::ID, CreditCardGateway::ID, WebhooksStatusPage::ID ), true );
	},

	'wcgateway.current-ppcp-settings-page-id'      => static function ( $container ): string {
		if ( ! $container->get( 'wcgateway.is-ppcp-settings-page' ) ) {
			return '';
		}

		$section = isset( $_GET['section'] ) ? sanitize_text_field( wp_unslash( $_GET['section'] ) ) : '';
		$ppcp_tab = isset( $_GET[ SectionsRenderer::KEY ] ) ? sanitize_text_field( wp_unslash( $_GET[ SectionsRenderer::KEY ] ) ) : '';

		return $ppcp_tab ? $ppcp_tab : $section;
	},

	'wcgateway.settings'                           => static function ( $container ): Settings {
		return new Settings();
	},
	'wcgateway.notice.connect'                     => static function ( $container ): ConnectAdminNotice {
		$state    = $container->get( 'onboarding.state' );
		$settings = $container->get( 'wcgateway.settings' );
		return new ConnectAdminNotice( $state, $settings );
	},
	'wcgateway.notice.dcc-without-paypal'          => static function ( $container ): DccWithoutPayPalAdminNotice {
		$state    = $container->get( 'onboarding.state' );
		$settings = $container->get( 'wcgateway.settings' );
		$is_payments_page = $container->get( 'wcgateway.is-wc-payments-page' );
		$is_ppcp_settings_page = $container->get( 'wcgateway.is-ppcp-settings-page' );
		return new DccWithoutPayPalAdminNotice( $state, $settings, $is_payments_page, $is_ppcp_settings_page );
	},
	'wcgateway.notice.authorize-order-action'      =>
		static function ( $container ): AuthorizeOrderActionNotice {
			return new AuthorizeOrderActionNotice();
		},
	'wcgateway.settings.sections-renderer'         => static function ( $container ): SectionsRenderer {
		return new SectionsRenderer( $container->get( 'wcgateway.current-ppcp-settings-page-id' ) );
	},
	'wcgateway.settings.status'                    => static function ( $container ): SettingsStatus {
		$settings      = $container->get( 'wcgateway.settings' );
		return new SettingsStatus( $settings );
	},
	'wcgateway.settings.render'                    => static function ( $container ): SettingsRenderer {
		$settings      = $container->get( 'wcgateway.settings' );
		$state         = $container->get( 'onboarding.state' );
		$fields        = $container->get( 'wcgateway.settings.fields' );
		$dcc_applies    = $container->get( 'api.helpers.dccapplies' );
		$messages_apply = $container->get( 'button.helper.messages-apply' );
		$dcc_product_status = $container->get( 'wcgateway.helper.dcc-product-status' );
		$settings_status = $container->get( 'wcgateway.settings.status' );
		$page_id         = $container->get( 'wcgateway.current-ppcp-settings-page-id' );
		return new SettingsRenderer(
			$settings,
			$state,
			$fields,
			$dcc_applies,
			$messages_apply,
			$dcc_product_status,
			$settings_status,
			$page_id
		);
	},
	'wcgateway.settings.listener'                  => static function ( $container ): SettingsListener {
		$settings         = $container->get( 'wcgateway.settings' );
		$fields           = $container->get( 'wcgateway.settings.fields' );
		$webhook_registrar = $container->get( 'webhook.registrar' );
		$state            = $container->get( 'onboarding.state' );
		$cache = new Cache( 'ppcp-paypal-bearer' );
		$bearer = $container->get( 'api.bearer' );
		$page_id = $container->get( 'wcgateway.current-ppcp-settings-page-id' );
		return new SettingsListener( $settings, $fields, $webhook_registrar, $cache, $state, $bearer, $page_id );
	},
	'wcgateway.order-processor'                    => static function ( $container ): OrderProcessor {

		$session_handler              = $container->get( 'session.handler' );
		$order_endpoint               = $container->get( 'api.endpoint.order' );
		$order_factory                = $container->get( 'api.factory.order' );
		$threed_secure                = $container->get( 'button.helper.three-d-secure' );
		$authorized_payments_processor = $container->get( 'wcgateway.processor.authorized-payments' );
		$settings                      = $container->get( 'wcgateway.settings' );
		$environment                   = $container->get( 'onboarding.environment' );
		$logger                        = $container->get( 'woocommerce.logger.woocommerce' );
		return new OrderProcessor(
			$session_handler,
			$order_endpoint,
			$order_factory,
			$threed_secure,
			$authorized_payments_processor,
			$settings,
			$logger,
			$environment
		);
	},
	'wcgateway.processor.refunds'                  => static function ( $container ): RefundProcessor {
		$order_endpoint    = $container->get( 'api.endpoint.order' );
		$payments_endpoint    = $container->get( 'api.endpoint.payments' );
		$logger                        = $container->get( 'woocommerce.logger.woocommerce' );
		return new RefundProcessor( $order_endpoint, $payments_endpoint, $logger );
	},
	'wcgateway.processor.authorized-payments'      => static function ( $container ): AuthorizedPaymentsProcessor {
		$order_endpoint    = $container->get( 'api.endpoint.order' );
		$payments_endpoint = $container->get( 'api.endpoint.payments' );
		$logger = $container->get( 'woocommerce.logger.woocommerce' );
		return new AuthorizedPaymentsProcessor( $order_endpoint, $payments_endpoint, $logger );
	},
	'wcgateway.admin.render-authorize-action'      => static function ( $container ): RenderAuthorizeAction {

		return new RenderAuthorizeAction();
	},
	'wcgateway.admin.order-payment-status'         => static function ( $container ): PaymentStatusOrderDetail {
		$column = $container->get( 'wcgateway.admin.orders-payment-status-column' );
		return new PaymentStatusOrderDetail( $column );
	},
	'wcgateway.admin.orders-payment-status-column' => static function ( $container ): OrderTablePaymentStatusColumn {
		$settings = $container->get( 'wcgateway.settings' );
		return new OrderTablePaymentStatusColumn( $settings );
	},

	'wcgateway.settings.fields'                    => static function ( $container ): array {

		$state = $container->get( 'onboarding.state' );
		$messages_disclaimers = $container->get( 'button.helper.messages-disclaimers' );

		$fields              = array(
			'sandbox_on'                     => array(
				'title'        => __( 'Sandbox', 'woocommerce-paypal-payments' ),
				'type'         => 'checkbox',
				'label'        => __( 'To test your WooCommerce installation, you can use the sandbox mode.', 'woocommerce-paypal-payments' ),
				'default'      => 0,
				'screens'      => array(
					State::STATE_START,
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),

			// Production credentials.
			'credentials_production_heading' => array(
				'heading'      => __( 'API Credentials', 'woocommerce-paypal-payments' ),
				'type'         => 'ppcp-heading',
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'ppcp_onboarding_production'     => array(
				'title'        => __( 'Connect to PayPal', 'woocommerce-paypal-payments' ),
				'type'         => 'ppcp_onboarding',
				'screens'      => array(
					State::STATE_START,
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'env'          => 'production',
				'requirements' => array(),
				'gateway'      => 'paypal',
				'description'  => __( 'Setup or link an existing PayPal account.', 'woocommerce-paypal-payments' ),
			),
			'ppcp_disconnect_production'     => array(
				'title'        => __( 'Disconnect from PayPal', 'woocommerce-paypal-payments' ),
				'type'         => 'ppcp-text',
				'classes'      => array( State::STATE_ONBOARDED === $state->production_state() ? 'onboarded' : '' ),
				'text'         => '<button type="button" class="button ppcp-disconnect production">' . esc_html__( 'Disconnect', 'woocommerce-paypal-payments' ) . '</button>',
				'screens'      => array(
					State::STATE_START,
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'env'          => 'production',
				'requirements' => array(),
				'gateway'      => 'paypal',
				'description'  => __( 'Click to reset current credentials and use another account.', 'woocommerce-paypal-payments' ),
			),
			'production_toggle_manual_input' => array(
				'type'         => 'ppcp-text',
				'title'        => __( 'Manual mode', 'woocommerce-paypal-payments' ),
				'classes'      => array( State::STATE_ONBOARDED === $state->production_state() ? 'onboarded' : '' ),
				'text'         => '<button type="button" id="ppcp[production_toggle_manual_input]" class="production-toggle">' . __( 'Toggle to manual credential input', 'woocommerce-paypal-payments' ) . '</button>',
				'screens'      => array(
					State::STATE_START,
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'merchant_email_production'      => array(
				'title'        => __( 'Live Email address', 'woocommerce-paypal-payments' ),
				'classes'      => array( State::STATE_ONBOARDED === $state->production_state() ? 'onboarded' : '' ),
				'type'         => 'text',
				'required'     => true,
				'desc_tip'     => true,
				'description'  => __( 'The email address of your PayPal account.', 'woocommerce-paypal-payments' ),
				'default'      => '',
				'screens'      => array(
					State::STATE_START,
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'merchant_id_production'         => array(
				'title'        => __( 'Live Merchant Id', 'woocommerce-paypal-payments' ),
				'classes'      => array( State::STATE_ONBOARDED === $state->production_state() ? 'onboarded' : '' ),
				'type'         => 'ppcp-text-input',
				'desc_tip'     => true,
				'description'  => __( 'The merchant id of your account ', 'woocommerce-paypal-payments' ),
				'default'      => false,
				'screens'      => array(
					State::STATE_START,
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'client_id_production'           => array(
				'title'        => __( 'Live Client Id', 'woocommerce-paypal-payments' ),
				'classes'      => array( State::STATE_ONBOARDED === $state->production_state() ? 'onboarded' : '' ),
				'type'         => 'ppcp-text-input',
				'desc_tip'     => true,
				'description'  => __( 'The client id of your api ', 'woocommerce-paypal-payments' ),
				'default'      => false,
				'screens'      => array(
					State::STATE_START,
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'client_secret_production'       => array(
				'title'        => __( 'Live Secret Key', 'woocommerce-paypal-payments' ),
				'classes'      => array( State::STATE_ONBOARDED === $state->production_state() ? 'onboarded' : '' ),
				'type'         => 'ppcp-password',
				'desc_tip'     => true,
				'description'  => __( 'The secret key of your api', 'woocommerce-paypal-payments' ),
				'default'      => false,
				'screens'      => array(
					State::STATE_START,
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),

			// Sandbox credentials.
			'credentials_sandbox_heading'    => array(
				'heading'      => __( 'Sandbox API Credentials', 'woocommerce-paypal-payments' ),
				'type'         => 'ppcp-heading',
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
				'description'  => __( 'Your account setting is set to sandbox, no real charging takes place. To accept live payments, switch your environment to live and connect your PayPal account.', 'woocommerce-paypal-payments' ),
			),

			'ppcp_onboarding_sandbox'        => array(
				'title'        => __( 'Connect to PayPal', 'woocommerce-paypal-payments' ),
				'type'         => 'ppcp_onboarding',
				'screens'      => array(
					State::STATE_START,
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'env'          => 'sandbox',
				'requirements' => array(),
				'gateway'      => 'paypal',
				'description'  => __( 'Setup or link an existing PayPal Sandbox account.', 'woocommerce-paypal-payments' ),
			),
			'ppcp_disconnect_sandbox'        => array(
				'title'        => __( 'Disconnect from PayPal Sandbox', 'woocommerce-paypal-payments' ),
				'type'         => 'ppcp-text',
				'classes'      => array( State::STATE_ONBOARDED === $state->sandbox_state() ? 'onboarded' : '' ),
				'text'         => '<button type="button" class="button ppcp-disconnect sandbox">' . esc_html__( 'Disconnect', 'woocommerce-paypal-payments' ) . '</button>',
				'screens'      => array(
					State::STATE_START,
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'env'          => 'production',
				'requirements' => array(),
				'gateway'      => 'paypal',
				'description'  => __( 'Click to reset current credentials and use another account.', 'woocommerce-paypal-payments' ),
			),
			'sandbox_toggle_manual_input'    => array(
				'type'         => 'ppcp-text',
				'title'        => __( 'Manual mode', 'woocommerce-paypal-payments' ),
				'classes'      => array( State::STATE_ONBOARDED === $state->sandbox_state() ? 'onboarded' : '' ),
				'text'         => '<button type="button" id="ppcp[sandbox_toggle_manual_input]" class="sandbox-toggle">' . __( 'Toggle to manual credential input', 'woocommerce-paypal-payments' ) . '</button>',
				'screens'      => array(
					State::STATE_START,
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'merchant_email_sandbox'         => array(
				'title'        => __( 'Sandbox Email address', 'woocommerce-paypal-payments' ),
				'classes'      => array( State::STATE_ONBOARDED === $state->sandbox_state() ? 'onboarded' : '' ),
				'type'         => 'text',
				'required'     => true,
				'desc_tip'     => true,
				'description'  => __( 'The email address of your PayPal account.', 'woocommerce-paypal-payments' ),
				'default'      => '',
				'screens'      => array(
					State::STATE_START,
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'merchant_id_sandbox'            => array(
				'title'        => __( 'Sandbox Merchant Id', 'woocommerce-paypal-payments' ),
				'classes'      => array( State::STATE_ONBOARDED === $state->sandbox_state() ? 'onboarded' : '' ),
				'type'         => 'ppcp-text-input',
				'desc_tip'     => true,
				'description'  => __( 'The merchant id of your account ', 'woocommerce-paypal-payments' ),
				'default'      => false,
				'screens'      => array(
					State::STATE_START,
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'client_id_sandbox'              => array(
				'title'        => __( 'Sandbox Client Id', 'woocommerce-paypal-payments' ),
				'classes'      => array( State::STATE_ONBOARDED === $state->sandbox_state() ? 'onboarded' : '' ),
				'type'         => 'ppcp-text-input',
				'desc_tip'     => true,
				'description'  => __( 'The client id of your api ', 'woocommerce-paypal-payments' ),
				'default'      => false,
				'screens'      => array(
					State::STATE_START,
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'client_secret_sandbox'          => array(
				'title'        => __( 'Sandbox Secret Key', 'woocommerce-paypal-payments' ),
				'classes'      => array( State::STATE_ONBOARDED === $state->sandbox_state() ? 'onboarded' : '' ),
				'type'         => 'ppcp-password',
				'desc_tip'     => true,
				'description'  => __( 'The secret key of your api', 'woocommerce-paypal-payments' ),
				'default'      => false,
				'screens'      => array(
					State::STATE_START,
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),

			'checkout_settings_heading'      => array(
				'heading'      => __( 'PayPal Checkout Settings', 'woocommerce-paypal-payments' ),
				'type'         => 'ppcp-heading',
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'title'                          => array(
				'title'        => __( 'Title', 'woocommerce-paypal-payments' ),
				'type'         => 'text',
				'description'  => __(
					'This controls the title which the user sees during checkout.',
					'woocommerce-paypal-payments'
				),
				'default'      => __( 'PayPal', 'woocommerce-paypal-payments' ),
				'desc_tip'     => true,
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'dcc_enabled'                    => array(
				'title'        => __( 'Enable/Disable', 'woocommerce-paypal-payments' ),
				'desc_tip'     => true,
				'description'  => __( 'Once enabled, the Credit Card option will show up in the checkout.', 'woocommerce-paypal-payments' ),
				'label'        => __( 'Enable PayPal Card Processing', 'woocommerce-paypal-payments' ),
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
			'dcc_gateway_title'              => array(
				'title'        => __( 'Title', 'woocommerce-paypal-payments' ),
				'type'         => 'text',
				'description'  => __(
					'This controls the title which the user sees during checkout.',
					'woocommerce-paypal-payments'
				),
				'default'      => __( 'Credit Cards', 'woocommerce-paypal-payments' ),
				'desc_tip'     => true,
				'screens'      => array(
					State::STATE_ONBOARDED,
				),
				'requirements' => array(
					'dcc',
				),
				'gateway'      => 'dcc',
			),
			'description'                    => array(
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
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'intent'                         => array(
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
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'capture_for_virtual_only'       => array(
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
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'payee_preferred'                => array(
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
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'brand_name'                     => array(
				'title'        => __( 'Brand Name', 'woocommerce-paypal-payments' ),
				'type'         => 'text',
				'default'      => get_bloginfo( 'name' ),
				'desc_tip'     => true,
				'description'  => __(
					'Control the name of your shop, customers will see in the PayPal process.',
					'woocommerce-paypal-payments'
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'landing_page'                   => array(
				'title'        => __( 'Landing Page', 'woocommerce-paypal-payments' ),
				'type'         => 'select',
				'class'        => array(),
				'input_class'  => array( 'wc-enhanced-select' ),
				'default'      => 'gold',
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
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'disable_funding'                => array(
				'title'        => __( 'Disable funding sources', 'woocommerce-paypal-payments' ),
				'type'         => 'ppcp-multiselect',
				'class'        => array(),
				'input_class'  => array( 'wc-enhanced-select' ),
				'default'      => array(),
				'desc_tip'     => true,
				'description'  => __(
					'By default all possible funding sources will be shown. You can disable some sources, if you wish.',
					'woocommerce-paypal-payments'
				),
				'options'      => array(
					'card'       => _x( 'Credit or debit cards', 'Name of payment method', 'woocommerce-paypal-payments' ),
					'credit'     => _x( 'Pay Later', 'Name of payment method', 'woocommerce-paypal-payments' ),
					'sepa'       => _x( 'SEPA-Lastschrift', 'Name of payment method', 'woocommerce-paypal-payments' ),
					'bancontact' => _x( 'Bancontact', 'Name of payment method', 'woocommerce-paypal-payments' ),
					'eps'        => _x( 'eps', 'Name of payment method', 'woocommerce-paypal-payments' ),
					'giropay'    => _x( 'giropay', 'Name of payment method', 'woocommerce-paypal-payments' ),
					'ideal'      => _x( 'iDEAL', 'Name of payment method', 'woocommerce-paypal-payments' ),
					'mybank'     => _x( 'MyBank', 'Name of payment method', 'woocommerce-paypal-payments' ),
					'p24'        => _x( 'Przelewy24', 'Name of payment method', 'woocommerce-paypal-payments' ),
					'sofort'     => _x( 'Sofort', 'Name of payment method', 'woocommerce-paypal-payments' ),
					'venmo'      => _x( 'Venmo', 'Name of payment method', 'woocommerce-paypal-payments' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'vault_enabled'                  => array(
				'title'        => __( 'Vaulting', 'woocommerce-paypal-payments' ),
				'type'         => 'checkbox',
				'desc_tip'     => true,
				'label'        => sprintf(
					// translators: %1$s and %2$s are the opening and closing of HTML <a> tag.
					__( 'Enable saved cards and subscription features on your store. To use vaulting features, you must %1$senable vaulting on your account%2$s.', 'woocommerce-paypal-payments' ),
					'<a
						href="https://docs.woocommerce.com/document/woocommerce-paypal-payments/#enable-vaulting-on-your-live-account"
						target="_blank"
					>',
					'</a>'
				),
				'description'  => __( 'Allow registered buyers to save PayPal and Credit Card accounts. Allow Subscription renewals.', 'woocommerce-paypal-payments' ),
				'default'      => false,
				'screens'      => array(
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => array( 'paypal', 'dcc' ),
			),
			'logging_enabled'                => array(
				'title'        => __( 'Logging', 'woocommerce-paypal-payments' ),
				'type'         => 'checkbox',
				'desc_tip'     => true,
				'label'        => __( 'Enable logging', 'woocommerce-paypal-payments' ),
				'description'  => __( 'Enable logging of unexpected behavior. This can also log private data and should only be enabled in a development or stage environment.', 'woocommerce-paypal-payments' ),
				'default'      => false,
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'prefix'                         => array(
				'title'        => __( 'Invoice prefix', 'woocommerce-paypal-payments' ),
				'type'         => 'text',
				'desc_tip'     => true,
				'description'  => __( 'If you use your PayPal account with more than one installation, please use a distinct prefix to separate those installations. Please do not use numbers in your prefix.', 'woocommerce-paypal-payments' ),
				'default'      => ( static function (): string {
					$site_url = get_site_url( get_current_blog_id() );
					$hash = md5( $site_url );
					$letters = preg_replace( '~\d~', '', $hash );
					return substr( $letters, 0, 6 ) . '-';
				} )(),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),

			// General button styles.
			'button_style_heading'           => array(
				'heading'      => __( 'Checkout', 'woocommerce-paypal-payments' ),
				'type'         => 'ppcp-heading',
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
				'description'  => __( 'Customize the appearance of PayPal Checkout on the checkout page.', 'woocommerce-paypal-payments' ),
			),
			'button_enabled'                 => array(
				'title'        => __( 'Enable buttons on Checkout', 'woocommerce-paypal-payments' ),
				'type'         => 'checkbox',
				'label'        => __( 'Enable on Checkout', 'woocommerce-paypal-payments' ),
				'default'      => true,
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_layout'                  => array(
				'title'        => __( 'Button Layout', 'woocommerce-paypal-payments' ),
				'type'         => 'select',
				'class'        => array(),
				'input_class'  => array( 'wc-enhanced-select' ),
				'default'      => 'vertical',
				'desc_tip'     => true,
				'description'  => __(
					'If additional funding sources are available to the buyer through PayPal, then multiple buttons are displayed in the space provided. Choose "vertical" for a dynamic list of alternative and local payment options, or "horizontal" when space is limited.',
					'woocommerce-paypal-payments'
				),
				'options'      => array(
					'vertical'   => __( 'Vertical', 'woocommerce-paypal-payments' ),
					'horizontal' => __( 'Horizontal', 'woocommerce-paypal-payments' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_tagline'                 => array(
				'title'        => __( 'Tagline', 'woocommerce-paypal-payments' ),
				'type'         => 'checkbox',
				'default'      => true,
				'label'        => __( 'Enable tagline', 'woocommerce-paypal-payments' ),
				'desc_tip'     => true,
				'description'  => __(
					'Add the tagline. This line will only show up, if you select a horizontal layout.',
					'woocommerce-paypal-payments'
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_label'                   => array(
				'title'        => __( 'Button Label', 'woocommerce-paypal-payments' ),
				'type'         => 'select',
				'class'        => array(),
				'input_class'  => array( 'wc-enhanced-select' ),
				'default'      => apply_filters( 'woocommerce_paypal_payments_button_label_default', 'paypal' ),
				'desc_tip'     => true,
				'description'  => __(
					'This controls the label on the primary button.',
					'woocommerce-paypal-payments'
				),
				'options'      => array(
					'paypal'   => __( 'PayPal', 'woocommerce-paypal-payments' ),
					'checkout' => __( 'PayPal Checkout', 'woocommerce-paypal-payments' ),
					'buynow'   => __( 'PayPal Buy Now', 'woocommerce-paypal-payments' ),
					'pay'      => __( 'Pay with PayPal', 'woocommerce-paypal-payments' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_color'                   => array(
				'title'        => __( 'Color', 'woocommerce-paypal-payments' ),
				'type'         => 'select',
				'class'        => array(),
				'input_class'  => array( 'wc-enhanced-select' ),
				'default'      => 'gold',
				'desc_tip'     => true,
				'description'  => __(
					'Controls the background color of the primary button. Use "Gold" to leverage PayPal\'s recognition and preference, or change it to match your site design or aesthetic.',
					'woocommerce-paypal-payments'
				),
				'options'      => array(
					'gold'   => __( 'Gold (Recommended)', 'woocommerce-paypal-payments' ),
					'blue'   => __( 'Blue', 'woocommerce-paypal-payments' ),
					'silver' => __( 'Silver', 'woocommerce-paypal-payments' ),
					'black'  => __( 'Black', 'woocommerce-paypal-payments' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_shape'                   => array(
				'title'        => __( 'Shape', 'woocommerce-paypal-payments' ),
				'type'         => 'select',
				'class'        => array(),
				'input_class'  => array( 'wc-enhanced-select' ),
				'default'      => 'rect',
				'desc_tip'     => true,
				'description'  => __(
					'The pill-shaped button\'s unique and powerful shape signifies PayPal in people\'s minds. Use the rectangular button as an alternative when pill-shaped buttons might pose design challenges.',
					'woocommerce-paypal-payments'
				),
				'options'      => array(
					'pill' => __( 'Pill', 'woocommerce-paypal-payments' ),
					'rect' => __( 'Rectangle', 'woocommerce-paypal-payments' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'message_heading'                => array(
				'heading'      => __( 'Pay Later on Checkout', 'woocommerce-paypal-payments' ),
				'type'         => 'ppcp-heading',
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array( 'messages' ),
				'gateway'      => 'paypal',
				'description'  => str_replace( '<a>', '<a href="' . $messages_disclaimers->link_for_country() . '" target="_blank">', __( 'Displays Pay Later messaging for available offers. Restrictions apply. <a>Click here to learn more</a>. Pay Later button will show for eligible buyers and PayPal determines eligibility.', 'woocommerce-paypal-payments' ) ),
				'class'        => array( 'ppcp-subheading' ),
			),
			'message_enabled'                => array(
				'title'        => __( 'Enable message on Checkout', 'woocommerce-paypal-payments' ),
				'type'         => 'checkbox',
				'label'        => __( 'Enable on Checkout', 'woocommerce-paypal-payments' ),
				'default'      => true,
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array( 'messages' ),
				'gateway'      => 'paypal',
			),
			'message_layout'                 => array(
				'title'        => __( 'Pay Later Messaging layout', 'woocommerce-paypal-payments' ),
				'type'         => 'select',
				'class'        => array(),
				'input_class'  => array( 'wc-enhanced-select' ),
				'default'      => 'text',
				'desc_tip'     => true,
				'description'  => __(
					'The layout of the message.',
					'woocommerce-paypal-payments'
				),
				'options'      => array(
					'text' => __( 'Text', 'woocommerce-paypal-payments' ),
					'flex' => __( 'Flex', 'woocommerce-paypal-payments' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array( 'messages' ),
				'gateway'      => 'paypal',
			),
			'message_logo'                   => array(
				'title'        => __( 'Pay Later Messaging logo', 'woocommerce-paypal-payments' ),
				'type'         => 'select',
				'class'        => array(),
				'input_class'  => array( 'wc-enhanced-select' ),
				'default'      => 'primary',
				'desc_tip'     => true,
				'description'  => __(
					'What logo the text message contains. Only applicable, when the layout style Text is used.',
					'woocommerce-paypal-payments'
				),
				'options'      => array(
					'primary'     => __( 'Primary', 'woocommerce-paypal-payments' ),
					'alternative' => __( 'Alternative', 'woocommerce-paypal-payments' ),
					'inline'      => __( 'Inline', 'woocommerce-paypal-payments' ),
					'none'        => __( 'None', 'woocommerce-paypal-payments' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array( 'messages' ),
				'gateway'      => 'paypal',
			),
			'message_position'               => array(
				'title'        => __( 'Pay Later Messaging logo position', 'woocommerce-paypal-payments' ),
				'type'         => 'select',
				'class'        => array(),
				'input_class'  => array( 'wc-enhanced-select' ),
				'default'      => 'left',
				'desc_tip'     => true,
				'description'  => __(
					'The position of the logo. Only applicable, when the layout style Text is used.',
					'woocommerce-paypal-payments'
				),
				'options'      => array(
					'left'  => __( 'Left', 'woocommerce-paypal-payments' ),
					'right' => __( 'Right', 'woocommerce-paypal-payments' ),
					'top'   => __( 'Top', 'woocommerce-paypal-payments' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array( 'messages' ),
				'gateway'      => 'paypal',
			),
			'message_color'                  => array(
				'title'        => __( 'Pay Later Messaging text color', 'woocommerce-paypal-payments' ),
				'type'         => 'select',
				'class'        => array(),
				'input_class'  => array( 'wc-enhanced-select' ),
				'default'      => 'black',
				'desc_tip'     => true,
				'description'  => __(
					'The color of the text. Only applicable, when the layout style Text is used.',
					'woocommerce-paypal-payments'
				),
				'options'      => array(
					'black'      => __( 'Black', 'woocommerce-paypal-payments' ),
					'white'      => __( 'White', 'woocommerce-paypal-payments' ),
					'monochrome' => __( 'Monochrome', 'woocommerce-paypal-payments' ),
					'grayscale'  => __( 'Grayscale', 'woocommerce-paypal-payments' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array( 'messages' ),
				'gateway'      => 'paypal',
			),
			'message_flex_color'             => array(
				'title'        => __( 'Pay Later Messaging color', 'woocommerce-paypal-payments' ),
				'type'         => 'select',
				'class'        => array(),
				'input_class'  => array( 'wc-enhanced-select' ),
				'default'      => 'blue',
				'desc_tip'     => true,
				'description'  => __(
					'The color of the text. Only applicable, when the layout style Flex is used.',
					'woocommerce-paypal-payments'
				),
				'options'      => array(
					'blue'            => __( 'Blue', 'woocommerce-paypal-payments' ),
					'black'           => __( 'Black', 'woocommerce-paypal-payments' ),
					'white'           => __( 'White', 'woocommerce-paypal-payments' ),
					'white-no-border' => __( 'White no border', 'woocommerce-paypal-payments' ),
					'gray'            => __( 'Gray', 'woocommerce-paypal-payments' ),
					'monochrome'      => __( 'Monochrome', 'woocommerce-paypal-payments' ),
					'grayscale'       => __( 'Grayscale', 'woocommerce-paypal-payments' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array( 'messages' ),
				'gateway'      => 'paypal',
			),
			'message_flex_ratio'             => array(
				'title'        => __( 'Pay Later Messaging ratio', 'woocommerce-paypal-payments' ),
				'type'         => 'select',
				'class'        => array(),
				'input_class'  => array( 'wc-enhanced-select' ),
				'default'      => '1x1',
				'desc_tip'     => true,
				'description'  => __(
					'The width/height ratio of the banner. Only applicable, when the layout style Flex is used.',
					'woocommerce-paypal-payments'
				),
				'options'      => array(
					'1x1'  => __( '1x1', 'woocommerce-paypal-payments' ),
					'1x4'  => __( '1x4', 'woocommerce-paypal-payments' ),
					'8x1'  => __( '8x1', 'woocommerce-paypal-payments' ),
					'20x1' => __( '20x1', 'woocommerce-paypal-payments' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array( 'messages' ),
				'gateway'      => 'paypal',
			),

			// Single product page.
			'button_product_heading'         => array(
				'heading'      => __( 'Single Product Page', 'woocommerce-paypal-payments' ),
				'type'         => 'ppcp-heading',
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
				'description'  => __( 'Customize the appearance of PayPal Checkout on the single product page.', 'woocommerce-paypal-payments' ),
			),
			'button_product_enabled'         => array(
				'title'        => __( 'Enable buttons on Single Product', 'woocommerce-paypal-payments' ),
				'type'         => 'checkbox',
				'label'        => __( 'Enable on Single Product', 'woocommerce-paypal-payments' ),
				'default'      => true,
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_product_layout'          => array(
				'title'        => __( 'Button Layout', 'woocommerce-paypal-payments' ),
				'type'         => 'select',
				'class'        => array(),
				'input_class'  => array( 'wc-enhanced-select' ),
				'default'      => 'horizontal',
				'desc_tip'     => true,
				'description'  => __(
					'If additional funding sources are available to the buyer through PayPal, such as Venmo, then multiple buttons are displayed in the space provided. Choose "vertical" for a dynamic list of alternative and local payment options, or "horizontal" when space is limited.',
					'woocommerce-paypal-payments'
				),
				'options'      => array(
					'vertical'   => __( 'Vertical', 'woocommerce-paypal-payments' ),
					'horizontal' => __( 'Horizontal', 'woocommerce-paypal-payments' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_product_tagline'         => array(
				'title'        => __( 'Tagline', 'woocommerce-paypal-payments' ),
				'type'         => 'checkbox',
				'label'        => __( 'Enable tagline', 'woocommerce-paypal-payments' ),
				'default'      => true,
				'desc_tip'     => true,
				'description'  => __(
					'Add the tagline. This line will only show up, if you select a horizontal layout.',
					'woocommerce-paypal-payments'
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_product_label'           => array(
				'title'        => __( 'Button Label', 'woocommerce-paypal-payments' ),
				'type'         => 'select',
				'class'        => array(),
				'input_class'  => array( 'wc-enhanced-select' ),
				'default'      => apply_filters( 'woocommerce_paypal_payments_button_product_label_default', 'paypal' ),
				'desc_tip'     => true,
				'description'  => __(
					'This controls the label on the primary button.',
					'woocommerce-paypal-payments'
				),
				'options'      => array(
					'paypal'   => __( 'PayPal', 'woocommerce-paypal-payments' ),
					'checkout' => __( 'PayPal Checkout', 'woocommerce-paypal-payments' ),
					'buynow'   => __( 'PayPal Buy Now', 'woocommerce-paypal-payments' ),
					'pay'      => __( 'Pay with PayPal', 'woocommerce-paypal-payments' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_product_color'           => array(
				'title'        => __( 'Color', 'woocommerce-paypal-payments' ),
				'type'         => 'select',
				'class'        => array(),
				'input_class'  => array( 'wc-enhanced-select' ),
				'default'      => 'gold',
				'desc_tip'     => true,
				'description'  => __(
					'Controls the background color of the primary button. Use "Gold" to leverage PayPal\'s recognition and preference, or change it to match your site design or aesthetic.',
					'woocommerce-paypal-payments'
				),
				'options'      => array(
					'gold'   => __( 'Gold (Recommended)', 'woocommerce-paypal-payments' ),
					'blue'   => __( 'Blue', 'woocommerce-paypal-payments' ),
					'silver' => __( 'Silver', 'woocommerce-paypal-payments' ),
					'black'  => __( 'Black', 'woocommerce-paypal-payments' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_product_shape'           => array(
				'title'        => __( 'Shape', 'woocommerce-paypal-payments' ),
				'type'         => 'select',
				'class'        => array(),
				'input_class'  => array( 'wc-enhanced-select' ),
				'default'      => 'rect',
				'desc_tip'     => true,
				'description'  => __(
					'The pill-shaped button\'s unique and powerful shape signifies PayPal in people\'s minds. Use the rectangular button as an alternative when pill-shaped buttons might pose design challenges.',
					'woocommerce-paypal-payments'
				),
				'options'      => array(
					'pill' => __( 'Pill', 'woocommerce-paypal-payments' ),
					'rect' => __( 'Rectangle', 'woocommerce-paypal-payments' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),

			'message_product_heading'        => array(
				'heading'      => __( 'Pay Later on Single Product Page', 'woocommerce-paypal-payments' ),
				'type'         => 'ppcp-heading',
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array( 'messages' ),
				'gateway'      => 'paypal',
				'description'  => str_replace( '<a>', '<a href="' . $messages_disclaimers->link_for_country() . '" target="_blank">', __( 'Displays Pay Later messaging for available offers. Restrictions apply. <a>Click here to learn more</a>. Pay Later button will show for eligible buyers and PayPal determines eligibility.', 'woocommerce-paypal-payments' ) ),
				'class'        => array( 'ppcp-subheading' ),
			),
			'message_product_enabled'        => array(
				'title'        => __( 'Enable message on Single Product', 'woocommerce-paypal-payments' ),
				'type'         => 'checkbox',
				'label'        => __( 'Enable on Single Product', 'woocommerce-paypal-payments' ),
				'default'      => true,
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array( 'messages' ),
				'gateway'      => 'paypal',
			),
			'message_product_layout'         => array(
				'title'        => __( 'Pay Later Messaging layout', 'woocommerce-paypal-payments' ),
				'type'         => 'select',
				'class'        => array(),
				'input_class'  => array( 'wc-enhanced-select' ),
				'default'      => 'text',
				'desc_tip'     => true,
				'description'  => __(
					'The layout of the message.',
					'woocommerce-paypal-payments'
				),
				'options'      => array(
					'text' => __( 'Text', 'woocommerce-paypal-payments' ),
					'flex' => __( 'Flex', 'woocommerce-paypal-payments' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array( 'messages' ),
				'gateway'      => 'paypal',
			),
			'message_product_logo'           => array(
				'title'        => __( 'Pay Later Messaging logo', 'woocommerce-paypal-payments' ),
				'type'         => 'select',
				'class'        => array(),
				'input_class'  => array( 'wc-enhanced-select' ),
				'default'      => 'primary',
				'desc_tip'     => true,
				'description'  => __(
					'What logo the text message contains. Only applicable, when the layout style Text is used.',
					'woocommerce-paypal-payments'
				),
				'options'      => array(
					'primary'     => __( 'Primary', 'woocommerce-paypal-payments' ),
					'alternative' => __( 'Alternative', 'woocommerce-paypal-payments' ),
					'inline'      => __( 'Inline', 'woocommerce-paypal-payments' ),
					'none'        => __( 'None', 'woocommerce-paypal-payments' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array( 'messages' ),
				'gateway'      => 'paypal',
			),
			'message_product_position'       => array(
				'title'        => __( 'Pay Later Messaging logo position', 'woocommerce-paypal-payments' ),
				'type'         => 'select',
				'class'        => array(),
				'input_class'  => array( 'wc-enhanced-select' ),
				'default'      => 'left',
				'desc_tip'     => true,
				'description'  => __(
					'The position of the logo. Only applicable, when the layout style Text is used.',
					'woocommerce-paypal-payments'
				),
				'options'      => array(
					'left'  => __( 'Left', 'woocommerce-paypal-payments' ),
					'right' => __( 'Right', 'woocommerce-paypal-payments' ),
					'top'   => __( 'Top', 'woocommerce-paypal-payments' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array( 'messages' ),
				'gateway'      => 'paypal',
			),
			'message_product_color'          => array(
				'title'        => __( 'Pay Later Messaging text color', 'woocommerce-paypal-payments' ),
				'type'         => 'select',
				'class'        => array(),
				'input_class'  => array( 'wc-enhanced-select' ),
				'default'      => 'black',
				'desc_tip'     => true,
				'description'  => __(
					'The color of the text. Only applicable, when the layout style Text is used.',
					'woocommerce-paypal-payments'
				),
				'options'      => array(
					'black'      => __( 'Black', 'woocommerce-paypal-payments' ),
					'white'      => __( 'White', 'woocommerce-paypal-payments' ),
					'monochrome' => __( 'Monochrome', 'woocommerce-paypal-payments' ),
					'grayscale'  => __( 'Grayscale', 'woocommerce-paypal-payments' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array( 'messages' ),
				'gateway'      => 'paypal',
			),
			'message_product_flex_color'     => array(
				'title'        => __( 'Pay Later Messaging color', 'woocommerce-paypal-payments' ),
				'type'         => 'select',
				'class'        => array(),
				'input_class'  => array( 'wc-enhanced-select' ),
				'default'      => 'blue',
				'desc_tip'     => true,
				'description'  => __(
					'The color of the text. Only applicable, when the layout style Flex is used.',
					'woocommerce-paypal-payments'
				),
				'options'      => array(
					'blue'            => __( 'Blue', 'woocommerce-paypal-payments' ),
					'black'           => __( 'Black', 'woocommerce-paypal-payments' ),
					'white'           => __( 'White', 'woocommerce-paypal-payments' ),
					'white-no-border' => __( 'White no border', 'woocommerce-paypal-payments' ),
					'gray'            => __( 'Gray', 'woocommerce-paypal-payments' ),
					'monochrome'      => __( 'Monochrome', 'woocommerce-paypal-payments' ),
					'grayscale'       => __( 'Grayscale', 'woocommerce-paypal-payments' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array( 'messages' ),
				'gateway'      => 'paypal',
			),
			'message_product_flex_ratio'     => array(
				'title'        => __( 'Pay Later Messaging ratio', 'woocommerce-paypal-payments' ),
				'type'         => 'select',
				'class'        => array(),
				'input_class'  => array( 'wc-enhanced-select' ),
				'default'      => '1x1',
				'desc_tip'     => true,
				'description'  => __(
					'The width/height ratio of the banner. Only applicable, when the layout style Flex is used.',
					'woocommerce-paypal-payments'
				),
				'options'      => array(
					'1x1'  => __( '1x1', 'woocommerce-paypal-payments' ),
					'1x4'  => __( '1x4', 'woocommerce-paypal-payments' ),
					'8x1'  => __( '8x1', 'woocommerce-paypal-payments' ),
					'20x1' => __( '20x1', 'woocommerce-paypal-payments' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array( 'messages' ),
				'gateway'      => 'paypal',
			),

			// Cart settings.
			'button_cart_heading'            => array(
				'heading'      => __( 'Cart', 'woocommerce-paypal-payments' ),
				'type'         => 'ppcp-heading',
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
				'description'  => __( 'Customize the appearance of PayPal Checkout on the cart page.', 'woocommerce-paypal-payments' ),
			),
			'button_cart_enabled'            => array(
				'title'        => __( 'Buttons on Cart', 'woocommerce-paypal-payments' ),
				'type'         => 'checkbox',
				'label'        => __( 'Enable on Cart', 'woocommerce-paypal-payments' ),
				'default'      => true,
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_cart_layout'             => array(
				'title'        => __( 'Button Layout', 'woocommerce-paypal-payments' ),
				'type'         => 'select',
				'class'        => array(),
				'input_class'  => array( 'wc-enhanced-select' ),
				'default'      => 'horizontal',
				'desc_tip'     => true,
				'description'  => __(
					'If additional funding sources are available to the buyer through PayPal, such as Venmo, then multiple buttons are displayed in the space provided. Choose "vertical" for a dynamic list of alternative and local payment options, or "horizontal" when space is limited.',
					'woocommerce-paypal-payments'
				),
				'options'      => array(
					'vertical'   => __( 'Vertical', 'woocommerce-paypal-payments' ),
					'horizontal' => __( 'Horizontal', 'woocommerce-paypal-payments' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_cart_tagline'            => array(
				'title'        => __( 'Tagline', 'woocommerce-paypal-payments' ),
				'type'         => 'checkbox',
				'label'        => __( 'Enable tagline', 'woocommerce-paypal-payments' ),
				'default'      => true,
				'desc_tip'     => true,
				'description'  => __(
					'Add the tagline. This line will only show up, if you select a horizontal layout.',
					'woocommerce-paypal-payments'
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_cart_label'              => array(
				'title'        => __( 'Button Label', 'woocommerce-paypal-payments' ),
				'type'         => 'select',
				'class'        => array(),
				'input_class'  => array( 'wc-enhanced-select' ),
				'default'      => apply_filters( 'woocommerce_paypal_payments_button_cart_label_default', 'paypal' ),
				'desc_tip'     => true,
				'description'  => __(
					'This controls the label on the primary button.',
					'woocommerce-paypal-payments'
				),
				'options'      => array(
					'paypal'   => __( 'PayPal', 'woocommerce-paypal-payments' ),
					'checkout' => __( 'PayPal Checkout', 'woocommerce-paypal-payments' ),
					'buynow'   => __( 'PayPal Buy Now', 'woocommerce-paypal-payments' ),
					'pay'      => __( 'Pay with PayPal', 'woocommerce-paypal-payments' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_cart_color'              => array(
				'title'        => __( 'Color', 'woocommerce-paypal-payments' ),
				'type'         => 'select',
				'class'        => array(),
				'input_class'  => array( 'wc-enhanced-select' ),
				'default'      => 'gold',
				'desc_tip'     => true,
				'description'  => __(
					'Controls the background color of the primary button. Use "Gold" to leverage PayPal\'s recognition and preference, or change it to match your site design or aesthetic.',
					'woocommerce-paypal-payments'
				),
				'options'      => array(
					'gold'   => __( 'Gold (Recommended)', 'woocommerce-paypal-payments' ),
					'blue'   => __( 'Blue', 'woocommerce-paypal-payments' ),
					'silver' => __( 'Silver', 'woocommerce-paypal-payments' ),
					'black'  => __( 'Black', 'woocommerce-paypal-payments' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_cart_shape'              => array(
				'title'        => __( 'Shape', 'woocommerce-paypal-payments' ),
				'type'         => 'select',
				'class'        => array(),
				'input_class'  => array( 'wc-enhanced-select' ),
				'default'      => 'rect',
				'desc_tip'     => true,
				'description'  => __(
					'The pill-shaped button\'s unique and powerful shape signifies PayPal in people\'s minds. Use the rectangular button as an alternative when pill-shaped buttons might pose design challenges.',
					'woocommerce-paypal-payments'
				),
				'options'      => array(
					'pill' => __( 'Pill', 'woocommerce-paypal-payments' ),
					'rect' => __( 'Rectangle', 'woocommerce-paypal-payments' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),

			'message_cart_heading'           => array(
				'heading'      => __( 'Pay Later on Cart', 'woocommerce-paypal-payments' ),
				'type'         => 'ppcp-heading',
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array( 'messages' ),
				'gateway'      => 'paypal',
				'description'  => str_replace( '<a>', '<a href="' . $messages_disclaimers->link_for_country() . '" target="_blank">', __( 'Displays Pay Later messaging for available offers. Restrictions apply. <a>Click here to learn more</a>. Pay Later button will show for eligible buyers and PayPal determines eligibility.', 'woocommerce-paypal-payments' ) ),
				'class'        => array( 'ppcp-subheading' ),
			),
			'message_cart_enabled'           => array(
				'title'        => __( 'Enable message on Cart', 'woocommerce-paypal-payments' ),
				'type'         => 'checkbox',
				'label'        => __( 'Enable on Cart', 'woocommerce-paypal-payments' ),
				'default'      => true,
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array( 'messages' ),
				'gateway'      => 'paypal',
			),
			'message_cart_layout'            => array(
				'title'        => __( 'Pay Later Messaging layout', 'woocommerce-paypal-payments' ),
				'type'         => 'select',
				'class'        => array(),
				'input_class'  => array( 'wc-enhanced-select' ),
				'default'      => 'text',
				'desc_tip'     => true,
				'description'  => __(
					'The layout of the message.',
					'woocommerce-paypal-payments'
				),
				'options'      => array(
					'text' => __( 'Text', 'woocommerce-paypal-payments' ),
					'flex' => __( 'Flex', 'woocommerce-paypal-payments' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array( 'messages' ),
				'gateway'      => 'paypal',
			),
			'message_cart_logo'              => array(
				'title'        => __( 'Pay Later Messaging logo', 'woocommerce-paypal-payments' ),
				'type'         => 'select',
				'class'        => array(),
				'input_class'  => array( 'wc-enhanced-select' ),
				'default'      => 'primary',
				'desc_tip'     => true,
				'description'  => __(
					'What logo the text message contains. Only applicable, when the layout style Text is used.',
					'woocommerce-paypal-payments'
				),
				'options'      => array(
					'primary'     => __( 'Primary', 'woocommerce-paypal-payments' ),
					'alternative' => __( 'Alternative', 'woocommerce-paypal-payments' ),
					'inline'      => __( 'Inline', 'woocommerce-paypal-payments' ),
					'none'        => __( 'None', 'woocommerce-paypal-payments' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array( 'messages' ),
				'gateway'      => 'paypal',
			),
			'message_cart_position'          => array(
				'title'        => __( 'Pay Later Messaging logo position', 'woocommerce-paypal-payments' ),
				'type'         => 'select',
				'class'        => array(),
				'input_class'  => array( 'wc-enhanced-select' ),
				'default'      => 'left',
				'desc_tip'     => true,
				'description'  => __(
					'The position of the logo. Only applicable, when the layout style Text is used.',
					'woocommerce-paypal-payments'
				),
				'options'      => array(
					'left'  => __( 'Left', 'woocommerce-paypal-payments' ),
					'right' => __( 'Right', 'woocommerce-paypal-payments' ),
					'top'   => __( 'Top', 'woocommerce-paypal-payments' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array( 'messages' ),
				'gateway'      => 'paypal',
			),
			'message_cart_color'             => array(
				'title'        => __( 'Pay Later Messaging text color', 'woocommerce-paypal-payments' ),
				'type'         => 'select',
				'class'        => array(),
				'input_class'  => array( 'wc-enhanced-select' ),
				'default'      => 'black',
				'desc_tip'     => true,
				'description'  => __(
					'The color of the text. Only applicable, when the layout style Text is used.',
					'woocommerce-paypal-payments'
				),
				'options'      => array(
					'black'      => __( 'Black', 'woocommerce-paypal-payments' ),
					'white'      => __( 'White', 'woocommerce-paypal-payments' ),
					'monochrome' => __( 'Monochrome', 'woocommerce-paypal-payments' ),
					'grayscale'  => __( 'Grayscale', 'woocommerce-paypal-payments' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array( 'messages' ),
				'gateway'      => 'paypal',
			),
			'message_cart_flex_color'        => array(
				'title'        => __( 'Pay Later Messaging color', 'woocommerce-paypal-payments' ),
				'type'         => 'select',
				'class'        => array(),
				'input_class'  => array( 'wc-enhanced-select' ),
				'default'      => 'blue',
				'desc_tip'     => true,
				'description'  => __(
					'The color of the text. Only applicable, when the layout style Flex is used.',
					'woocommerce-paypal-payments'
				),
				'options'      => array(
					'blue'            => __( 'Blue', 'woocommerce-paypal-payments' ),
					'black'           => __( 'Black', 'woocommerce-paypal-payments' ),
					'white'           => __( 'White', 'woocommerce-paypal-payments' ),
					'white-no-border' => __( 'White no border', 'woocommerce-paypal-payments' ),
					'gray'            => __( 'Gray', 'woocommerce-paypal-payments' ),
					'monochrome'      => __( 'Monochrome', 'woocommerce-paypal-payments' ),
					'grayscale'       => __( 'Grayscale', 'woocommerce-paypal-payments' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array( 'messages' ),
				'gateway'      => 'paypal',
			),
			'message_cart_flex_ratio'        => array(
				'title'        => __( 'Pay Later Messaging ratio', 'woocommerce-paypal-payments' ),
				'type'         => 'select',
				'class'        => array(),
				'input_class'  => array( 'wc-enhanced-select' ),
				'default'      => '1x1',
				'desc_tip'     => true,
				'description'  => __(
					'The width/height ratio of the banner. Only applicable, when the layout style Flex is used.',
					'woocommerce-paypal-payments'
				),
				'options'      => array(
					'1x1'  => __( '1x1', 'woocommerce-paypal-payments' ),
					'1x4'  => __( '1x4', 'woocommerce-paypal-payments' ),
					'8x1'  => __( '8x1', 'woocommerce-paypal-payments' ),
					'20x1' => __( '20x1', 'woocommerce-paypal-payments' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array( 'messages' ),
				'gateway'      => 'paypal',
			),

			// Mini cart settings.
			'button_mini-cart_heading'       => array(
				'heading'      => __( 'Mini Cart', 'woocommerce-paypal-payments' ),
				'type'         => 'ppcp-heading',
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
				'description'  => __( 'Customize the appearance of PayPal Checkout on the Mini Cart.', 'woocommerce-paypal-payments' ),
			),
			'button_mini-cart_enabled'       => array(
				'title'        => __( 'Buttons on Mini Cart', 'woocommerce-paypal-payments' ),
				'type'         => 'checkbox',
				'label'        => __( 'Enable on Mini Cart', 'woocommerce-paypal-payments' ),
				'default'      => true,
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_mini-cart_layout'        => array(
				'title'        => __( 'Button Layout', 'woocommerce-paypal-payments' ),
				'type'         => 'select',
				'class'        => array(),
				'input_class'  => array( 'wc-enhanced-select' ),
				'default'      => 'vertical',
				'desc_tip'     => true,
				'description'  => __(
					'If additional funding sources are available to the buyer through PayPal, such as Venmo, then multiple buttons are displayed in the space provided. Choose "vertical" for a dynamic list of alternative and local payment options, or "horizontal" when space is limited.',
					'woocommerce-paypal-payments'
				),
				'options'      => array(
					'vertical'   => __( 'Vertical', 'woocommerce-paypal-payments' ),
					'horizontal' => __( 'Horizontal', 'woocommerce-paypal-payments' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_mini-cart_tagline'       => array(
				'title'        => __( 'Tagline', 'woocommerce-paypal-payments' ),
				'type'         => 'checkbox',
				'label'        => __( 'Enable tagline', 'woocommerce-paypal-payments' ),
				'default'      => false,
				'desc_tip'     => true,
				'description'  => __(
					'Add the tagline. This line will only show up, if you select a horizontal layout.',
					'woocommerce-paypal-payments'
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_mini-cart_label'         => array(
				'title'        => __( 'Button Label', 'woocommerce-paypal-payments' ),
				'type'         => 'select',
				'class'        => array(),
				'input_class'  => array( 'wc-enhanced-select' ),
				'default'      => apply_filters( 'woocommerce_paypal_payments_button_mini_cart_label_default', 'paypal' ),
				'desc_tip'     => true,
				'description'  => __(
					'This controls the label on the primary button.',
					'woocommerce-paypal-payments'
				),
				'options'      => array(
					'paypal'   => __( 'PayPal', 'woocommerce-paypal-payments' ),
					'checkout' => __( 'PayPal Checkout', 'woocommerce-paypal-payments' ),
					'buynow'   => __( 'PayPal Buy Now', 'woocommerce-paypal-payments' ),
					'pay'      => __( 'Pay with PayPal', 'woocommerce-paypal-payments' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_mini-cart_color'         => array(
				'title'        => __( 'Color', 'woocommerce-paypal-payments' ),
				'type'         => 'select',
				'class'        => array(),
				'input_class'  => array( 'wc-enhanced-select' ),
				'default'      => 'gold',
				'desc_tip'     => true,
				'description'  => __(
					'Controls the background color of the primary button. Use "Gold" to leverage PayPal\'s recognition and preference, or change it to match your site design or aesthetic.',
					'woocommerce-paypal-payments'
				),
				'options'      => array(
					'gold'   => __( 'Gold (Recommended)', 'woocommerce-paypal-payments' ),
					'blue'   => __( 'Blue', 'woocommerce-paypal-payments' ),
					'silver' => __( 'Silver', 'woocommerce-paypal-payments' ),
					'black'  => __( 'Black', 'woocommerce-paypal-payments' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_mini-cart_shape'         => array(
				'title'        => __( 'Shape', 'woocommerce-paypal-payments' ),
				'type'         => 'select',
				'class'        => array(),
				'input_class'  => array( 'wc-enhanced-select' ),
				'default'      => 'rect',
				'desc_tip'     => true,
				'description'  => __(
					'The pill-shaped button\'s unique and powerful shape signifies PayPal in people\'s minds. Use the rectangular button as an alternative when pill-shaped buttons might pose design challenges.',
					'woocommerce-paypal-payments'
				),
				'options'      => array(
					'pill' => __( 'Pill', 'woocommerce-paypal-payments' ),
					'rect' => __( 'Rectangle', 'woocommerce-paypal-payments' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_mini-cart_height'        => array(
				'title'        => __( 'Button Height', 'woocommerce-paypal-payments' ),
				'type'         => 'number',
				'default'      => '35',
				'desc_tip'     => true,
				'description'  => __( 'Add a value from 25 to 55.', 'woocommerce-paypal-payments' ),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),

			'disable_cards'                  => array(
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
			'card_icons'                     => array(
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
			'3d_secure_heading'              => array(
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
			'3d_secure_contingency'          => array(
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
				'default'      => 'SCA_WHEN_REQUIRED',
				'desc_tip'     => true,
				'options'      => array(
					'NO_3D_SECURE'      => __( 'No 3D Secure (transaction will be denied if 3D Secure is required)', 'woocommerce-paypal-payments' ),
					'SCA_WHEN_REQUIRED' => __( '3D Secure when required', 'woocommerce-paypal-payments' ),
					'3D_SECURE'         => __( 'Always trigger 3D Secure', 'woocommerce-paypal-payments' ),
				),
				'screens'      => array(
					State::STATE_ONBOARDED,
				),
				'requirements' => array(
					'dcc',
				),
				'gateway'      => 'dcc',
			),
		);
		if ( ! defined( 'PPCP_FLAG_SUBSCRIPTION' ) || ! PPCP_FLAG_SUBSCRIPTION ) {
			unset( $fields['vault_enabled'] );
		}

		if ( State::STATE_ONBOARDED === $state->production_state() ) {
			unset( $fields['ppcp_onboarding_production'] );
		} else {
			unset( $fields['ppcp_disconnect_production'] );
		}
		if ( State::STATE_ONBOARDED === $state->sandbox_state() ) {
			unset( $fields['ppcp_onboarding_sandbox'] );
		} else {
			unset( $fields['ppcp_disconnect_sandbox'] );
		}

		/**
		 * Depending on your store location, some credit cards can't be used.
		 * Here, we filter them out.
		 *
		 * The DCC Applies object.
		 *
		 * @var DccApplies $dcc_applies
		 */
		$dcc_applies = $container->get( 'api.helpers.dccapplies' );
		$card_options = $fields['disable_cards']['options'];
		foreach ( $card_options as $card => $label ) {
			if ( $dcc_applies->can_process_card( $card ) ) {
				continue;
			}
			unset( $card_options[ $card ] );
		}
		$fields['disable_cards']['options'] = $card_options;
		$fields['card_icons']['options'] = $card_options;

		/**
		 * Display vault message on Pay Later label if vault is enabled.
		 */
		$settings = $container->get( 'wcgateway.settings' );
		if ( $settings->has( 'vault_enabled' ) && $settings->get( 'vault_enabled' ) ) {
			$message = __( "You have PayPal vaulting enabled, that's why Pay Later Messaging options are unavailable now. You cannot use both features at the same time.", 'woocommerce-paypal-payments' );
			$fields['message_enabled']['label'] = $message;
			$fields['message_product_enabled']['label'] = $message;
			$fields['message_cart_enabled']['label'] = $message;
		}

		return $fields;
	},

	'wcgateway.checkout.address-preset'            => static function( $container ): CheckoutPayPalAddressPreset {

		return new CheckoutPayPalAddressPreset(
			$container->get( 'session.handler' )
		);
	},
	'wcgateway.url'                                => static function ( $container ): string {
		return plugins_url(
			$container->get( 'wcgateway.relative-path' ),
			dirname( __FILE__, 3 ) . '/woocommerce-paypal-payments.php'
		);
	},
	'wcgateway.relative-path'                      => static function( $container ): string {
		return 'modules/ppcp-wc-gateway/';
	},
	'wcgateway.absolute-path'                      => static function( $container ): string {
		return plugin_dir_path(
			dirname( __FILE__, 3 ) . '/woocommerce-paypal-payments.php'
		) .
			$container->get( 'wcgateway.relative-path' );
	},
	'wcgateway.endpoint.return-url'                => static function ( $container ) : ReturnUrlEndpoint {
		$gateway  = $container->get( 'wcgateway.paypal-gateway' );
		$endpoint = $container->get( 'api.endpoint.order' );
		$prefix   = $container->get( 'api.prefix' );
		return new ReturnUrlEndpoint(
			$gateway,
			$endpoint,
			$prefix
		);
	},

	'wcgateway.transaction-url-sandbox'            => static function ( $container ): string {
		return 'https://www.sandbox.paypal.com/cgi-bin/webscr?cmd=_view-a-trans&id=%s';
	},

	'wcgateway.transaction-url-live'               => static function ( $container ): string {
		return 'https://www.paypal.com/cgi-bin/webscr?cmd=_view-a-trans&id=%s';
	},

	'wcgateway.transaction-url-provider'           => static function ( $container ): TransactionUrlProvider {
		$sandbox_url_base = $container->get( 'wcgateway.transaction-url-sandbox' );
		$live_url_base    = $container->get( 'wcgateway.transaction-url-live' );

		return new TransactionUrlProvider( $sandbox_url_base, $live_url_base );
	},

	'wcgateway.helper.dcc-product-status'          => static function ( $container ) : DccProductStatus {

		$settings         = $container->get( 'wcgateway.settings' );
		$partner_endpoint = $container->get( 'api.endpoint.partners' );
		return new DccProductStatus( $settings, $partner_endpoint );
	},

	'button.helper.messages-disclaimers'           => static function ( $container ): MessagesDisclaimers {
		return new MessagesDisclaimers();
	},
);
