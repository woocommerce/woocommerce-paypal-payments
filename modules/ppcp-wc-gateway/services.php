<?php
/**
 * The services of the Gateway module.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway;

use Dhii\Data\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Entity\ApplicationContext;
use WooCommerce\PayPalCommerce\ApiClient\Helper\Cache;
use WooCommerce\PayPalCommerce\ApiClient\Helper\DccApplies;
use WooCommerce\PayPalCommerce\Onboarding\State;
use WooCommerce\PayPalCommerce\WcGateway\Admin\OrderTablePaymentStatusColumn;
use WooCommerce\PayPalCommerce\WcGateway\Admin\PaymentStatusOrderDetail;
use WooCommerce\PayPalCommerce\WcGateway\Admin\RenderAuthorizeAction;
use WooCommerce\PayPalCommerce\WcGateway\Checkout\CheckoutPayPalAddressPreset;
use WooCommerce\PayPalCommerce\WcGateway\Checkout\DisableGateways;
use WooCommerce\PayPalCommerce\WcGateway\Endpoint\ReturnUrlEndpoint;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use Woocommerce\PayPalCommerce\WcGateway\Helper\DccProductStatus;
use WooCommerce\PayPalCommerce\WcGateway\Notice\AuthorizeOrderActionNotice;
use WooCommerce\PayPalCommerce\WcGateway\Notice\ConnectAdminNotice;
use WooCommerce\PayPalCommerce\WcGateway\Processor\AuthorizedPaymentsProcessor;
use WooCommerce\PayPalCommerce\WcGateway\Processor\OrderProcessor;
use WooCommerce\PayPalCommerce\WcGateway\Processor\RefundProcessor;
use WooCommerce\PayPalCommerce\WcGateway\Settings\SectionsRenderer;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
use WooCommerce\PayPalCommerce\WcGateway\Settings\SettingsListener;
use WooCommerce\PayPalCommerce\WcGateway\Settings\SettingsRenderer;
use WpOop\TransientCache\CachePoolFactory;

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

		return new PayPalGateway(
			$settings_renderer,
			$order_processor,
			$authorized_payments,
			$notice,
			$settings,
			$session_handler,
			$refund_processor,
			$state
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
		return new CreditCardGateway(
			$settings_renderer,
			$order_processor,
			$authorized_payments,
			$notice,
			$settings,
			$module_url,
			$session_handler,
			$refund_processor,
			$state
		);
	},
	'wcgateway.disabler'                           => static function ( $container ): DisableGateways {
		$session_handler = $container->get( 'session.handler' );
		$settings       = $container->get( 'wcgateway.settings' );
		return new DisableGateways( $session_handler, $settings );
	},
	'wcgateway.settings'                           => static function ( $container ): Settings {
		return new Settings();
	},
	'wcgateway.notice.connect'                     => static function ( $container ): ConnectAdminNotice {
		$state    = $container->get( 'onboarding.state' );
		$settings = $container->get( 'wcgateway.settings' );
		return new ConnectAdminNotice( $state, $settings );
	},
	'wcgateway.notice.authorize-order-action'      =>
		static function ( $container ): AuthorizeOrderActionNotice {
			return new AuthorizeOrderActionNotice();
		},
	'wcgateway.settings.sections-renderer'         => static function ( $container ): SectionsRenderer {
		return new SectionsRenderer();
	},
	'wcgateway.settings.render'                    => static function ( $container ): SettingsRenderer {
		$settings      = $container->get( 'wcgateway.settings' );
		$state         = $container->get( 'onboarding.state' );
		$fields        = $container->get( 'wcgateway.settings.fields' );
		$dcc_applies    = $container->get( 'api.helpers.dccapplies' );
		$messages_apply = $container->get( 'button.helper.messages-apply' );
		$dcc_product_status = $container->get( 'wcgateway.helper.dcc-product-status' );
		return new SettingsRenderer(
			$settings,
			$state,
			$fields,
			$dcc_applies,
			$messages_apply,
			$dcc_product_status
		);
	},
	'wcgateway.settings.listener'                  => static function ( $container ): SettingsListener {
		$settings         = $container->get( 'wcgateway.settings' );
		$fields           = $container->get( 'wcgateway.settings.fields' );
		$webhook_registrar = $container->get( 'webhook.registrar' );
		$state            = $container->get( 'onboarding.state' );
		$cache = new Cache( 'ppcp-paypal-bearer' );
		return new SettingsListener( $settings, $fields, $webhook_registrar, $cache, $state );
	},
	'wcgateway.order-processor'                    => static function ( $container ): OrderProcessor {

		$session_handler              = $container->get( 'session.handler' );
		$cart_repository              = $container->get( 'api.repository.cart' );
		$order_endpoint               = $container->get( 'api.endpoint.order' );
		$payments_endpoint            = $container->get( 'api.endpoint.payments' );
		$order_factory                = $container->get( 'api.factory.order' );
		$threed_secure                = $container->get( 'button.helper.three-d-secure' );
		$authorized_payments_processor = $container->get( 'wcgateway.processor.authorized-payments' );
		$settings                    = $container->get( 'wcgateway.settings' );
		return new OrderProcessor(
			$session_handler,
			$cart_repository,
			$order_endpoint,
			$payments_endpoint,
			$order_factory,
			$threed_secure,
			$authorized_payments_processor,
			$settings
		);
	},
	'wcgateway.processor.refunds'                  => static function ( $container ): RefundProcessor {
		$order_endpoint    = $container->get( 'api.endpoint.order' );
		$payments_endpoint    = $container->get( 'api.endpoint.payments' );
		return new RefundProcessor( $order_endpoint, $payments_endpoint );
	},
	'wcgateway.processor.authorized-payments'      => static function ( $container ): AuthorizedPaymentsProcessor {
		$order_endpoint    = $container->get( 'api.endpoint.order' );
		$payments_endpoint = $container->get( 'api.endpoint.payments' );
		return new AuthorizedPaymentsProcessor( $order_endpoint, $payments_endpoint );
	},
	'wcgateway.admin.render-authorize-action'      => static function ( $container ): RenderAuthorizeAction {

		return new RenderAuthorizeAction();
	},
	'wcgateway.admin.order-payment-status'         => static function ( $container ): PaymentStatusOrderDetail {
		return new PaymentStatusOrderDetail();
	},
	'wcgateway.admin.orders-payment-status-column' => static function ( $container ): OrderTablePaymentStatusColumn {
		$settings = $container->get( 'wcgateway.settings' );
		return new OrderTablePaymentStatusColumn( $settings );
	},

	'wcgateway.settings.fields'                    => static function ( $container ): array {

		$state = $container->get( 'onboarding.state' );
		/**
		 * The state.
		 *
		 * @var State $state
		 */

		$settings     = $container->get( 'wcgateway.settings' );

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
				'label'        => __( 'Enable vaulting', 'woocommerce-paypal-payments' ),
				'description'  => __( 'Enables you to store payment tokens for subscriptions.', 'woocommerce-paypal-payments' ),
				'default'      => true,
				'screens'      => array(
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
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
				'description'  => __( 'If you use your PayPal account with more than one installation, please use a distinct prefix to seperate those installations. Please do not use numbers in your prefix.', 'woocommerce-paypal-payments' ),
				'default'      => 'WC-',
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
				'default'      => 'paypal',
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
				'description'  => str_replace( '<a>', '<a href="https://www.paypal.com/us/business/buy-now-pay-later">', __( 'Customize the appearance of <a>Pay Later messages</a> on checkout to promote special financing offers, which help increase sales.', 'woocommerce-paypal-payments' ) ),
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
				'default'      => 'paypal',
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
				'description'  => str_replace( '<a>', '<a href="https://www.paypal.com/us/business/buy-now-pay-later">', __( 'Customize the appearance of <a>Pay Later messages</a> on product pages to promote special financing offers, which help increase sales.', 'woocommerce-paypal-payments' ) ),
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
				'default'      => 'paypal',
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
				'description'  => str_replace( '<a>', '<a href="https://www.paypal.com/us/business/buy-now-pay-later">', __( 'Customize the appearance of <a>Pay Later messages</a> on your cart page to promote special financing offers, which help increase sales.', 'woocommerce-paypal-payments' ) ),
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
				'default'      => 'paypal',
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
		 * Disable card for UK.
		 */
		$region  = wc_get_base_location();
		$country = $region['country'];
		if ( 'GB' === $country ) {
			unset( $fields['disable_funding']['options']['card'] );
		}

		/**
		 * Set Pay in 3 heading and description for UK.
		 */
		if ( 'GB' === $country ) {
			$fields['message_heading']['heading'] = __( 'Pay Later Messaging on Checkout', 'woocommerce-paypal-payments' );
			$fields['message_heading']['description'] = __( 'Display pay later messaging on your site for offers like Pay in 3, which lets customers pay with 3 interest-free monthly payments. We’ll show messages on your site to promote this feature for you. You may not promote pay later offers with any other content, marketing, or materials.', 'woocommerce-paypal-payments' );

			$fields['message_product_heading']['heading'] = __( 'Pay Later Messaging on Single Product Page', 'woocommerce-paypal-payments' );
			$fields['message_product_heading']['description'] = __( 'Display pay later messaging on your site for offers like Pay in 3, which lets customers pay with 3 interest-free monthly payments. We’ll show messages on your site to promote this feature for you. You may not promote pay later offers with any other content, marketing, or materials.', 'woocommerce-paypal-payments' );

			$fields['message_cart_heading']['heading'] = __( 'Pay Later Messaging on Cart', 'woocommerce-paypal-payments' );
			$fields['message_cart_heading']['description'] = __( 'Display pay later messaging on your site for offers like Pay in 3, which lets customers pay with 3 interest-free monthly payments. We’ll show messages on your site to promote this feature for you. You may not promote pay later offers with any other content, marketing, or materials.', 'woocommerce-paypal-payments' );
		}

		/**
		 * Set Pay Later link for DE
		 */
		if ( 'DE' === $country ) {
			$fields['message_heading']['description'] = str_replace( '<a>', '<a href="https://www.paypal.com/de/webapps/mpp/installments">', __( 'Customize the appearance of <a>Pay Later messages</a> on checkout to promote special financing offers, which help increase sales.', 'woocommerce-paypal-payments' ) );
			$fields['message_product_heading']['description'] = str_replace( '<a>', '<a href="https://www.paypal.com/de/webapps/mpp/installments">', __( 'Customize the appearance of <a>Pay Later messages</a> on checkout to promote special financing offers, which help increase sales.', 'woocommerce-paypal-payments' ) );
			$fields['message_cart_heading']['description'] = str_replace( '<a>', '<a href="https://www.paypal.com/de/webapps/mpp/installments">', __( 'Customize the appearance of <a>Pay Later messages</a> on checkout to promote special financing offers, which help increase sales.', 'woocommerce-paypal-payments' ) );
		}

		$dcc_applies = $container->get( 'api.helpers.dccapplies' );
		/**
		 * Depending on your store location, some credit cards can't be used.
		 * Here, we filter them out.
		 *
		 * The DCC Applies object.
		 *
		 * @var DccApplies $dcc_applies
		 */
		$card_options = $fields['disable_cards']['options'];
		foreach ( $card_options as $card => $label ) {
			if ( $dcc_applies->can_process_card( $card ) ) {
				continue;
			}
			unset( $card_options[ $card ] );
		}
		$fields['disable_cards']['options'] = $card_options;
		$fields['card_icons']['options'] = $card_options;
		return $fields;
	},

	'wcgateway.checkout.address-preset'            => static function( $container ): CheckoutPayPalAddressPreset {

		return new CheckoutPayPalAddressPreset(
			$container->get( 'session.handler' )
		);
	},
	'wcgateway.url'                                => static function ( $container ): string {
		return plugins_url(
			'/modules/ppcp-wc-gateway/',
			dirname( __FILE__, 3 ) . '/woocommerce-paypal-payments.php'
		);
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
	'wcgateway.helper.dcc-product-status'          => static function ( $container ) : DccProductStatus {

		$settings         = $container->get( 'wcgateway.settings' );
		$partner_endpoint = $container->get( 'api.endpoint.partners' );
		return new DccProductStatus( $settings, $partner_endpoint );
	},
);
