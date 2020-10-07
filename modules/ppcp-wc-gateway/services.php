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
				'title'        => __( 'Sandbox', 'paypal-payments-for-woocommerce' ),
				'type'         => 'checkbox',
				'label'        => __( 'To test your WooCommerce installation, you can use the sandbox mode.', 'paypal-payments-for-woocommerce' ),
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
				'heading'      => __( 'API', 'paypal-payments-for-woocommerce' ),
				'type'         => 'ppcp-heading',
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'ppcp_onboarding_production'     => array(
				'title'        => __( 'Connect to PayPal', 'paypal-payments-for-woocommerce' ),
				'type'         => 'ppcp_onboarding',
				'screens'      => array(
					State::STATE_START,
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'env'          => 'production',
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'ppcp_disconnect_production'     => array(
				'title'        => __( 'Disconnect from PayPal', 'paypal-payments-for-woocommerce' ),
				'type'         => 'ppcp-text',
				'classes'      => array( State::STATE_ONBOARDED === $state->production_state() ? 'onboarded' : '' ),
				'text'         => '<button type="button" class="button ppcp-disconnect production">' . esc_html__( 'Disconnect', 'paypal-payments-for-woocommerce' ) . '</button>',
				'screens'      => array(
					State::STATE_START,
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'env'          => 'production',
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'production_toggle_manual_input' => array(
				'type'         => 'ppcp-text',
				'title'        => __( 'Manual mode', 'paypal-payments-for-woocommerce' ),
				'classes'      => array( State::STATE_ONBOARDED === $state->production_state() ? 'onboarded' : '' ),
				'text'         => '<button type="button" id="ppcp[production_toggle_manual_input]" class="production-toggle">' . __( 'Toggle to manual credential input', 'paypal-payments-for-woocommerce' ) . '</button>',
				'screens'      => array(
					State::STATE_START,
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'merchant_email_production'      => array(
				'title'        => __( 'Live Email address', 'paypal-payments-for-woocommerce' ),
				'classes'      => array( State::STATE_ONBOARDED === $state->production_state() ? 'onboarded' : '' ),
				'type'         => 'text',
				'required'     => true,
				'desc_tip'     => true,
				'description'  => __( 'The email address of your PayPal account.', 'paypal-payments-for-woocommerce' ),
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
				'title'        => __( 'Live Merchant Id', 'paypal-payments-for-woocommerce' ),
				'classes'      => array( State::STATE_ONBOARDED === $state->production_state() ? 'onboarded' : '' ),
				'type'         => 'ppcp-text-input',
				'desc_tip'     => true,
				'description'  => __( 'The merchant id of your account ', 'paypal-payments-for-woocommerce' ),
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
				'title'        => __( 'Live Client Id', 'paypal-payments-for-woocommerce' ),
				'classes'      => array( State::STATE_ONBOARDED === $state->production_state() ? 'onboarded' : '' ),
				'type'         => 'ppcp-text-input',
				'desc_tip'     => true,
				'description'  => __( 'The client id of your api ', 'paypal-payments-for-woocommerce' ),
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
				'title'        => __( 'Live Secret Key', 'paypal-payments-for-woocommerce' ),
				'classes'      => array( State::STATE_ONBOARDED === $state->production_state() ? 'onboarded' : '' ),
				'type'         => 'ppcp-password',
				'desc_tip'     => true,
				'description'  => __( 'The secret key of your api', 'paypal-payments-for-woocommerce' ),
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
				'heading'      => __( 'Sandbox API', 'paypal-payments-for-woocommerce' ),
				'type'         => 'ppcp-heading',
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'ppcp_onboarding_sandbox'        => array(
				'title'        => __( 'Connect to PayPal', 'paypal-payments-for-woocommerce' ),
				'type'         => 'ppcp_onboarding',
				'screens'      => array(
					State::STATE_START,
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'env'          => 'sandbox',
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'ppcp_disconnect_sandbox'        => array(
				'title'        => __( 'Disconnect from PayPal Sandbox', 'paypal-payments-for-woocommerce' ),
				'type'         => 'ppcp-text',
				'classes'      => array( State::STATE_ONBOARDED === $state->sandbox_state() ? 'onboarded' : '' ),
				'text'         => '<button type="button" class="button ppcp-disconnect sandbox">' . esc_html__( 'Disconnect', 'paypal-payments-for-woocommerce' ) . '</button>',
				'screens'      => array(
					State::STATE_START,
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'env'          => 'production',
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'sandbox_toggle_manual_input'    => array(
				'type'         => 'ppcp-text',
				'title'        => __( 'Manual mode', 'paypal-payments-for-woocommerce' ),
				'classes'      => array( State::STATE_ONBOARDED === $state->sandbox_state() ? 'onboarded' : '' ),
				'text'         => '<button type="button" id="ppcp[sandbox_toggle_manual_input]" class="sandbox-toggle">' . __( 'Toggle to manual credential input', 'paypal-payments-for-woocommerce' ) . '</button>',
				'screens'      => array(
					State::STATE_START,
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'merchant_email_sandbox'         => array(
				'title'        => __( 'Sandbox Email address', 'paypal-payments-for-woocommerce' ),
				'classes'      => array( State::STATE_ONBOARDED === $state->sandbox_state() ? 'onboarded' : '' ),
				'type'         => 'text',
				'required'     => true,
				'desc_tip'     => true,
				'description'  => __( 'The email address of your PayPal account.', 'paypal-payments-for-woocommerce' ),
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
				'title'        => __( 'Sandbox Merchant Id', 'paypal-payments-for-woocommerce' ),
				'classes'      => array( State::STATE_ONBOARDED === $state->sandbox_state() ? 'onboarded' : '' ),
				'type'         => 'ppcp-text-input',
				'desc_tip'     => true,
				'description'  => __( 'The merchant id of your account ', 'paypal-payments-for-woocommerce' ),
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
				'title'        => __( 'Sandbox Client Id', 'paypal-payments-for-woocommerce' ),
				'classes'      => array( State::STATE_ONBOARDED === $state->sandbox_state() ? 'onboarded' : '' ),
				'type'         => 'ppcp-text-input',
				'desc_tip'     => true,
				'description'  => __( 'The client id of your api ', 'paypal-payments-for-woocommerce' ),
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
				'title'        => __( 'Sandbox Secret Key', 'paypal-payments-for-woocommerce' ),
				'classes'      => array( State::STATE_ONBOARDED === $state->sandbox_state() ? 'onboarded' : '' ),
				'type'         => 'ppcp-password',
				'desc_tip'     => true,
				'description'  => __( 'The secret key of your api', 'paypal-payments-for-woocommerce' ),
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
				'heading'      => __( 'PayPal Checkout Plugin Settings', 'paypal-payments-for-woocommerce' ),
				'type'         => 'ppcp-heading',
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'title'                          => array(
				'title'        => __( 'Title', 'paypal-payments-for-woocommerce' ),
				'type'         => 'text',
				'description'  => __(
					'This controls the title which the user sees during checkout.',
					'paypal-payments-for-woocommerce'
				),
				'default'      => __( 'PayPal', 'paypal-payments-for-woocommerce' ),
				'desc_tip'     => true,
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'dcc_enabled'                    => array(
				'title'        => __( 'Enable/Disable', 'paypal-payments-for-woocommerce' ),
				'desc_tip'     => true,
				'description'  => __( 'Once enabled, the Credit Card option will show up in the checkout.', 'paypal-payments-for-woocommerce' ),
				'label'        => __( 'Enable PayPal Card Processing', 'paypal-payments-for-woocommerce' ),
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
				'title'        => __( 'Title', 'paypal-payments-for-woocommerce' ),
				'type'         => 'text',
				'description'  => __(
					'This controls the title which the user sees during checkout.',
					'paypal-payments-for-woocommerce'
				),
				'default'      => __( 'Credit Cards', 'paypal-payments-for-woocommerce' ),
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
				'title'        => __( 'Description', 'paypal-payments-for-woocommerce' ),
				'type'         => 'text',
				'desc_tip'     => true,
				'description'  => __(
					'This controls the description which the user sees during checkout.',
					'paypal-payments-for-woocommerce'
				),
				'default'      => __(
					'Pay via PayPal; you can pay with your credit card if you don\'t have a PayPal account.',
					'paypal-payments-for-woocommerce'
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'intent'                         => array(
				'title'        => __( 'Intent', 'paypal-payments-for-woocommerce' ),
				'type'         => 'select',
				'class'        => array( 'wc-enhanced-select' ),
				'default'      => 'capture',
				'desc_tip'     => true,
				'description'  => __(
					'The intent to either capture payment immediately or authorize a payment for an order after order creation.',
					'paypal-payments-for-woocommerce'
				),
				'options'      => array(
					'capture'   => __( 'Capture', 'paypal-payments-for-woocommerce' ),
					'authorize' => __( 'Authorize', 'paypal-payments-for-woocommerce' ),
				),
				'screens'      => array(
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'capture_for_virtual_only'       => array(
				'title'        => __( 'Capture Virtual-Only Orders ', 'paypal-payments-for-woocommerce' ),
				'type'         => 'checkbox',
				'default'      => false,
				'desc_tip'     => true,
				'description'  => __(
					'If the order contains exclusively virtual items, enable this to immediately capture, rather than authorize, the transaction.',
					'paypal-payments-for-woocommerce'
				),
				'label'        => __( 'Capture Virtual-Only Orders', 'paypal-payments-for-woocommerce' ),
				'screens'      => array(
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'payee_preferred'                => array(
				'title'        => __( 'Instant Payments ', 'paypal-payments-for-woocommerce' ),
				'type'         => 'checkbox',
				'default'      => false,
				'desc_tip'     => true,
				'description'  => __(
					'If you enable this setting, PayPal will be instructed not to allow the buyer to use funding sources that take additional time to complete (for example, eChecks). Instead, the buyer will be required to use an instant funding source, such as an instant transfer, a credit/debit card, or PayPal Credit.',
					'paypal-payments-for-woocommerce'
				),
				'label'        => __( 'Require Instant Payment', 'paypal-payments-for-woocommerce' ),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'brand_name'                     => array(
				'title'        => __( 'Brand Name', 'paypal-payments-for-woocommerce' ),
				'type'         => 'text',
				'default'      => get_bloginfo( 'name' ),
				'desc_tip'     => true,
				'description'  => __(
					'Control the name of your shop, customers will see in the PayPal process.',
					'paypal-payments-for-woocommerce'
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'landing_page'                   => array(
				'title'        => __( 'Landing Page', 'paypal-payments-for-woocommerce' ),
				'type'         => 'select',
				'class'        => array( 'wc-enhanced-select' ),
				'default'      => 'gold',
				'desc_tip'     => true,
				'description'  => __(
					'Type of PayPal page to display.',
					'paypal-payments-for-woocommerce'
				),
				'options'      => array(
					ApplicationContext::LANDING_PAGE_LOGIN => __( 'Login (PayPal account login)', 'paypal-payments-for-woocommerce' ),
					ApplicationContext::LANDING_PAGE_BILLING => __( 'Billing (Non-PayPal account)', 'paypal-payments-for-woocommerce' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'disable_funding'                => array(
				'title'        => __( 'Disable funding sources', 'paypal-payments-for-woocommerce' ),
				'type'         => 'ppcp-multiselect',
				'class'        => array( 'wc-enhanced-select' ),
				'default'      => array(),
				'desc_tip'     => true,
				'description'  => __(
					'By default all possible funding sources will be shown. You can disable some sources, if you wish.',
					'paypal-payments-for-woocommerce'
				),
				'options'      => array(
					'card'       => _x( 'Credit or debit cards', 'Name of payment method', 'paypal-payments-for-woocommerce' ),
					'credit'     => _x( 'PayPal Credit', 'Name of payment method', 'paypal-payments-for-woocommerce' ),
					'sepa'       => _x( 'SEPA-Lastschrift', 'Name of payment method', 'paypal-payments-for-woocommerce' ),
					'bancontact' => _x( 'Bancontact', 'Name of payment method', 'paypal-payments-for-woocommerce' ),
					'eps'        => _x( 'eps', 'Name of payment method', 'paypal-payments-for-woocommerce' ),
					'giropay'    => _x( 'giropay', 'Name of payment method', 'paypal-payments-for-woocommerce' ),
					'ideal'      => _x( 'iDEAL', 'Name of payment method', 'paypal-payments-for-woocommerce' ),
					'mybank'     => _x( 'MyBank', 'Name of payment method', 'paypal-payments-for-woocommerce' ),
					'p24'        => _x( 'Przelewy24', 'Name of payment method', 'paypal-payments-for-woocommerce' ),
					'sofort'     => _x( 'Sofort', 'Name of payment method', 'paypal-payments-for-woocommerce' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'vault_enabled'                  => array(
				'title'        => __( 'Vaulting', 'paypal-payments-for-woocommerce' ),
				'type'         => 'checkbox',
				'desc_tip'     => true,
				'label'        => __( 'Enable vaulting', 'paypal-payments-for-woocommerce' ),
				'description'  => __( 'Enables you to store payment tokens for subscriptions.', 'paypal-payments-for-woocommerce' ),
				'default'      => true,
				'screens'      => array(
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),

			'logging_enabled'                => array(
				'title'        => __( 'Logging', 'paypal-payments-for-woocommerce' ),
				'type'         => 'checkbox',
				'desc_tip'     => true,
				'label'        => __( 'Enable logging', 'paypal-payments-for-woocommerce' ),
				'description'  => __( 'Enable logging of unexpected behavior. This can also log private data and should only be enabled in a development or stage environment.', 'paypal-payments-for-woocommerce' ),
				'default'      => false,
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'prefix'                         => array(
				'title'        => __( 'Invoice prefix', 'paypal-payments-for-woocommerce' ),
				'type'         => 'text',
				'desc_tip'     => true,
				'description'  => __( 'If you use your PayPal account with more than one installation, please use a distinct prefix to seperate those installations. Please do not use numbers in your prefix.', 'paypal-payments-for-woocommerce' ),
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
				'heading'      => __( 'Checkout', 'paypal-payments-for-woocommerce' ),
				'type'         => 'ppcp-heading',
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_enabled'                 => array(
				'title'        => __( 'Enable buttons on Checkout', 'paypal-payments-for-woocommerce' ),
				'type'         => 'checkbox',
				'label'        => __( 'Enable on Checkout', 'paypal-payments-for-woocommerce' ),
				'default'      => true,
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_layout'                  => array(
				'title'        => __( 'Button Layout', 'paypal-payments-for-woocommerce' ),
				'type'         => 'select',
				'class'        => array( 'wc-enhanced-select' ),
				'default'      => 'vertical',
				'desc_tip'     => true,
				'description'  => __(
					'If additional funding sources are available to the buyer through PayPal, then multiple buttons are displayed in the space provided. Choose "vertical" for a dynamic list of alternative and local payment options, or "horizontal" when space is limited.',
					'paypal-payments-for-woocommerce'
				),
				'options'      => array(
					'vertical'   => __( 'Vertical', 'paypal-payments-for-woocommerce' ),
					'horizontal' => __( 'Horizontal', 'paypal-payments-for-woocommerce' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_tagline'                 => array(
				'title'        => __( 'Tagline', 'paypal-payments-for-woocommerce' ),
				'type'         => 'checkbox',
				'default'      => true,
				'label'        => __( 'Enable tagline', 'paypal-payments-for-woocommerce' ),
				'desc_tip'     => true,
				'description'  => __(
					'Add the tagline. This line will only show up, if you select a horizontal layout.',
					'paypal-payments-for-woocommerce'
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_label'                   => array(
				'title'        => __( 'Button Label', 'paypal-payments-for-woocommerce' ),
				'type'         => 'select',
				'class'        => array( 'wc-enhanced-select' ),
				'default'      => 'paypal',
				'desc_tip'     => true,
				'description'  => __(
					'This controls the label on the primary button.',
					'paypal-payments-for-woocommerce'
				),
				'options'      => array(
					'paypal'   => __( 'PayPal', 'paypal-payments-for-woocommerce' ),
					'checkout' => __( 'PayPal Checkout', 'paypal-payments-for-woocommerce' ),
					'buynow'   => __( 'PayPal Buy Now', 'paypal-payments-for-woocommerce' ),
					'pay'      => __( 'Pay with PayPal', 'paypal-payments-for-woocommerce' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_color'                   => array(
				'title'        => __( 'Color', 'paypal-payments-for-woocommerce' ),
				'type'         => 'select',
				'class'        => array( 'wc-enhanced-select' ),
				'default'      => 'gold',
				'desc_tip'     => true,
				'description'  => __(
					'Controls the background color of the primary button. Use "Gold" to leverage PayPal\'s recognition and preference, or change it to match your site design or aesthetic.',
					'paypal-payments-for-woocommerce'
				),
				'options'      => array(
					'gold'   => __( 'Gold (Recommended)', 'paypal-payments-for-woocommerce' ),
					'blue'   => __( 'Blue', 'paypal-payments-for-woocommerce' ),
					'silver' => __( 'Silver', 'paypal-payments-for-woocommerce' ),
					'black'  => __( 'Black', 'paypal-payments-for-woocommerce' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_shape'                   => array(
				'title'        => __( 'Shape', 'paypal-payments-for-woocommerce' ),
				'type'         => 'select',
				'class'        => array( 'wc-enhanced-select' ),
				'default'      => 'rect',
				'desc_tip'     => true,
				'description'  => __(
					'The pill-shaped button\'s unique and powerful shape signifies PayPal in people\'s minds. Use the rectangular button as an alternative when pill-shaped buttons might pose design challenges.',
					'paypal-payments-for-woocommerce'
				),
				'options'      => array(
					'pill' => __( 'Pill', 'paypal-payments-for-woocommerce' ),
					'rect' => __( 'Rectangle', 'paypal-payments-for-woocommerce' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'message_heading'                => array(
				'heading'      => __( 'Credit Messaging on Checkout', 'paypal-payments-for-woocommerce' ),
				'type'         => 'ppcp-heading',
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array( 'messages' ),
				'gateway'      => 'paypal',
			),
			'message_enabled'                => array(
				'title'        => __( 'Enable message on Checkout', 'paypal-payments-for-woocommerce' ),
				'type'         => 'checkbox',
				'label'        => __( 'Enable on Checkout', 'paypal-payments-for-woocommerce' ),
				'default'      => true,
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array( 'messages' ),
				'gateway'      => 'paypal',
			),
			'message_layout'                 => array(
				'title'        => __( 'Credit Messaging layout', 'paypal-payments-for-woocommerce' ),
				'type'         => 'select',
				'class'        => array( 'wc-enhanced-select' ),
				'default'      => 'text',
				'desc_tip'     => true,
				'description'  => __(
					'The layout of the message.',
					'paypal-payments-for-woocommerce'
				),
				'options'      => array(
					'text' => __( 'Text', 'paypal-payments-for-woocommerce' ),
					'flex' => __( 'Flex', 'paypal-payments-for-woocommerce' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array( 'messages' ),
				'gateway'      => 'paypal',
			),
			'message_logo'                   => array(
				'title'        => __( 'Credit Messaging logo', 'paypal-payments-for-woocommerce' ),
				'type'         => 'select',
				'class'        => array( 'wc-enhanced-select' ),
				'default'      => 'primary',
				'desc_tip'     => true,
				'description'  => __(
					'What logo the text message contains. Only applicable, when the layout style Text is used.',
					'paypal-payments-for-woocommerce'
				),
				'options'      => array(
					'primary'     => __( 'Primary', 'paypal-payments-for-woocommerce' ),
					'alternative' => __( 'Alternative', 'paypal-payments-for-woocommerce' ),
					'inline'      => __( 'Inline', 'paypal-payments-for-woocommerce' ),
					'none'        => __( 'None', 'paypal-payments-for-woocommerce' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array( 'messages' ),
				'gateway'      => 'paypal',
			),
			'message_position'               => array(
				'title'        => __( 'Credit Messaging logo position', 'paypal-payments-for-woocommerce' ),
				'type'         => 'select',
				'class'        => array( 'wc-enhanced-select' ),
				'default'      => 'left',
				'desc_tip'     => true,
				'description'  => __(
					'The position of the logo. Only applicable, when the layout style Text is used.',
					'paypal-payments-for-woocommerce'
				),
				'options'      => array(
					'left'  => __( 'Left', 'paypal-payments-for-woocommerce' ),
					'right' => __( 'Right', 'paypal-payments-for-woocommerce' ),
					'top'   => __( 'Top', 'paypal-payments-for-woocommerce' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array( 'messages' ),
				'gateway'      => 'paypal',
			),
			'message_color'                  => array(
				'title'        => __( 'Credit Messaging text color', 'paypal-payments-for-woocommerce' ),
				'type'         => 'select',
				'class'        => array( 'wc-enhanced-select' ),
				'default'      => 'black',
				'desc_tip'     => true,
				'description'  => __(
					'The color of the text. Only applicable, when the layout style Text is used.',
					'paypal-payments-for-woocommerce'
				),
				'options'      => array(
					'black'      => __( 'Black', 'paypal-payments-for-woocommerce' ),
					'white'      => __( 'White', 'paypal-payments-for-woocommerce' ),
					'monochrome' => __( 'Monochrome', 'paypal-payments-for-woocommerce' ),
					'grayscale'  => __( 'Grayscale', 'paypal-payments-for-woocommerce' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array( 'messages' ),
				'gateway'      => 'paypal',
			),
			'message_flex_color'             => array(
				'title'        => __( 'Credit Messaging color', 'paypal-payments-for-woocommerce' ),
				'type'         => 'select',
				'class'        => array( 'wc-enhanced-select' ),
				'default'      => 'blue',
				'desc_tip'     => true,
				'description'  => __(
					'The color of the text. Only applicable, when the layout style Flex is used.',
					'paypal-payments-for-woocommerce'
				),
				'options'      => array(
					'blue'            => __( 'Blue', 'paypal-payments-for-woocommerce' ),
					'black'           => __( 'Black', 'paypal-payments-for-woocommerce' ),
					'white'           => __( 'White', 'paypal-payments-for-woocommerce' ),
					'white-no-border' => __( 'White no border', 'paypal-payments-for-woocommerce' ),
					'gray'            => __( 'Gray', 'paypal-payments-for-woocommerce' ),
					'monochrome'      => __( 'Monochrome', 'paypal-payments-for-woocommerce' ),
					'grayscale'       => __( 'Grayscale', 'paypal-payments-for-woocommerce' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array( 'messages' ),
				'gateway'      => 'paypal',
			),
			'message_flex_ratio'             => array(
				'title'        => __( 'Credit Messaging ratio', 'paypal-payments-for-woocommerce' ),
				'type'         => 'select',
				'class'        => array( 'wc-enhanced-select' ),
				'default'      => '1x1',
				'desc_tip'     => true,
				'description'  => __(
					'The width/height ratio of the banner. Only applicable, when the layout style Flex is used.',
					'paypal-payments-for-woocommerce'
				),
				'options'      => array(
					'1x1'  => __( '1x1', 'paypal-payments-for-woocommerce' ),
					'1x4'  => __( '1x4', 'paypal-payments-for-woocommerce' ),
					'8x1'  => __( '8x1', 'paypal-payments-for-woocommerce' ),
					'20x1' => __( '20x1', 'paypal-payments-for-woocommerce' ),
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
				'heading'      => __( 'Button on Single product', 'paypal-payments-for-woocommerce' ),
				'type'         => 'ppcp-heading',
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_product_enabled'         => array(
				'title'        => __( 'Enable buttons on Single Product', 'paypal-payments-for-woocommerce' ),
				'type'         => 'checkbox',
				'label'        => __( 'Enable on Single Product', 'paypal-payments-for-woocommerce' ),
				'default'      => true,
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_product_layout'          => array(
				'title'        => __( 'Button Layout', 'paypal-payments-for-woocommerce' ),
				'type'         => 'select',
				'class'        => array( 'wc-enhanced-select' ),
				'default'      => 'horizontal',
				'desc_tip'     => true,
				'description'  => __(
					'If additional funding sources are available to the buyer through PayPal, such as Venmo, then multiple buttons are displayed in the space provided. Choose "vertical" for a dynamic list of alternative and local payment options, or "horizontal" when space is limited.',
					'paypal-payments-for-woocommerce'
				),
				'options'      => array(
					'vertical'   => __( 'Vertical', 'paypal-payments-for-woocommerce' ),
					'horizontal' => __( 'Horizontal', 'paypal-payments-for-woocommerce' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_product_tagline'         => array(
				'title'        => __( 'Tagline', 'paypal-payments-for-woocommerce' ),
				'type'         => 'checkbox',
				'label'        => __( 'Enable tagline', 'paypal-payments-for-woocommerce' ),
				'default'      => true,
				'desc_tip'     => true,
				'description'  => __(
					'Add the tagline. This line will only show up, if you select a horizontal layout.',
					'paypal-payments-for-woocommerce'
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_product_label'           => array(
				'title'        => __( 'Button Label', 'paypal-payments-for-woocommerce' ),
				'type'         => 'select',
				'class'        => array( 'wc-enhanced-select' ),
				'default'      => 'paypal',
				'desc_tip'     => true,
				'description'  => __(
					'This controls the label on the primary button.',
					'paypal-payments-for-woocommerce'
				),
				'options'      => array(
					'paypal'   => __( 'PayPal', 'paypal-payments-for-woocommerce' ),
					'checkout' => __( 'PayPal Checkout', 'paypal-payments-for-woocommerce' ),
					'buynow'   => __( 'PayPal Buy Now', 'paypal-payments-for-woocommerce' ),
					'pay'      => __( 'Pay with PayPal', 'paypal-payments-for-woocommerce' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_product_color'           => array(
				'title'        => __( 'Color', 'paypal-payments-for-woocommerce' ),
				'type'         => 'select',
				'class'        => array( 'wc-enhanced-select' ),
				'default'      => 'gold',
				'desc_tip'     => true,
				'description'  => __(
					'Controls the background color of the primary button. Use "Gold" to leverage PayPal\'s recognition and preference, or change it to match your site design or aesthetic.',
					'paypal-payments-for-woocommerce'
				),
				'options'      => array(
					'gold'   => __( 'Gold (Recommended)', 'paypal-payments-for-woocommerce' ),
					'blue'   => __( 'Blue', 'paypal-payments-for-woocommerce' ),
					'silver' => __( 'Silver', 'paypal-payments-for-woocommerce' ),
					'black'  => __( 'Black', 'paypal-payments-for-woocommerce' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_product_shape'           => array(
				'title'        => __( 'Shape', 'paypal-payments-for-woocommerce' ),
				'type'         => 'select',
				'class'        => array( 'wc-enhanced-select' ),
				'default'      => 'rect',
				'desc_tip'     => true,
				'description'  => __(
					'The pill-shaped button\'s unique and powerful shape signifies PayPal in people\'s minds. Use the rectangular button as an alternative when pill-shaped buttons might pose design challenges.',
					'paypal-payments-for-woocommerce'
				),
				'options'      => array(
					'pill' => __( 'Pill', 'paypal-payments-for-woocommerce' ),
					'rect' => __( 'Rectangle', 'paypal-payments-for-woocommerce' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),

			'message_product_heading'        => array(
				'heading'      => __( 'Credit Messaging on Single product', 'paypal-payments-for-woocommerce' ),
				'type'         => 'ppcp-heading',
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array( 'messages' ),
				'gateway'      => 'paypal',
			),
			'message_product_enabled'        => array(
				'title'        => __( 'Enable message on Single Product', 'paypal-payments-for-woocommerce' ),
				'type'         => 'checkbox',
				'label'        => __( 'Enable on Single Product', 'paypal-payments-for-woocommerce' ),
				'default'      => true,
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array( 'messages' ),
				'gateway'      => 'paypal',
			),
			'message_product_layout'         => array(
				'title'        => __( 'Credit Messaging layout', 'paypal-payments-for-woocommerce' ),
				'type'         => 'select',
				'class'        => array( 'wc-enhanced-select' ),
				'default'      => 'text',
				'desc_tip'     => true,
				'description'  => __(
					'The layout of the message.',
					'paypal-payments-for-woocommerce'
				),
				'options'      => array(
					'text' => __( 'Text', 'paypal-payments-for-woocommerce' ),
					'flex' => __( 'Flex', 'paypal-payments-for-woocommerce' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array( 'messages' ),
				'gateway'      => 'paypal',
			),
			'message_product_logo'           => array(
				'title'        => __( 'Credit Messaging logo', 'paypal-payments-for-woocommerce' ),
				'type'         => 'select',
				'class'        => array( 'wc-enhanced-select' ),
				'default'      => 'primary',
				'desc_tip'     => true,
				'description'  => __(
					'What logo the text message contains. Only applicable, when the layout style Text is used.',
					'paypal-payments-for-woocommerce'
				),
				'options'      => array(
					'primary'     => __( 'Primary', 'paypal-payments-for-woocommerce' ),
					'alternative' => __( 'Alternative', 'paypal-payments-for-woocommerce' ),
					'inline'      => __( 'Inline', 'paypal-payments-for-woocommerce' ),
					'none'        => __( 'None', 'paypal-payments-for-woocommerce' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array( 'messages' ),
				'gateway'      => 'paypal',
			),
			'message_product_position'       => array(
				'title'        => __( 'Credit Messaging logo position', 'paypal-payments-for-woocommerce' ),
				'type'         => 'select',
				'class'        => array( 'wc-enhanced-select' ),
				'default'      => 'left',
				'desc_tip'     => true,
				'description'  => __(
					'The position of the logo. Only applicable, when the layout style Text is used.',
					'paypal-payments-for-woocommerce'
				),
				'options'      => array(
					'left'  => __( 'Left', 'paypal-payments-for-woocommerce' ),
					'right' => __( 'Right', 'paypal-payments-for-woocommerce' ),
					'top'   => __( 'Top', 'paypal-payments-for-woocommerce' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array( 'messages' ),
				'gateway'      => 'paypal',
			),
			'message_product_color'          => array(
				'title'        => __( 'Credit Messaging text color', 'paypal-payments-for-woocommerce' ),
				'type'         => 'select',
				'class'        => array( 'wc-enhanced-select' ),
				'default'      => 'black',
				'desc_tip'     => true,
				'description'  => __(
					'The color of the text. Only applicable, when the layout style Text is used.',
					'paypal-payments-for-woocommerce'
				),
				'options'      => array(
					'black'      => __( 'Black', 'paypal-payments-for-woocommerce' ),
					'white'      => __( 'White', 'paypal-payments-for-woocommerce' ),
					'monochrome' => __( 'Monochrome', 'paypal-payments-for-woocommerce' ),
					'grayscale'  => __( 'Grayscale', 'paypal-payments-for-woocommerce' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array( 'messages' ),
				'gateway'      => 'paypal',
			),
			'message_product_flex_color'     => array(
				'title'        => __( 'Credit Messaging color', 'paypal-payments-for-woocommerce' ),
				'type'         => 'select',
				'class'        => array( 'wc-enhanced-select' ),
				'default'      => 'blue',
				'desc_tip'     => true,
				'description'  => __(
					'The color of the text. Only applicable, when the layout style Flex is used.',
					'paypal-payments-for-woocommerce'
				),
				'options'      => array(
					'blue'            => __( 'Blue', 'paypal-payments-for-woocommerce' ),
					'black'           => __( 'Black', 'paypal-payments-for-woocommerce' ),
					'white'           => __( 'White', 'paypal-payments-for-woocommerce' ),
					'white-no-border' => __( 'White no border', 'paypal-payments-for-woocommerce' ),
					'gray'            => __( 'Gray', 'paypal-payments-for-woocommerce' ),
					'monochrome'      => __( 'Monochrome', 'paypal-payments-for-woocommerce' ),
					'grayscale'       => __( 'Grayscale', 'paypal-payments-for-woocommerce' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array( 'messages' ),
				'gateway'      => 'paypal',
			),
			'message_product_flex_ratio'     => array(
				'title'        => __( 'Credit Messaging ratio', 'paypal-payments-for-woocommerce' ),
				'type'         => 'select',
				'class'        => array( 'wc-enhanced-select' ),
				'default'      => '1x1',
				'desc_tip'     => true,
				'description'  => __(
					'The width/height ratio of the banner. Only applicable, when the layout style Flex is used.',
					'paypal-payments-for-woocommerce'
				),
				'options'      => array(
					'1x1'  => __( '1x1', 'paypal-payments-for-woocommerce' ),
					'1x4'  => __( '1x4', 'paypal-payments-for-woocommerce' ),
					'8x1'  => __( '8x1', 'paypal-payments-for-woocommerce' ),
					'20x1' => __( '20x1', 'paypal-payments-for-woocommerce' ),
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
				'heading'      => __( 'Mini Cart', 'paypal-payments-for-woocommerce' ),
				'type'         => 'ppcp-heading',
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_mini-cart_enabled'       => array(
				'title'        => __( 'Buttons on Mini Cart', 'paypal-payments-for-woocommerce' ),
				'type'         => 'checkbox',
				'label'        => __( 'Enable on Mini Cart', 'paypal-payments-for-woocommerce' ),
				'default'      => true,
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_mini-cart_layout'        => array(
				'title'        => __( 'Button Layout', 'paypal-payments-for-woocommerce' ),
				'type'         => 'select',
				'class'        => array( 'wc-enhanced-select' ),
				'default'      => 'horizontal',
				'desc_tip'     => true,
				'description'  => __(
					'If additional funding sources are available to the buyer through PayPal, such as Venmo, then multiple buttons are displayed in the space provided. Choose "vertical" for a dynamic list of alternative and local payment options, or "horizontal" when space is limited.',
					'paypal-payments-for-woocommerce'
				),
				'options'      => array(
					'vertical'   => __( 'Vertical', 'paypal-payments-for-woocommerce' ),
					'horizontal' => __( 'Horizontal', 'paypal-payments-for-woocommerce' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_mini-cart_tagline'       => array(
				'title'        => __( 'Tagline', 'paypal-payments-for-woocommerce' ),
				'type'         => 'checkbox',
				'label'        => __( 'Enable tagline', 'paypal-payments-for-woocommerce' ),
				'default'      => false,
				'desc_tip'     => true,
				'description'  => __(
					'Add the tagline. This line will only show up, if you select a horizontal layout.',
					'paypal-payments-for-woocommerce'
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_mini-cart_label'         => array(
				'title'        => __( 'Button Label', 'paypal-payments-for-woocommerce' ),
				'type'         => 'select',
				'class'        => array( 'wc-enhanced-select' ),
				'default'      => 'paypal',
				'desc_tip'     => true,
				'description'  => __(
					'This controls the label on the primary button.',
					'paypal-payments-for-woocommerce'
				),
				'options'      => array(
					'paypal'   => __( 'PayPal', 'paypal-payments-for-woocommerce' ),
					'checkout' => __( 'PayPal Checkout', 'paypal-payments-for-woocommerce' ),
					'buynow'   => __( 'PayPal Buy Now', 'paypal-payments-for-woocommerce' ),
					'pay'      => __( 'Pay with PayPal', 'paypal-payments-for-woocommerce' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_mini-cart_color'         => array(
				'title'        => __( 'Color', 'paypal-payments-for-woocommerce' ),
				'type'         => 'select',
				'class'        => array( 'wc-enhanced-select' ),
				'default'      => 'gold',
				'desc_tip'     => true,
				'description'  => __(
					'Controls the background color of the primary button. Use "Gold" to leverage PayPal\'s recognition and preference, or change it to match your site design or aesthetic.',
					'paypal-payments-for-woocommerce'
				),
				'options'      => array(
					'gold'   => __( 'Gold (Recommended)', 'paypal-payments-for-woocommerce' ),
					'blue'   => __( 'Blue', 'paypal-payments-for-woocommerce' ),
					'silver' => __( 'Silver', 'paypal-payments-for-woocommerce' ),
					'black'  => __( 'Black', 'paypal-payments-for-woocommerce' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_mini-cart_shape'         => array(
				'title'        => __( 'Shape', 'paypal-payments-for-woocommerce' ),
				'type'         => 'select',
				'class'        => array( 'wc-enhanced-select' ),
				'default'      => 'rect',
				'desc_tip'     => true,
				'description'  => __(
					'The pill-shaped button\'s unique and powerful shape signifies PayPal in people\'s minds. Use the rectangular button as an alternative when pill-shaped buttons might pose design challenges.',
					'paypal-payments-for-woocommerce'
				),
				'options'      => array(
					'pill' => __( 'Pill', 'paypal-payments-for-woocommerce' ),
					'rect' => __( 'Rectangle', 'paypal-payments-for-woocommerce' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),

			// Cart settings.
			'button_cart_heading'            => array(
				'heading'      => __( 'Cart', 'paypal-payments-for-woocommerce' ),
				'type'         => 'ppcp-heading',
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_cart_enabled'            => array(
				'title'        => __( 'Buttons on Cart', 'paypal-payments-for-woocommerce' ),
				'type'         => 'checkbox',
				'label'        => __( 'Enable on Cart', 'paypal-payments-for-woocommerce' ),
				'default'      => true,
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_cart_layout'             => array(
				'title'        => __( 'Button Layout', 'paypal-payments-for-woocommerce' ),
				'type'         => 'select',
				'class'        => array( 'wc-enhanced-select' ),
				'default'      => 'horizontal',
				'desc_tip'     => true,
				'description'  => __(
					'If additional funding sources are available to the buyer through PayPal, such as Venmo, then multiple buttons are displayed in the space provided. Choose "vertical" for a dynamic list of alternative and local payment options, or "horizontal" when space is limited.',
					'paypal-payments-for-woocommerce'
				),
				'options'      => array(
					'vertical'   => __( 'Vertical', 'paypal-payments-for-woocommerce' ),
					'horizontal' => __( 'Horizontal', 'paypal-payments-for-woocommerce' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_cart_tagline'            => array(
				'title'        => __( 'Tagline', 'paypal-payments-for-woocommerce' ),
				'type'         => 'checkbox',
				'label'        => __( 'Enable tagline', 'paypal-payments-for-woocommerce' ),
				'default'      => true,
				'desc_tip'     => true,
				'description'  => __(
					'Add the tagline. This line will only show up, if you select a horizontal layout.',
					'paypal-payments-for-woocommerce'
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_cart_label'              => array(
				'title'        => __( 'Button Label', 'paypal-payments-for-woocommerce' ),
				'type'         => 'select',
				'class'        => array( 'wc-enhanced-select' ),
				'default'      => 'paypal',
				'desc_tip'     => true,
				'description'  => __(
					'This controls the label on the primary button.',
					'paypal-payments-for-woocommerce'
				),
				'options'      => array(
					'paypal'   => __( 'PayPal', 'paypal-payments-for-woocommerce' ),
					'checkout' => __( 'PayPal Checkout', 'paypal-payments-for-woocommerce' ),
					'buynow'   => __( 'PayPal Buy Now', 'paypal-payments-for-woocommerce' ),
					'pay'      => __( 'Pay with PayPal', 'paypal-payments-for-woocommerce' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_cart_color'              => array(
				'title'        => __( 'Color', 'paypal-payments-for-woocommerce' ),
				'type'         => 'select',
				'class'        => array( 'wc-enhanced-select' ),
				'default'      => 'gold',
				'desc_tip'     => true,
				'description'  => __(
					'Controls the background color of the primary button. Use "Gold" to leverage PayPal\'s recognition and preference, or change it to match your site design or aesthetic.',
					'paypal-payments-for-woocommerce'
				),
				'options'      => array(
					'gold'   => __( 'Gold (Recommended)', 'paypal-payments-for-woocommerce' ),
					'blue'   => __( 'Blue', 'paypal-payments-for-woocommerce' ),
					'silver' => __( 'Silver', 'paypal-payments-for-woocommerce' ),
					'black'  => __( 'Black', 'paypal-payments-for-woocommerce' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_cart_shape'              => array(
				'title'        => __( 'Shape', 'paypal-payments-for-woocommerce' ),
				'type'         => 'select',
				'class'        => array( 'wc-enhanced-select' ),
				'default'      => 'rect',
				'desc_tip'     => true,
				'description'  => __(
					'The pill-shaped button\'s unique and powerful shape signifies PayPal in people\'s minds. Use the rectangular button as an alternative when pill-shaped buttons might pose design challenges.',
					'paypal-payments-for-woocommerce'
				),
				'options'      => array(
					'pill' => __( 'Pill', 'paypal-payments-for-woocommerce' ),
					'rect' => __( 'Rectangle', 'paypal-payments-for-woocommerce' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),

			'message_cart_heading'           => array(
				'heading'      => __( 'Credit Messaging on Cart', 'paypal-payments-for-woocommerce' ),
				'type'         => 'ppcp-heading',
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array( 'messages' ),
				'gateway'      => 'paypal',
			),
			'message_cart_enabled'           => array(
				'title'        => __( 'Enable message on Cart', 'paypal-payments-for-woocommerce' ),
				'type'         => 'checkbox',
				'label'        => __( 'Enable on Cart', 'paypal-payments-for-woocommerce' ),
				'default'      => true,
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array( 'messages' ),
				'gateway'      => 'paypal',
			),
			'message_cart_layout'            => array(
				'title'        => __( 'Credit Messaging layout', 'paypal-payments-for-woocommerce' ),
				'type'         => 'select',
				'class'        => array( 'wc-enhanced-select' ),
				'default'      => 'text',
				'desc_tip'     => true,
				'description'  => __(
					'The layout of the message.',
					'paypal-payments-for-woocommerce'
				),
				'options'      => array(
					'text' => __( 'Text', 'paypal-payments-for-woocommerce' ),
					'flex' => __( 'Flex', 'paypal-payments-for-woocommerce' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array( 'messages' ),
				'gateway'      => 'paypal',
			),
			'message_cart_logo'              => array(
				'title'        => __( 'Credit Messaging logo', 'paypal-payments-for-woocommerce' ),
				'type'         => 'select',
				'class'        => array( 'wc-enhanced-select' ),
				'default'      => 'primary',
				'desc_tip'     => true,
				'description'  => __(
					'What logo the text message contains. Only applicable, when the layout style Text is used.',
					'paypal-payments-for-woocommerce'
				),
				'options'      => array(
					'primary'     => __( 'Primary', 'paypal-payments-for-woocommerce' ),
					'alternative' => __( 'Alternative', 'paypal-payments-for-woocommerce' ),
					'inline'      => __( 'Inline', 'paypal-payments-for-woocommerce' ),
					'none'        => __( 'None', 'paypal-payments-for-woocommerce' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array( 'messages' ),
				'gateway'      => 'paypal',
			),
			'message_cart_position'          => array(
				'title'        => __( 'Credit Messaging logo position', 'paypal-payments-for-woocommerce' ),
				'type'         => 'select',
				'class'        => array( 'wc-enhanced-select' ),
				'default'      => 'left',
				'desc_tip'     => true,
				'description'  => __(
					'The position of the logo. Only applicable, when the layout style Text is used.',
					'paypal-payments-for-woocommerce'
				),
				'options'      => array(
					'left'  => __( 'Left', 'paypal-payments-for-woocommerce' ),
					'right' => __( 'Right', 'paypal-payments-for-woocommerce' ),
					'top'   => __( 'Top', 'paypal-payments-for-woocommerce' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array( 'messages' ),
				'gateway'      => 'paypal',
			),
			'message_cart_color'             => array(
				'title'        => __( 'Credit Messaging text color', 'paypal-payments-for-woocommerce' ),
				'type'         => 'select',
				'class'        => array( 'wc-enhanced-select' ),
				'default'      => 'black',
				'desc_tip'     => true,
				'description'  => __(
					'The color of the text. Only applicable, when the layout style Text is used.',
					'paypal-payments-for-woocommerce'
				),
				'options'      => array(
					'black'      => __( 'Black', 'paypal-payments-for-woocommerce' ),
					'white'      => __( 'White', 'paypal-payments-for-woocommerce' ),
					'monochrome' => __( 'Monochrome', 'paypal-payments-for-woocommerce' ),
					'grayscale'  => __( 'Grayscale', 'paypal-payments-for-woocommerce' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array( 'messages' ),
				'gateway'      => 'paypal',
			),
			'message_cart_flex_color'        => array(
				'title'        => __( 'Credit Messaging color', 'paypal-payments-for-woocommerce' ),
				'type'         => 'select',
				'class'        => array( 'wc-enhanced-select' ),
				'default'      => 'blue',
				'desc_tip'     => true,
				'description'  => __(
					'The color of the text. Only applicable, when the layout style Flex is used.',
					'paypal-payments-for-woocommerce'
				),
				'options'      => array(
					'blue'            => __( 'Blue', 'paypal-payments-for-woocommerce' ),
					'black'           => __( 'Black', 'paypal-payments-for-woocommerce' ),
					'white'           => __( 'White', 'paypal-payments-for-woocommerce' ),
					'white-no-border' => __( 'White no border', 'paypal-payments-for-woocommerce' ),
					'gray'            => __( 'Gray', 'paypal-payments-for-woocommerce' ),
					'monochrome'      => __( 'Monochrome', 'paypal-payments-for-woocommerce' ),
					'grayscale'       => __( 'Grayscale', 'paypal-payments-for-woocommerce' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array( 'messages' ),
				'gateway'      => 'paypal',
			),
			'message_cart_flex_ratio'        => array(
				'title'        => __( 'Credit Messaging ratio', 'paypal-payments-for-woocommerce' ),
				'type'         => 'select',
				'class'        => array( 'wc-enhanced-select' ),
				'default'      => '1x1',
				'desc_tip'     => true,
				'description'  => __(
					'The width/height ratio of the banner. Only applicable, when the layout style Flex is used.',
					'paypal-payments-for-woocommerce'
				),
				'options'      => array(
					'1x1'  => __( '1x1', 'paypal-payments-for-woocommerce' ),
					'1x4'  => __( '1x4', 'paypal-payments-for-woocommerce' ),
					'8x1'  => __( '8x1', 'paypal-payments-for-woocommerce' ),
					'20x1' => __( '20x1', 'paypal-payments-for-woocommerce' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array( 'messages' ),
				'gateway'      => 'paypal',
			),

			'disable_cards'                  => array(
				'title'        => __( 'Disable specific credit cards', 'paypal-payments-for-woocommerce' ),
				'type'         => 'ppcp-multiselect',
				'class'        => array( 'wc-enhanced-select' ),
				'default'      => array(),
				'desc_tip'     => true,
				'description'  => __(
					'By default all possible credit cards will be accepted. You can disable some cards, if you wish.',
					'paypal-payments-for-woocommerce'
				),
				'options'      => array(
					'visa'       => _x( 'Visa', 'Name of credit card', 'paypal-payments-for-woocommerce' ),
					'mastercard' => _x( 'Mastercard', 'Name of credit card', 'paypal-payments-for-woocommerce' ),
					'amex'       => _x( 'American Express', 'Name of credit card', 'paypal-payments-for-woocommerce' ),
					'discover'   => _x( 'Discover', 'Name of credit card', 'paypal-payments-for-woocommerce' ),
					'jcb'        => _x( 'JCB', 'Name of credit card', 'paypal-payments-for-woocommerce' ),
					'elo'        => _x( 'Elo', 'Name of credit card', 'paypal-payments-for-woocommerce' ),
					'hiper'      => _x( 'Hiper', 'Name of credit card', 'paypal-payments-for-woocommerce' ),
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
				'title'        => __( 'Show logo of the following credit cards', 'paypal-payments-for-woocommerce' ),
				'type'         => 'ppcp-multiselect',
				'class'        => array( 'wc-enhanced-select' ),
				'default'      => array(),
				'desc_tip'     => true,
				'description'  => __(
					'Define, which cards you want to display in your checkout..',
					'paypal-payments-for-woocommerce'
				),
				'options'      => array(
					'visa'       => _x( 'Visa', 'Name of credit card', 'paypal-payments-for-woocommerce' ),
					'mastercard' => _x( 'Mastercard', 'Name of credit card', 'paypal-payments-for-woocommerce' ),
					'amex'       => _x( 'American Express', 'Name of credit card', 'paypal-payments-for-woocommerce' ),
					'discover'   => _x( 'Discover', 'Name of credit card', 'paypal-payments-for-woocommerce' ),
					'jcb'        => _x( 'JCB', 'Name of credit card', 'paypal-payments-for-woocommerce' ),
					'elo'        => _x( 'Elo', 'Name of credit card', 'paypal-payments-for-woocommerce' ),
					'hiper'      => _x( 'Hiper', 'Name of credit card', 'paypal-payments-for-woocommerce' ),
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
			dirname( __FILE__, 3 ) . '/woocommerce-paypal-commerce-gateway.php'
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
