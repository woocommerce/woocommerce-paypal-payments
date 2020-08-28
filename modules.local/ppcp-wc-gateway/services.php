<?php
/**
 * The services of the Gateway module.
 *
 * @package Inpsyde\PayPalCommerce\WcGateway
 */

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\WcGateway;

use Dhii\Data\Container\ContainerInterface;
use Inpsyde\PayPalCommerce\ApiClient\Entity\ApplicationContext;
use Inpsyde\PayPalCommerce\Onboarding\State;
use Inpsyde\PayPalCommerce\WcGateway\Admin\OrderTablePaymentStatusColumn;
use Inpsyde\PayPalCommerce\WcGateway\Admin\PaymentStatusOrderDetail;
use Inpsyde\PayPalCommerce\WcGateway\Checkout\CheckoutPayPalAddressPreset;
use Inpsyde\PayPalCommerce\WcGateway\Checkout\DisableGateways;
use Inpsyde\PayPalCommerce\WcGateway\Endpoint\ReturnUrlEndpoint;
use Inpsyde\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use Inpsyde\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use Inpsyde\PayPalCommerce\WcGateway\Notice\AuthorizeOrderActionNotice;
use Inpsyde\PayPalCommerce\WcGateway\Notice\ConnectAdminNotice;
use Inpsyde\PayPalCommerce\WcGateway\Processor\AuthorizedPaymentsProcessor;
use Inpsyde\PayPalCommerce\WcGateway\Processor\OrderProcessor;
use Inpsyde\PayPalCommerce\WcGateway\Settings\Settings;
use Inpsyde\PayPalCommerce\WcGateway\Settings\SettingsListener;
use Inpsyde\PayPalCommerce\WcGateway\Settings\SettingsRenderer;
use WpOop\TransientCache\CachePoolFactory;

return array(
	'wcgateway.paypal-gateway'                     => static function ( ContainerInterface $container ): PayPalGateway {
		$order_processor     = $container->get( 'wcgateway.order-processor' );
		$settings_renderer   = $container->get( 'wcgateway.settings.render' );
		$authorized_payments = $container->get( 'wcgateway.processor.authorized-payments' );
		$notice             = $container->get( 'wcgateway.notice.authorize-order-action' );
		$settings           = $container->get( 'wcgateway.settings' );
		$session_handler     = $container->get( 'session.handler' );

		return new PayPalGateway(
			$settings_renderer,
			$order_processor,
			$authorized_payments,
			$notice,
			$settings,
			$session_handler
		);
	},
	'wcgateway.credit-card-gateway'                => static function ( ContainerInterface $container ): CreditCardGateway {
		$order_processor     = $container->get( 'wcgateway.order-processor' );
		$settings_renderer   = $container->get( 'wcgateway.settings.render' );
		$authorized_payments = $container->get( 'wcgateway.processor.authorized-payments' );
		$notice             = $container->get( 'wcgateway.notice.authorize-order-action' );
		$settings           = $container->get( 'wcgateway.settings' );
		$moduleUrl          = $container->get( 'wcgateway.url' );
		$session_handler     = $container->get( 'session.handler' );
		return new CreditCardGateway(
			$settings_renderer,
			$order_processor,
			$authorized_payments,
			$notice,
			$settings,
			$moduleUrl,
			$session_handler
		);
	},
	'wcgateway.disabler'                           => static function ( ContainerInterface $container ): DisableGateways {
		$session_handler = $container->get( 'session.handler' );
		$settings       = $container->get( 'wcgateway.settings' );
		return new DisableGateways( $session_handler, $settings );
	},
	'wcgateway.settings'                           => static function ( ContainerInterface $container ): Settings {
		return new Settings();
	},
	'wcgateway.notice.connect'                     => static function ( ContainerInterface $container ): ConnectAdminNotice {
		$state    = $container->get( 'onboarding.state' );
		$settings = $container->get( 'wcgateway.settings' );
		return new ConnectAdminNotice( $state, $settings );
	},
	'wcgateway.notice.authorize-order-action'      =>
		static function ( ContainerInterface $container ): AuthorizeOrderActionNotice {
			return new AuthorizeOrderActionNotice();
		},
	'wcgateway.settings.render'                    => static function ( ContainerInterface $container ): SettingsRenderer {
		$settings      = $container->get( 'wcgateway.settings' );
		$state         = $container->get( 'onboarding.state' );
		$fields        = $container->get( 'wcgateway.settings.fields' );
		$dcc_applies    = $container->get( 'api.helpers.dccapplies' );
		$messages_apply = $container->get( 'button.helper.messages-apply' );
		return new SettingsRenderer(
			$settings,
			$state,
			$fields,
			$dcc_applies,
			$messages_apply
		);
	},
	'wcgateway.settings.listener'                  => static function ( ContainerInterface $container ): SettingsListener {
		$settings         = $container->get( 'wcgateway.settings' );
		$fields           = $container->get( 'wcgateway.settings.fields' );
		$webhook_registrar = $container->get( 'webhook.registrar' );
		$state            = $container->get( 'onboarding.state' );

		global $wpdb;
		$cache_pool_factory = new CachePoolFactory( $wpdb );
		$pool         = $cache_pool_factory->createCachePool( 'ppcp-token' );
		return new SettingsListener( $settings, $fields, $webhook_registrar, $pool, $state );
	},
	'wcgateway.order-processor'                    => static function ( ContainerInterface $container ): OrderProcessor {

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
	'wcgateway.processor.authorized-payments'      => static function ( ContainerInterface $container ): AuthorizedPaymentsProcessor {
		$order_endpoint    = $container->get( 'api.endpoint.order' );
		$payments_endpoint = $container->get( 'api.endpoint.payments' );
		return new AuthorizedPaymentsProcessor( $order_endpoint, $payments_endpoint );
	},
	'wcgateway.admin.order-payment-status'         => static function ( ContainerInterface $container ): PaymentStatusOrderDetail {
		return new PaymentStatusOrderDetail();
	},
	'wcgateway.admin.orders-payment-status-column' => static function ( ContainerInterface $container ): OrderTablePaymentStatusColumn {
		$settings = $container->get( 'wcgateway.settings' );
		return new OrderTablePaymentStatusColumn( $settings );
	},

	'wcgateway.settings.fields'                    => static function ( ContainerInterface $container ): array {
		$settings     = $container->get( 'wcgateway.settings' );
		$sandbox_text = $settings->has( 'sandbox_on' ) && $settings->get( 'sandbox_on' ) ?
			__(
				'You are currently in the sandbox mode to test your installation. You can switch this, by clicking <button name="%1$s" value="%2$s">Reset</button>',
				'woocommerce-paypal-commerce-gateway'
			) : __(
				'You are in live mode. This means, you can receive money into your account. You can switch this, by clicking <button name="%1$s" value="%2$s">Reset</button>',
				'woocommerce-paypal-commerce-gateway'
			);
		$sandbox_text = sprintf(
			$sandbox_text,
			'save',
			'reset'
		);

		$merchant_email_text = sprintf(
			__(
				'You are connected with your email address <mark>%1$s</mark>.
                If you want to change this settings, please click <button name="%2$s" value="%3$s">Reset</button>',
				'woocommerce-paypal-commerce-gateway'
			),
			$settings->has( 'merchant_email' ) ? $settings->get( 'merchant_email' ) : '',
			'save',
			'reset'
		);
		$fields              = array(
			'ppcp_onboarding'            => array(
				'title'        => __( 'Connect to PayPal', 'woocommerce-paypal-commerce-gateway' ),
				'type'         => 'ppcp_onboarding',
				'screens'      => array(
					State::STATE_PROGRESSIVE,
				),
				'requirements' => array(),
				'gateway'      => 'all',
			),
			'sandbox_on'                 => array(
				'title'        => __( 'Sandbox', 'woocommerce-paypal-commerce-gateway' ),
				'type'         => 'checkbox',
				'label'        => __( 'To test your Woocommerce installation, you can use the sandbox mode.', 'woocommerce-paypal-commerce-gateway' ),
				'default'      => 0,
				'screens'      => array(
					State::STATE_START,
				),
				'requirements' => array(),
				'gateway'      => 'all',
			),
			'sandbox_on_info'            => array(
				'title'        => __( 'Sandbox', 'woocommerce-paypal-commerce-gateway' ),
				'type'         => 'ppcp-text',
				'text'         => $sandbox_text,
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'hidden'       => 'sandbox_on',
				'requirements' => array(),
				'gateway'      => 'all',
			),
			'merchant_email'             => array(
				'title'        => __( 'Email address', 'woocommerce-paypal-commerce-gateway' ),
				'type'         => 'text',
				'required'     => true,
				'desc_tip'     => true,
				'description'  => __( 'The email address of your PayPal account.', 'woocommerce-paypal-commerce-gateway' ),
				'default'      => '',
				'screens'      => array(
					State::STATE_START,
				),
				'requirements' => array(),
				'gateway'      => 'all',
			),
			'merchant_email_info'        => array(
				'title'        => __( 'Email address', 'woocommerce-paypal-commerce-gateway' ),
				'type'         => 'ppcp-text',
				'text'         => $merchant_email_text,
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'hidden'       => 'merchant_email',
				'requirements' => array(),
				'gateway'      => 'all',
			),
			'toggle_manual_input'        => array(
				'type'         => 'ppcp-text',
				'title'        => __( 'Manual mode', 'woocommerce-paypla-commerce-gateway' ),
				'text'         => '<button id="ppcp[toggle_manual_input]">' . __( 'Toggle to manual credential input', 'woocommerce-paypal-commerce-gateway' ) . '</button>',
				'screens'      => array(
					State::STATE_START,
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'all',
			),
			'client_id'                  => array(
				'title'        => __( 'Client Id', 'woocommerce-paypal-commerce-gateway' ),
				'type'         => 'ppcp-text-input',
				'desc_tip'     => true,
				'description'  => __( 'The client id of your api ', 'woocommerce-paypal-commerce-gateway' ),
				'default'      => false,
				'screens'      => array(
					State::STATE_START,
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'all',
			),
			'client_secret'              => array(
				'title'        => __( 'Secret Key', 'woocommerce-paypal-commerce-gateway' ),
				'type'         => 'ppcp-password',
				'desc_tip'     => true,
				'description'  => __( 'The secret key of your api', 'woocommerce-paypal-commerce-gateway' ),
				'default'      => false,
				'screens'      => array(
					State::STATE_START,
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'all',
			),
			'title'                      => array(
				'title'        => __( 'Title', 'woocommerce-paypal-commerce-gateway' ),
				'type'         => 'text',
				'description'  => __(
					'This controls the title which the user sees during checkout.',
					'woocommerce-paypal-commerce-gateway'
				),
				'default'      => __( 'PayPal', 'woocommerce-paypal-commerce-gateway' ),
				'desc_tip'     => true,
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'dcc_gateway_title'          => array(
				'title'        => __( 'Title', 'woocommerce-paypal-commerce-gateway' ),
				'type'         => 'text',
				'description'  => __(
					'This controls the title which the user sees during checkout.',
					'woocommerce-paypal-commerce-gateway'
				),
				'default'      => __( 'Credit Cards', 'woocommerce-paypal-commerce-gateway' ),
				'desc_tip'     => true,
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'dcc',
			),
			'description'                => array(
				'title'        => __( 'Description', 'woocommerce-paypal-commerce-gateway' ),
				'type'         => 'text',
				'desc_tip'     => true,
				'description'  => __(
					'This controls the description which the user sees during checkout.',
					'woocommerce-paypal-commerce-gateway'
				),
				'default'      => __(
					'Pay via PayPal; you can pay with your credit card if you don\'t have a PayPal account.',
					'woocommerce-paypal-commerce-gateway'
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'dcc_gateway_description'    => array(
				'title'        => __( 'Description', 'woocommerce-paypal-commerce-gateway' ),
				'type'         => 'text',
				'desc_tip'     => true,
				'description'  => __(
					'This controls the description which the user sees during checkout.',
					'woocommerce-paypal-commerce-gateway'
				),
				'default'      => __(
					'Pay with your credit card.',
					'woocommerce-paypal-commerce-gateway'
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'dcc',
			),
			'intent'                     => array(
				'title'        => __( 'Intent', 'woocommerce-paypal-commerce-gateway' ),
				'type'         => 'select',
				'class'        => array( 'wc-enhanced-select' ),
				'default'      => 'capture',
				'desc_tip'     => true,
				'description'  => __(
					'The intent to either capture payment immediately or authorize a payment for an order after order creation.',
					'woocommerce-paypal-commerce-gateway'
				),
				'options'      => array(
					'capture'   => __( 'Capture', 'woocommerce-paypal-commerce-gateway' ),
					'authorize' => __( 'Authorize', 'woocommerce-paypal-commerce-gateway' ),
				),
				'screens'      => array(
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'all',
			),
			'capture_for_virtual_only'   => array(
				'title'        => __( 'Capture Virtual-Only Orders ', 'woocommerce-paypal-commerce-gateway' ),
				'type'         => 'checkbox',
				'default'      => false,
				'desc_tip'     => true,
				'description'  => __(
					'If the order contains exclusively virtual items, enable this to immediately capture, rather than authorize, the transaction.',
					'woocommerce-paypal-commerce-gateway'
				),
				'label'        => __( 'Capture Virtual-Only Orders', 'woocommerce-paypal-commerce-gateway' ),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'all',
			),
			'payee_preferred'            => array(
				'title'        => __( 'Instant Payments ', 'woocommerce-paypal-commerce-gateway' ),
				'type'         => 'checkbox',
				'default'      => false,
				'desc_tip'     => true,
				'description'  => __(
					'If you enable this setting, PayPal will be instructed not to allow the buyer to use funding sources that take additional time to complete (for example, eChecks). Instead, the buyer will be required to use an instant funding source, such as an instant transfer, a credit/debit card, or PayPal Credit.',
					'woocommerce-paypal-commerce-gateway'
				),
				'label'        => __( 'Require Instant Payment', 'woocommerce-paypal-commerce-gateway' ),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'brand_name'                 => array(
				'title'        => __( 'Brand Name', 'woocommerce-paypal-commerce-gateway' ),
				'type'         => 'text',
				'default'      => get_bloginfo( 'name' ),
				'desc_tip'     => true,
				'description'  => __(
					'Control the name of your shop, customers will see in the PayPal process.',
					'woocommerce-paypal-commerce-gateway'
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'landing_page'               => array(
				'title'        => __( 'Landing Page', 'woocommerce-paypal-commerce-gateway' ),
				'type'         => 'select',
				'class'        => array( 'wc-enhanced-select' ),
				'default'      => 'gold',
				'desc_tip'     => true,
				'description'  => __(
					'Type of PayPal page to display.',
					'woocommerce-paypal-commerce-gateway'
				),
				'options'      => array(
					ApplicationContext::LANDING_PAGE_LOGIN => __( 'Login (PayPal account login)', 'woocommerce-paypal-commerce-gateway' ),
					ApplicationContext::LANDING_PAGE_BILLING => __( 'Billing (Non-PayPal account)', 'woocommerce-paypal-commerce-gateway' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'disable_funding'            => array(
				'title'        => __( 'Disable funding sources', 'woocommerce-paypal-commerce-gateway' ),
				'type'         => 'ppcp-multiselect',
				'class'        => array( 'wc-enhanced-select' ),
				'default'      => array(),
				'desc_tip'     => true,
				'description'  => __(
					'By default all possible funding sources will be shown. You can disable some sources, if you wish.',
					'woocommerce-paypal-commerce-gateway'
				),
				'options'      => array(
					'card'       => _x( 'Credit or debit cards', 'Name of payment method', 'woocommerce-paypal-commerce-gateway' ),
					'credit'     => _x( 'PayPal Credit', 'Name of payment method', 'woocommerce-paypal-commerce-gateway' ),
					'sepa'       => _x( 'SEPA-Lastschrift', 'Name of payment method', 'woocommerce-paypal-commerce-gateway' ),
					'bancontact' => _x( 'Bancontact', 'Name of payment method', 'woocommerce-paypal-commerce-gateway' ),
					'eps'        => _x( 'eps', 'Name of payment method', 'woocommerce-paypal-commerce-gateway' ),
					'giropay'    => _x( 'giropay', 'Name of payment method', 'woocommerce-paypal-commerce-gateway' ),
					'ideal'      => _x( 'iDEAL', 'Name of payment method', 'woocommerce-paypal-commerce-gateway' ),
					'mybank'     => _x( 'MyBank', 'Name of payment method', 'woocommerce-paypal-commerce-gateway' ),
					'p24'        => _x( 'Przelewy24', 'Name of payment method', 'woocommerce-paypal-commerce-gateway' ),
					'sofort'     => _x( 'Sofort', 'Name of payment method', 'woocommerce-paypal-commerce-gateway' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'vault_enabled'              => array(
				'title'        => __( 'Vaulting', 'woocommerce-paypal-commerce-gateway' ),
				'type'         => 'checkbox',
				'desc_tip'     => true,
				'label'        => __( 'Enable vaulting', 'woocommerce-paypal-commerce-gateway' ),
				'description'  => __( 'Enables you to store payment tokens for subscriptions.', 'woocommerce-paypal-commerce-gateway' ),
				'default'      => true,
				'screens'      => array(
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),

			'logging_enabled'            => array(
				'title'        => __( 'Logging', 'woocommerce-paypal-commerce-gateway' ),
				'type'         => 'checkbox',
				'desc_tip'     => true,
				'label'        => __( 'Enable logging', 'woocommerce-paypal-commerce-gateway' ),
				'description'  => __( 'Enable logging of unexpected behavior. This can also log private data and should only be enabled in a development or stage environment.', 'woocommerce-paypal-commerce-gateway' ),
				'default'      => false,
				'screens'      => array(
					State::STATE_START,
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'all',
			),
			'prefix'                     => array(
				'title'        => __( 'Installation prefix', 'woocommerce-paypal-commerce-gateway' ),
				'type'         => 'text',
				'desc_tip'     => true,
				'description'  => __( 'If you use your PayPal account with more than one installation, please use a distinct prefix to seperate those installations. Please do not use numbers in your prefix.', 'woocommerce-paypal-commerce-gateway' ),
				'default'      => 'WC-',
				'screens'      => array(
					State::STATE_START,
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'all',
			),

			// General button styles
			'button_style_heading'       => array(
				'heading'      => __( 'Checkout', 'woocommerce-paypal-commerce-gateway' ),
				'type'         => 'ppcp-heading',
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_layout'              => array(
				'title'        => __( 'Button Layout', 'woocommerce-paypal-commerce-gateway' ),
				'type'         => 'select',
				'class'        => array( 'wc-enhanced-select' ),
				'default'      => 'vertical',
				'desc_tip'     => true,
				'description'  => __(
					'If additional funding sources are available to the buyer through PayPal, then multiple buttons are displayed in the space provided. Choose "vertical" for a dynamic list of alternative and local payment options, or "horizontal" when space is limited.',
					'woocommerce-paypal-commerce-gateway'
				),
				'options'      => array(
					'vertical'   => __( 'Vertical', 'woocommerce-paypal-commerce-gateway' ),
					'horizontal' => __( 'Horizontal', 'woocommerce-paypal-commerce-gateway' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_tagline'             => array(
				'title'        => __( 'Tagline', 'woocommerce-paypal-commerce-gateway' ),
				'type'         => 'checkbox',
				'default'      => true,
				'label'        => __( 'Enable tagline', 'woocommerce-paypal-commerce-gateway' ),
				'desc_tip'     => true,
				'description'  => __(
					'Add the tagline. This line will only show up, if you select a horizontal layout.',
					'woocommerce-paypal-commerce-gateway'
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_label'               => array(
				'title'        => __( 'Button Label', 'woocommerce-paypal-commerce-gateway' ),
				'type'         => 'select',
				'class'        => array( 'wc-enhanced-select' ),
				'default'      => 'paypal',
				'desc_tip'     => true,
				'description'  => __(
					'This controls the label on the primary button.',
					'woocommerce-paypal-commerce-gateway'
				),
				'options'      => array(
					'paypal'   => __( 'PayPal', 'woocommerce-paypal-commerce-gateway' ),
					'checkout' => __( 'PayPal Checkout', 'woocommerce-paypal-commerce-gateway' ),
					'buynow'   => __( 'PayPal Buy Now', 'woocommerce-paypal-commerce-gateway' ),
					'pay'      => __( 'Pay with PayPal', 'woocommerce-paypal-commerce-gateway' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_color'               => array(
				'title'        => __( 'Color', 'woocommerce-paypal-commerce-gateway' ),
				'type'         => 'select',
				'class'        => array( 'wc-enhanced-select' ),
				'default'      => 'gold',
				'desc_tip'     => true,
				'description'  => __(
					'Controls the background color of the primary button. Use "Gold" to leverage PayPal\'s recognition and preference, or change it to match your site design or aesthetic.',
					'woocommerce-paypal-commerce-gateway'
				),
				'options'      => array(
					'gold'   => __( 'Gold (Recommended)', 'woocommerce-paypal-commerce-gateway' ),
					'blue'   => __( 'Blue', 'woocommerce-paypal-commerce-gateway' ),
					'silver' => __( 'Silver', 'woocommerce-paypal-commerce-gateway' ),
					'black'  => __( 'Black', 'woocommerce-paypal-commerce-gateway' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_shape'               => array(
				'title'        => __( 'Shape', 'woocommerce-paypal-commerce-gateway' ),
				'type'         => 'select',
				'class'        => array( 'wc-enhanced-select' ),
				'default'      => 'rect',
				'desc_tip'     => true,
				'description'  => __(
					'The pill-shaped button\'s unique and powerful shape signifies PayPal in people\'s minds. Use the rectangular button as an alternative when pill-shaped buttons might pose design challenges.',
					'woocommerce-paypal-commerce-gateway'
				),
				'options'      => array(
					'pill' => __( 'Pill', 'woocommerce-paypal-commerce-gateway' ),
					'rect' => __( 'Rectangle', 'woocommerce-paypal-commerce-gateway' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'message_heading'            => array(
				'heading'      => __( 'Credit Messaging on Checkout', 'woocommerce-paypal-commerce-gateway' ),
				'type'         => 'ppcp-heading',
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array( 'messages' ),
				'gateway'      => 'paypal',
			),
			'message_enabled'            => array(
				'title'        => __( 'Enable message on Checkout', 'woocommerce-paypal-commerce-gateway' ),
				'type'         => 'checkbox',
				'label'        => __( 'Enable on Checkout', 'woocommerce-paypal-commerce-gateway' ),
				'default'      => true,
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array( 'messages' ),
				'gateway'      => 'paypal',
			),
			'message_layout'             => array(
				'title'        => __( 'Credit Messaging layout', 'woocommerce-paypal-commerce-gateway' ),
				'type'         => 'select',
				'class'        => array( 'wc-enhanced-select' ),
				'default'      => 'text',
				'desc_tip'     => true,
				'description'  => __(
					'The layout of the message.',
					'woocommerce-paypal-commerce-gateway'
				),
				'options'      => array(
					'text' => __( 'Text', 'woocommerce-paypal-commerce-gateway' ),
					'flex' => __( 'Flex', 'woocommerce-paypal-commerce-gateway' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array( 'messages' ),
				'gateway'      => 'paypal',
			),
			'message_logo'               => array(
				'title'        => __( 'Credit Messaging logo', 'woocommerce-paypal-commerce-gateway' ),
				'type'         => 'select',
				'class'        => array( 'wc-enhanced-select' ),
				'default'      => 'primary',
				'desc_tip'     => true,
				'description'  => __(
					'What logo the text message contains. Only applicable, when the layout style Text is used.',
					'woocommerce-paypal-commerce-gateway'
				),
				'options'      => array(
					'primary'     => __( 'Primary', 'woocommerce-paypal-commerce-gateway' ),
					'alternative' => __( 'Alternative', 'woocommerce-paypal-commerce-gateway' ),
					'inline'      => __( 'Inline', 'woocommerce-paypal-commerce-gateway' ),
					'none'        => __( 'None', 'woocommerce-paypal-commerce-gateway' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array( 'messages' ),
				'gateway'      => 'paypal',
			),
			'message_position'           => array(
				'title'        => __( 'Credit Messaging logo position', 'woocommerce-paypal-commerce-gateway' ),
				'type'         => 'select',
				'class'        => array( 'wc-enhanced-select' ),
				'default'      => 'left',
				'desc_tip'     => true,
				'description'  => __(
					'The position of the logo. Only applicable, when the layout style Text is used.',
					'woocommerce-paypal-commerce-gateway'
				),
				'options'      => array(
					'left'  => __( 'Left', 'woocommerce-paypal-commerce-gateway' ),
					'right' => __( 'Right', 'woocommerce-paypal-commerce-gateway' ),
					'top'   => __( 'Top', 'woocommerce-paypal-commerce-gateway' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array( 'messages' ),
				'gateway'      => 'paypal',
			),
			'message_color'              => array(
				'title'        => __( 'Credit Messaging text color', 'woocommerce-paypal-commerce-gateway' ),
				'type'         => 'select',
				'class'        => array( 'wc-enhanced-select' ),
				'default'      => 'black',
				'desc_tip'     => true,
				'description'  => __(
					'The color of the text. Only applicable, when the layout style Text is used.',
					'woocommerce-paypal-commerce-gateway'
				),
				'options'      => array(
					'black'      => __( 'Black', 'woocommerce-paypal-commerce-gateway' ),
					'white'      => __( 'White', 'woocommerce-paypal-commerce-gateway' ),
					'monochrome' => __( 'Monochrome', 'woocommerce-paypal-commerce-gateway' ),
					'grayscale'  => __( 'Grayscale', 'woocommerce-paypal-commerce-gateway' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array( 'messages' ),
				'gateway'      => 'paypal',
			),
			'message_flex_color'         => array(
				'title'        => __( 'Credit Messaging color', 'woocommerce-paypal-commerce-gateway' ),
				'type'         => 'select',
				'class'        => array( 'wc-enhanced-select' ),
				'default'      => 'blue',
				'desc_tip'     => true,
				'description'  => __(
					'The color of the text. Only applicable, when the layout style Flex is used.',
					'woocommerce-paypal-commerce-gateway'
				),
				'options'      => array(
					'blue'            => __( 'Blue', 'woocommerce-paypal-commerce-gateway' ),
					'black'           => __( 'Black', 'woocommerce-paypal-commerce-gateway' ),
					'white'           => __( 'White', 'woocommerce-paypal-commerce-gateway' ),
					'white-no-border' => __( 'White no border', 'woocommerce-paypal-commerce-gateway' ),
					'gray'            => __( 'Gray', 'woocommerce-paypal-commerce-gateway' ),
					'monochrome'      => __( 'Monochrome', 'woocommerce-paypal-commerce-gateway' ),
					'grayscale'       => __( 'Grayscale', 'woocommerce-paypal-commerce-gateway' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array( 'messages' ),
				'gateway'      => 'paypal',
			),
			'message_flex_ratio'         => array(
				'title'        => __( 'Credit Messaging ratio', 'woocommerce-paypal-commerce-gateway' ),
				'type'         => 'select',
				'class'        => array( 'wc-enhanced-select' ),
				'default'      => '1x1',
				'desc_tip'     => true,
				'description'  => __(
					'The color of the text. Only applicable, when the layout style Flex is used.',
					'woocommerce-paypal-commerce-gateway'
				),
				'options'      => array(
					'1x1'  => __( '1x1', 'woocommerce-paypal-commerce-gateway' ),
					'1x4'  => __( '1x4', 'woocommerce-paypal-commerce-gateway' ),
					'8x1'  => __( '8x1', 'woocommerce-paypal-commerce-gateway' ),
					'20x1' => __( '20x1', 'woocommerce-paypal-commerce-gateway' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array( 'messages' ),
				'gateway'      => 'paypal',
			),

			// Single product page
			'button_product_heading'     => array(
				'heading'      => __( 'Button on Single product', 'woocommerce-paypal-commerce-gateway' ),
				'type'         => 'ppcp-heading',
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_product_enabled'     => array(
				'title'        => __( 'Enable buttons on Single Product', 'woocommerce-paypal-commerce-gateway' ),
				'type'         => 'checkbox',
				'label'        => __( 'Enable on Single Product', 'woocommerce-paypal-commerce-gateway' ),
				'default'      => true,
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_product_layout'      => array(
				'title'        => __( 'Button Layout', 'woocommerce-paypal-commerce-gateway' ),
				'type'         => 'select',
				'class'        => array( 'wc-enhanced-select' ),
				'default'      => 'horizontal',
				'desc_tip'     => true,
				'description'  => __(
					'If additional funding sources are available to the buyer through PayPal, such as Venmo, then multiple buttons are displayed in the space provided. Choose "vertical" for a dynamic list of alternative and local payment options, or "horizontal" when space is limited.',
					'woocommerce-paypal-commerce-gateway'
				),
				'options'      => array(
					'vertical'   => __( 'Vertical', 'woocommerce-paypal-commerce-gateway' ),
					'horizontal' => __( 'Horizontal', 'woocommerce-paypal-commerce-gateway' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_product_tagline'     => array(
				'title'        => __( 'Tagline', 'woocommerce-paypal-commerce-gateway' ),
				'type'         => 'checkbox',
				'label'        => __( 'Enable tagline', 'woocommerce-paypal-commerce-gateway' ),
				'default'      => true,
				'desc_tip'     => true,
				'description'  => __(
					'Add the tagline. This line will only show up, if you select a horizontal layout.',
					'woocommerce-paypal-commerce-gateway'
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_product_label'       => array(
				'title'        => __( 'Button Label', 'woocommerce-paypal-commerce-gateway' ),
				'type'         => 'select',
				'class'        => array( 'wc-enhanced-select' ),
				'default'      => 'paypal',
				'desc_tip'     => true,
				'description'  => __(
					'This controls the label on the primary button.',
					'woocommerce-paypal-commerce-gateway'
				),
				'options'      => array(
					'paypal'   => __( 'PayPal', 'woocommerce-paypal-commerce-gateway' ),
					'checkout' => __( 'PayPal Checkout', 'woocommerce-paypal-commerce-gateway' ),
					'buynow'   => __( 'PayPal Buy Now', 'woocommerce-paypal-commerce-gateway' ),
					'pay'      => __( 'Pay with PayPal', 'woocommerce-paypal-commerce-gateway' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_product_color'       => array(
				'title'        => __( 'Color', 'woocommerce-paypal-commerce-gateway' ),
				'type'         => 'select',
				'class'        => array( 'wc-enhanced-select' ),
				'default'      => 'gold',
				'desc_tip'     => true,
				'description'  => __(
					'Controls the background color of the primary button. Use "Gold" to leverage PayPal\'s recognition and preference, or change it to match your site design or aesthetic.',
					'woocommerce-paypal-commerce-gateway'
				),
				'options'      => array(
					'gold'   => __( 'Gold (Recommended)', 'woocommerce-paypal-commerce-gateway' ),
					'blue'   => __( 'Blue', 'woocommerce-paypal-commerce-gateway' ),
					'silver' => __( 'Silver', 'woocommerce-paypal-commerce-gateway' ),
					'black'  => __( 'Black', 'woocommerce-paypal-commerce-gateway' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_product_shape'       => array(
				'title'        => __( 'Shape', 'woocommerce-paypal-commerce-gateway' ),
				'type'         => 'select',
				'class'        => array( 'wc-enhanced-select' ),
				'default'      => 'rect',
				'desc_tip'     => true,
				'description'  => __(
					'The pill-shaped button\'s unique and powerful shape signifies PayPal in people\'s minds. Use the rectangular button as an alternative when pill-shaped buttons might pose design challenges.',
					'woocommerce-paypal-commerce-gateway'
				),
				'options'      => array(
					'pill' => __( 'Pill', 'woocommerce-paypal-commerce-gateway' ),
					'rect' => __( 'Rectangle', 'woocommerce-paypal-commerce-gateway' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),

			'message_product_heading'    => array(
				'heading'      => __( 'Credit Messaging on Single product', 'woocommerce-paypal-commerce-gateway' ),
				'type'         => 'ppcp-heading',
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array( 'messages' ),
				'gateway'      => 'paypal',
			),
			'message_product_enabled'    => array(
				'title'        => __( 'Enable message on Single Product', 'woocommerce-paypal-commerce-gateway' ),
				'type'         => 'checkbox',
				'label'        => __( 'Enable on Single Product', 'woocommerce-paypal-commerce-gateway' ),
				'default'      => true,
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array( 'messages' ),
				'gateway'      => 'paypal',
			),
			'message_product_layout'     => array(
				'title'        => __( 'Credit Messaging layout', 'woocommerce-paypal-commerce-gateway' ),
				'type'         => 'select',
				'class'        => array( 'wc-enhanced-select' ),
				'default'      => 'text',
				'desc_tip'     => true,
				'description'  => __(
					'The layout of the message.',
					'woocommerce-paypal-commerce-gateway'
				),
				'options'      => array(
					'text' => __( 'Text', 'woocommerce-paypal-commerce-gateway' ),
					'flex' => __( 'Flex', 'woocommerce-paypal-commerce-gateway' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array( 'messages' ),
				'gateway'      => 'paypal',
			),
			'message_product_logo'       => array(
				'title'        => __( 'Credit Messaging logo', 'woocommerce-paypal-commerce-gateway' ),
				'type'         => 'select',
				'class'        => array( 'wc-enhanced-select' ),
				'default'      => 'primary',
				'desc_tip'     => true,
				'description'  => __(
					'What logo the text message contains. Only applicable, when the layout style Text is used.',
					'woocommerce-paypal-commerce-gateway'
				),
				'options'      => array(
					'primary'     => __( 'Primary', 'woocommerce-paypal-commerce-gateway' ),
					'alternative' => __( 'Alternative', 'woocommerce-paypal-commerce-gateway' ),
					'inline'      => __( 'Inline', 'woocommerce-paypal-commerce-gateway' ),
					'none'        => __( 'None', 'woocommerce-paypal-commerce-gateway' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array( 'messages' ),
				'gateway'      => 'paypal',
			),
			'message_product_position'   => array(
				'title'        => __( 'Credit Messaging logo position', 'woocommerce-paypal-commerce-gateway' ),
				'type'         => 'select',
				'class'        => array( 'wc-enhanced-select' ),
				'default'      => 'left',
				'desc_tip'     => true,
				'description'  => __(
					'The position of the logo. Only applicable, when the layout style Text is used.',
					'woocommerce-paypal-commerce-gateway'
				),
				'options'      => array(
					'left'  => __( 'Left', 'woocommerce-paypal-commerce-gateway' ),
					'right' => __( 'Right', 'woocommerce-paypal-commerce-gateway' ),
					'top'   => __( 'Top', 'woocommerce-paypal-commerce-gateway' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array( 'messages' ),
				'gateway'      => 'paypal',
			),
			'message_product_color'      => array(
				'title'        => __( 'Credit Messaging text color', 'woocommerce-paypal-commerce-gateway' ),
				'type'         => 'select',
				'class'        => array( 'wc-enhanced-select' ),
				'default'      => 'black',
				'desc_tip'     => true,
				'description'  => __(
					'The color of the text. Only applicable, when the layout style Text is used.',
					'woocommerce-paypal-commerce-gateway'
				),
				'options'      => array(
					'black'      => __( 'Black', 'woocommerce-paypal-commerce-gateway' ),
					'white'      => __( 'White', 'woocommerce-paypal-commerce-gateway' ),
					'monochrome' => __( 'Monochrome', 'woocommerce-paypal-commerce-gateway' ),
					'grayscale'  => __( 'Grayscale', 'woocommerce-paypal-commerce-gateway' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array( 'messages' ),
				'gateway'      => 'paypal',
			),
			'message_product_flex_color' => array(
				'title'        => __( 'Credit Messaging color', 'woocommerce-paypal-commerce-gateway' ),
				'type'         => 'select',
				'class'        => array( 'wc-enhanced-select' ),
				'default'      => 'blue',
				'desc_tip'     => true,
				'description'  => __(
					'The color of the text. Only applicable, when the layout style Flex is used.',
					'woocommerce-paypal-commerce-gateway'
				),
				'options'      => array(
					'blue'            => __( 'Blue', 'woocommerce-paypal-commerce-gateway' ),
					'black'           => __( 'Black', 'woocommerce-paypal-commerce-gateway' ),
					'white'           => __( 'White', 'woocommerce-paypal-commerce-gateway' ),
					'white-no-border' => __( 'White no border', 'woocommerce-paypal-commerce-gateway' ),
					'gray'            => __( 'Gray', 'woocommerce-paypal-commerce-gateway' ),
					'monochrome'      => __( 'Monochrome', 'woocommerce-paypal-commerce-gateway' ),
					'grayscale'       => __( 'Grayscale', 'woocommerce-paypal-commerce-gateway' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array( 'messages' ),
				'gateway'      => 'paypal',
			),
			'message_product_flex_ratio' => array(
				'title'        => __( 'Credit Messaging ratio', 'woocommerce-paypal-commerce-gateway' ),
				'type'         => 'select',
				'class'        => array( 'wc-enhanced-select' ),
				'default'      => '1x1',
				'desc_tip'     => true,
				'description'  => __(
					'The color of the text. Only applicable, when the layout style Flex is used.',
					'woocommerce-paypal-commerce-gateway'
				),
				'options'      => array(
					'1x1'  => __( '1x1', 'woocommerce-paypal-commerce-gateway' ),
					'1x4'  => __( '1x4', 'woocommerce-paypal-commerce-gateway' ),
					'8x1'  => __( '8x1', 'woocommerce-paypal-commerce-gateway' ),
					'20x1' => __( '20x1', 'woocommerce-paypal-commerce-gateway' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array( 'messages' ),
				'gateway'      => 'paypal',
			),

			// Mini cart settings
			'button_mini-cart_heading'   => array(
				'heading'      => __( 'Mini Cart', 'woocommerce-paypal-commerce-gateway' ),
				'type'         => 'ppcp-heading',
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_mini-cart_enabled'   => array(
				'title'        => __( 'Buttons on Mini Cart', 'woocommerce-paypal-commerce-gateway' ),
				'type'         => 'checkbox',
				'label'        => __( 'Enable on Mini Cart', 'woocommerce-paypal-commerce-gateway' ),
				'default'      => true,
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_mini-cart_layout'    => array(
				'title'        => __( 'Button Layout', 'woocommerce-paypal-commerce-gateway' ),
				'type'         => 'select',
				'class'        => array( 'wc-enhanced-select' ),
				'default'      => 'horizontal',
				'desc_tip'     => true,
				'description'  => __(
					'If additional funding sources are available to the buyer through PayPal, such as Venmo, then multiple buttons are displayed in the space provided. Choose "vertical" for a dynamic list of alternative and local payment options, or "horizontal" when space is limited.',
					'woocommerce-paypal-commerce-gateway'
				),
				'options'      => array(
					'vertical'   => __( 'Vertical', 'woocommerce-paypal-commerce-gateway' ),
					'horizontal' => __( 'Horizontal', 'woocommerce-paypal-commerce-gateway' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_mini-cart_tagline'   => array(
				'title'        => __( 'Tagline', 'woocommerce-paypal-commerce-gateway' ),
				'type'         => 'checkbox',
				'label'        => __( 'Enable tagline', 'woocommerce-paypal-commerce-gateway' ),
				'default'      => false,
				'desc_tip'     => true,
				'description'  => __(
					'Add the tagline. This line will only show up, if you select a horizontal layout.',
					'woocommerce-paypal-commerce-gateway'
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_mini-cart_label'     => array(
				'title'        => __( 'Button Label', 'woocommerce-paypal-commerce-gateway' ),
				'type'         => 'select',
				'class'        => array( 'wc-enhanced-select' ),
				'default'      => 'paypal',
				'desc_tip'     => true,
				'description'  => __(
					'This controls the label on the primary button.',
					'woocommerce-paypal-commerce-gateway'
				),
				'options'      => array(
					'paypal'   => __( 'PayPal', 'woocommerce-paypal-commerce-gateway' ),
					'checkout' => __( 'PayPal Checkout', 'woocommerce-paypal-commerce-gateway' ),
					'buynow'   => __( 'PayPal Buy Now', 'woocommerce-paypal-commerce-gateway' ),
					'pay'      => __( 'Pay with PayPal', 'woocommerce-paypal-commerce-gateway' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_mini-cart_color'     => array(
				'title'        => __( 'Color', 'woocommerce-paypal-commerce-gateway' ),
				'type'         => 'select',
				'class'        => array( 'wc-enhanced-select' ),
				'default'      => 'gold',
				'desc_tip'     => true,
				'description'  => __(
					'Controls the background color of the primary button. Use "Gold" to leverage PayPal\'s recognition and preference, or change it to match your site design or aesthetic.',
					'woocommerce-paypal-commerce-gateway'
				),
				'options'      => array(
					'gold'   => __( 'Gold (Recommended)', 'woocommerce-paypal-commerce-gateway' ),
					'blue'   => __( 'Blue', 'woocommerce-paypal-commerce-gateway' ),
					'silver' => __( 'Silver', 'woocommerce-paypal-commerce-gateway' ),
					'black'  => __( 'Black', 'woocommerce-paypal-commerce-gateway' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_mini-cart_shape'     => array(
				'title'        => __( 'Shape', 'woocommerce-paypal-commerce-gateway' ),
				'type'         => 'select',
				'class'        => array( 'wc-enhanced-select' ),
				'default'      => 'rect',
				'desc_tip'     => true,
				'description'  => __(
					'The pill-shaped button\'s unique and powerful shape signifies PayPal in people\'s minds. Use the rectangular button as an alternative when pill-shaped buttons might pose design challenges.',
					'woocommerce-paypal-commerce-gateway'
				),
				'options'      => array(
					'pill' => __( 'Pill', 'woocommerce-paypal-commerce-gateway' ),
					'rect' => __( 'Rectangle', 'woocommerce-paypal-commerce-gateway' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),

			// Cart settings
			'button_cart_heading'        => array(
				'heading'      => __( 'Cart', 'woocommerce-paypal-commerce-gateway' ),
				'type'         => 'ppcp-heading',
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_cart_enabled'        => array(
				'title'        => __( 'Buttons on Cart', 'woocommerce-paypal-commerce-gateway' ),
				'type'         => 'checkbox',
				'label'        => __( 'Enable on Cart', 'woocommerce-paypal-commerce-gateway' ),
				'default'      => true,
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_cart_layout'         => array(
				'title'        => __( 'Button Layout', 'woocommerce-paypal-commerce-gateway' ),
				'type'         => 'select',
				'class'        => array( 'wc-enhanced-select' ),
				'default'      => 'horizontal',
				'desc_tip'     => true,
				'description'  => __(
					'If additional funding sources are available to the buyer through PayPal, such as Venmo, then multiple buttons are displayed in the space provided. Choose "vertical" for a dynamic list of alternative and local payment options, or "horizontal" when space is limited.',
					'woocommerce-paypal-commerce-gateway'
				),
				'options'      => array(
					'vertical'   => __( 'Vertical', 'woocommerce-paypal-commerce-gateway' ),
					'horizontal' => __( 'Horizontal', 'woocommerce-paypal-commerce-gateway' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_cart_tagline'        => array(
				'title'        => __( 'Tagline', 'woocommerce-paypal-commerce-gateway' ),
				'type'         => 'checkbox',
				'label'        => __( 'Enable tagline', 'woocommerce-paypal-commerce-gateway' ),
				'default'      => true,
				'desc_tip'     => true,
				'description'  => __(
					'Add the tagline. This line will only show up, if you select a horizontal layout.',
					'woocommerce-paypal-commerce-gateway'
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_cart_label'          => array(
				'title'        => __( 'Button Label', 'woocommerce-paypal-commerce-gateway' ),
				'type'         => 'select',
				'class'        => array( 'wc-enhanced-select' ),
				'default'      => 'paypal',
				'desc_tip'     => true,
				'description'  => __(
					'This controls the label on the primary button.',
					'woocommerce-paypal-commerce-gateway'
				),
				'options'      => array(
					'paypal'   => __( 'PayPal', 'woocommerce-paypal-commerce-gateway' ),
					'checkout' => __( 'PayPal Checkout', 'woocommerce-paypal-commerce-gateway' ),
					'buynow'   => __( 'PayPal Buy Now', 'woocommerce-paypal-commerce-gateway' ),
					'pay'      => __( 'Pay with PayPal', 'woocommerce-paypal-commerce-gateway' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_cart_color'          => array(
				'title'        => __( 'Color', 'woocommerce-paypal-commerce-gateway' ),
				'type'         => 'select',
				'class'        => array( 'wc-enhanced-select' ),
				'default'      => 'gold',
				'desc_tip'     => true,
				'description'  => __(
					'Controls the background color of the primary button. Use "Gold" to leverage PayPal\'s recognition and preference, or change it to match your site design or aesthetic.',
					'woocommerce-paypal-commerce-gateway'
				),
				'options'      => array(
					'gold'   => __( 'Gold (Recommended)', 'woocommerce-paypal-commerce-gateway' ),
					'blue'   => __( 'Blue', 'woocommerce-paypal-commerce-gateway' ),
					'silver' => __( 'Silver', 'woocommerce-paypal-commerce-gateway' ),
					'black'  => __( 'Black', 'woocommerce-paypal-commerce-gateway' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_cart_shape'          => array(
				'title'        => __( 'Shape', 'woocommerce-paypal-commerce-gateway' ),
				'type'         => 'select',
				'class'        => array( 'wc-enhanced-select' ),
				'default'      => 'rect',
				'desc_tip'     => true,
				'description'  => __(
					'The pill-shaped button\'s unique and powerful shape signifies PayPal in people\'s minds. Use the rectangular button as an alternative when pill-shaped buttons might pose design challenges.',
					'woocommerce-paypal-commerce-gateway'
				),
				'options'      => array(
					'pill' => __( 'Pill', 'woocommerce-paypal-commerce-gateway' ),
					'rect' => __( 'Rectangle', 'woocommerce-paypal-commerce-gateway' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),

			'message_cart_heading'       => array(
				'heading'      => __( 'Credit Messaging on Cart', 'woocommerce-paypal-commerce-gateway' ),
				'type'         => 'ppcp-heading',
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array( 'messages' ),
				'gateway'      => 'paypal',
			),
			'message_cart_enabled'       => array(
				'title'        => __( 'Enable message on Single Product', 'woocommerce-paypal-commerce-gateway' ),
				'type'         => 'checkbox',
				'label'        => __( 'Enable on Single Product', 'woocommerce-paypal-commerce-gateway' ),
				'default'      => true,
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array( 'messages' ),
				'gateway'      => 'paypal',
			),
			'message_cart_layout'        => array(
				'title'        => __( 'Credit Messaging layout', 'woocommerce-paypal-commerce-gateway' ),
				'type'         => 'select',
				'class'        => array( 'wc-enhanced-select' ),
				'default'      => 'text',
				'desc_tip'     => true,
				'description'  => __(
					'The layout of the message.',
					'woocommerce-paypal-commerce-gateway'
				),
				'options'      => array(
					'text' => __( 'Text', 'woocommerce-paypal-commerce-gateway' ),
					'flex' => __( 'Flex', 'woocommerce-paypal-commerce-gateway' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array( 'messages' ),
				'gateway'      => 'paypal',
			),
			'message_cart_logo'          => array(
				'title'        => __( 'Credit Messaging logo', 'woocommerce-paypal-commerce-gateway' ),
				'type'         => 'select',
				'class'        => array( 'wc-enhanced-select' ),
				'default'      => 'primary',
				'desc_tip'     => true,
				'description'  => __(
					'What logo the text message contains. Only applicable, when the layout style Text is used.',
					'woocommerce-paypal-commerce-gateway'
				),
				'options'      => array(
					'primary'     => __( 'Primary', 'woocommerce-paypal-commerce-gateway' ),
					'alternative' => __( 'Alternative', 'woocommerce-paypal-commerce-gateway' ),
					'inline'      => __( 'Inline', 'woocommerce-paypal-commerce-gateway' ),
					'none'        => __( 'None', 'woocommerce-paypal-commerce-gateway' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array( 'messages' ),
				'gateway'      => 'paypal',
			),
			'message_cart_position'      => array(
				'title'        => __( 'Credit Messaging logo position', 'woocommerce-paypal-commerce-gateway' ),
				'type'         => 'select',
				'class'        => array( 'wc-enhanced-select' ),
				'default'      => 'left',
				'desc_tip'     => true,
				'description'  => __(
					'The position of the logo. Only applicable, when the layout style Text is used.',
					'woocommerce-paypal-commerce-gateway'
				),
				'options'      => array(
					'left'  => __( 'Left', 'woocommerce-paypal-commerce-gateway' ),
					'right' => __( 'Right', 'woocommerce-paypal-commerce-gateway' ),
					'top'   => __( 'Top', 'woocommerce-paypal-commerce-gateway' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array( 'messages' ),
				'gateway'      => 'paypal',
			),
			'message_cart_color'         => array(
				'title'        => __( 'Credit Messaging text color', 'woocommerce-paypal-commerce-gateway' ),
				'type'         => 'select',
				'class'        => array( 'wc-enhanced-select' ),
				'default'      => 'black',
				'desc_tip'     => true,
				'description'  => __(
					'The color of the text. Only applicable, when the layout style Text is used.',
					'woocommerce-paypal-commerce-gateway'
				),
				'options'      => array(
					'black'      => __( 'Black', 'woocommerce-paypal-commerce-gateway' ),
					'white'      => __( 'White', 'woocommerce-paypal-commerce-gateway' ),
					'monochrome' => __( 'Monochrome', 'woocommerce-paypal-commerce-gateway' ),
					'grayscale'  => __( 'Grayscale', 'woocommerce-paypal-commerce-gateway' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array( 'messages' ),
				'gateway'      => 'paypal',
			),
			'message_cart_flex_color'    => array(
				'title'        => __( 'Credit Messaging color', 'woocommerce-paypal-commerce-gateway' ),
				'type'         => 'select',
				'class'        => array( 'wc-enhanced-select' ),
				'default'      => 'blue',
				'desc_tip'     => true,
				'description'  => __(
					'The color of the text. Only applicable, when the layout style Flex is used.',
					'woocommerce-paypal-commerce-gateway'
				),
				'options'      => array(
					'blue'            => __( 'Blue', 'woocommerce-paypal-commerce-gateway' ),
					'black'           => __( 'Black', 'woocommerce-paypal-commerce-gateway' ),
					'white'           => __( 'White', 'woocommerce-paypal-commerce-gateway' ),
					'white-no-border' => __( 'White no border', 'woocommerce-paypal-commerce-gateway' ),
					'gray'            => __( 'Gray', 'woocommerce-paypal-commerce-gateway' ),
					'monochrome'      => __( 'Monochrome', 'woocommerce-paypal-commerce-gateway' ),
					'grayscale'       => __( 'Grayscale', 'woocommerce-paypal-commerce-gateway' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array( 'messages' ),
				'gateway'      => 'paypal',
			),
			'message_cart_flex_ratio'    => array(
				'title'        => __( 'Credit Messaging ratio', 'woocommerce-paypal-commerce-gateway' ),
				'type'         => 'select',
				'class'        => array( 'wc-enhanced-select' ),
				'default'      => '1x1',
				'desc_tip'     => true,
				'description'  => __(
					'The color of the text. Only applicable, when the layout style Flex is used.',
					'woocommerce-paypal-commerce-gateway'
				),
				'options'      => array(
					'1x1'  => __( '1x1', 'woocommerce-paypal-commerce-gateway' ),
					'1x4'  => __( '1x4', 'woocommerce-paypal-commerce-gateway' ),
					'8x1'  => __( '8x1', 'woocommerce-paypal-commerce-gateway' ),
					'20x1' => __( '20x1', 'woocommerce-paypal-commerce-gateway' ),
				),
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array( 'messages' ),
				'gateway'      => 'paypal',
			),

			'disable_cards'              => array(
				'title'        => __( 'Disable specific credit cards', 'woocommerce-paypal-commerce-gateway' ),
				'type'         => 'ppcp-multiselect',
				'class'        => array( 'wc-enhanced-select' ),
				'default'      => array(),
				'desc_tip'     => true,
				'description'  => __(
					'By default all possible credit cards will be accepted. You can disable some cards, if you wish.',
					'woocommerce-paypal-commerce-gateway'
				),
				'options'      => array(
					'visa'       => _x( 'Visa', 'Name of credit card', 'woocommerce-paypal-commerce-gateway' ),
					'mastercard' => _x( 'Mastercard', 'Name of credit card', 'woocommerce-paypal-commerce-gateway' ),
					'amex'       => _x( 'American Express', 'Name of credit card', 'woocommerce-paypal-commerce-gateway' ),
					'discover'   => _x( 'Discover', 'Name of credit card', 'woocommerce-paypal-commerce-gateway' ),
					'jcb'        => _x( 'JCB', 'Name of credit card', 'woocommerce-paypal-commerce-gateway' ),
					'elo'        => _x( 'Elo', 'Name of credit card', 'woocommerce-paypal-commerce-gateway' ),
					'hiper'      => _x( 'Hiper', 'Name of credit card', 'woocommerce-paypal-commerce-gateway' ),
				),
				'screens'      => array(
					State::STATE_ONBOARDED,
				),
				'requirements' => array(
					'dcc',
				),
				'gateway'      => 'dcc',
			),
			'card_icons'                 => array(
				'title'        => __( 'Show logo of the following credit cards', 'woocommerce-paypal-commerce-gateway' ),
				'type'         => 'ppcp-multiselect',
				'class'        => array( 'wc-enhanced-select' ),
				'default'      => array(),
				'desc_tip'     => true,
				'description'  => __(
					'Define, which cards you want to display in your checkout..',
					'woocommerce-paypal-commerce-gateway'
				),
				'options'      => array(
					'visa'       => _x( 'Visa', 'Name of credit card', 'woocommerce-paypal-commerce-gateway' ),
					'mastercard' => _x( 'Mastercard', 'Name of credit card', 'woocommerce-paypal-commerce-gateway' ),
					'amex'       => _x( 'American Express', 'Name of credit card', 'woocommerce-paypal-commerce-gateway' ),
					'discover'   => _x( 'Discover', 'Name of credit card', 'woocommerce-paypal-commerce-gateway' ),
					'jcb'        => _x( 'JCB', 'Name of credit card', 'woocommerce-paypal-commerce-gateway' ),
					'elo'        => _x( 'Elo', 'Name of credit card', 'woocommerce-paypal-commerce-gateway' ),
					'hiper'      => _x( 'Hiper', 'Name of credit card', 'woocommerce-paypal-commerce-gateway' ),
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
		return $fields;
	},

	'wcgateway.checkout.address-preset'            => static function( ContainerInterface $container ): CheckoutPayPalAddressPreset {

		return new CheckoutPayPalAddressPreset(
			$container->get( 'session.handler' )
		);
	},
	'wcgateway.url'                                => static function ( ContainerInterface $container ): string {
		return plugins_url(
			'/modules.local/ppcp-wc-gateway/',
			dirname( __FILE__, 3 ) . '/woocommerce-paypal-commerce-gateway.php'
		);
	},
	'wcgateway.endpoint.return-url'                => static function ( ContainerInterface $container ) : ReturnUrlEndpoint {
		$gateway  = $container->get( 'wcgateway.paypal-gateway' );
		$endpoint = $container->get( 'api.endpoint.order' );
		$prefix   = $container->get( 'api.prefix' );
		return new ReturnUrlEndpoint(
			$gateway,
			$endpoint,
			$prefix
		);
	},
);
