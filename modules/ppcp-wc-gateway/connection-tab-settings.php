<?php
/**
 * The services of the Gateway module.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway
 */

// phpcs:disable WordPress.Security.NonceVerification.Recommended

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway;

use Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Helper\DccApplies;
use WooCommerce\PayPalCommerce\Onboarding\Environment;
use WooCommerce\PayPalCommerce\Onboarding\Render\OnboardingOptionsRenderer;
use WooCommerce\PayPalCommerce\Onboarding\State;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;

return function ( ContainerInterface $container, array $fields ): array {

	$state = $container->get( 'onboarding.state' );
	assert( $state instanceof State );

	$dcc_applies = $container->get( 'api.helpers.dccapplies' );
	assert( $dcc_applies instanceof DccApplies );

	$is_shop_supports_dcc = $dcc_applies->for_country_currency() || $dcc_applies->for_wc_payments();

	$onboarding_options_renderer = $container->get( 'onboarding.render-options' );
	assert( $onboarding_options_renderer instanceof OnboardingOptionsRenderer );

	$module_url = $container->get( 'wcgateway.url' );

	$dash_icon_yes = '<span class="dashicons dashicons-yes"></span>';
	$dash_icon_no  = '<span class="dashicons dashicons-no"></span>';

	$connected_to_paypal_markup = sprintf(
		'<p>%1$s %2$s</p><p><button type="button" class="button ppcp-disconnect sandbox">%3$s</button></p>',
		esc_html__( 'Status: Connected', 'woocommerce-paypal-payments' ),
		$dash_icon_yes,
		esc_html__( 'Disconnect Account', 'woocommerce-paypal-payments' )
	);

	$settings = $container->get( 'wcgateway.settings' );
	assert( $settings instanceof Settings );

	$enabled_status_text  = esc_html__( 'Status: Enabled', 'woocommerce-paypal-payments' );
	$disabled_status_text = esc_html__( 'Status: Not yet enabled', 'woocommerce-paypal-payments' );

	$dcc_enabled = $settings->has( 'dcc_enabled' ) && $settings->get( 'dcc_enabled' );

	$dcc_button_text = $dcc_enabled
		? esc_html__( 'Disable Advanced Card Payments', 'woocommerce-paypal-payments' )
		: esc_html__( 'Enable Advanced Card Payments', 'woocommerce-paypal-payments' );

	$dcc_status = sprintf(
		'<p>%1$s %2$s</p><p><a href="%3$s" class="button">%4$s</a></p>',
		$dcc_enabled ? $enabled_status_text : $disabled_status_text,
		$dcc_enabled ? $dash_icon_yes : $dash_icon_no,
		admin_url( 'admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway&ppcp-tab=ppcp-credit-card-gateway' ),
		esc_html( $dcc_button_text )
	);

	$pui_enabled = $settings->has( 'products_pui_enabled' ) && $settings->get( 'products_pui_enabled' );

	$pui_button_text = $pui_enabled
		? esc_html__( 'Disable Pay Upon Invoice', 'woocommerce-paypal-payments' )
		: esc_html__( 'Enable Pay Upon Invoice', 'woocommerce-paypal-payments' );

	$pui_status = sprintf(
		'<p>%1$s %2$s</p><p><a href="%3$s" class="button">%4$s</a></p>',
		$pui_enabled ? $enabled_status_text : $disabled_status_text,
		$pui_enabled ? $dash_icon_yes : $dash_icon_no,
		admin_url( 'admin.php?page=wc-settings&tab=checkout&section=ppcp-pay-upon-invoice-gateway' ),
		esc_html( $pui_button_text )
	);

	$connection_fields = array(
		'ppcp_onboarading_header'            => array(
			'type'         => 'ppcp-text',
			'classes'      => array( 'ppcp-onboarding-element' ),
			'text'         => '
<div class="ppcp-onboarding-header">
	<div class="ppcp-onboarding-header-left">
		<img alt="PayPal" src="' . esc_url( $module_url ) . 'assets/images/paypal.png"/>
		<h2>The all-in-one checkout solution</h2>
	</div>
	<div class="ppcp-onboarding-header-right">
		<div class="ppcp-onboarding-header-paypal-logos">
			<img alt="PayPal" src="' . esc_url( $module_url ) . 'assets/images/paypal-button.svg"/>
			<img alt="Venmo" src="' . esc_url( $module_url ) . 'assets/images/venmo.svg"/>
			<img alt="Pay Later" src="' . esc_url( $module_url ) . 'assets/images/paylater.svg"/>
		</div>
		<div class="ppcp-onboarding-header-cards">
			<img alt="Visa" src="' . esc_url( $module_url ) . 'assets/images/visa-dark.svg"/>
			<img alt="Mastercard" src="' . esc_url( $module_url ) . 'assets/images/mastercard-dark.svg"/>
			<img alt="American Express" src="' . esc_url( $module_url ) . 'assets/images/amex.svg"/>
			<img alt="Discover" src="' . esc_url( $module_url ) . 'assets/images/discover.svg"/>
			<img alt="iDEAL" src="' . esc_url( $module_url ) . 'assets/images/ideal-dark.svg"/>
			<img alt="Sofort" src="' . esc_url( $module_url ) . 'assets/images/sofort.svg"/>
		</div>
	</div>
</div>',
			'screens'      => array(
				State::STATE_START,
				State::STATE_ONBOARDED,
			),
			'requirements' => array(),
			'gateway'      => 'connection',
		),
		'credentials_production_heading'     => array(
			'heading'      => __( 'API Credentials', 'woocommerce-paypal-payments' ),
			'type'         => 'ppcp-heading',
			'screens'      => array(
				State::STATE_ONBOARDED,
			),
			'state_from'   => Environment::PRODUCTION,
			'requirements' => array(),
			'gateway'      => 'connection',
		),
		'credentials_sandbox_heading'        => array(
			'heading'      => __( 'Sandbox API Credentials', 'woocommerce-paypal-payments' ),
			'type'         => 'ppcp-heading',
			'screens'      => array(
				State::STATE_ONBOARDED,
			),
			'state_from'   => Environment::SANDBOX,
			'requirements' => array(),
			'gateway'      => 'connection',
			'description'  => __( 'Your account is connected to sandbox, no real charging takes place. To accept live payments, turn off sandbox mode and connect your live PayPal account.', 'woocommerce-paypal-payments' ),
		),

		'ppcp_onboarading_options'           => array(
			'type'         => 'ppcp-text',
			'classes'      => array( 'ppcp-onboarding-element' ),
			'text'         => $onboarding_options_renderer->render( $is_shop_supports_dcc ),
			'raw'          => true,
			'screens'      => array(
				State::STATE_START,
				State::STATE_ONBOARDED,
			),
			'requirements' => array(),
			'gateway'      => 'connection',
		),

		// We need to have a button for each option (ppcp, express)
		// because currently the only documented way to use the PayPal onboarding JS library
		// is to have the buttons before loading the script.
		'ppcp_onboarding_production_ppcp'    => array(
			'type'         => 'ppcp_onboarding',
			'classes'      => array( 'ppcp-onboarding-element' ),
			'screens'      => array(
				State::STATE_START,
			),
			'state_from'   => Environment::PRODUCTION,
			'env'          => Environment::PRODUCTION,
			'products'     => array( 'PPCP' ),
			'requirements' => array(),
			'gateway'      => 'connection',
		),
		'ppcp_onboarding_production_express' => array(
			'type'         => 'ppcp_onboarding',
			'classes'      => array( 'ppcp-onboarding-element' ),
			'screens'      => array(
				State::STATE_START,
			),
			'state_from'   => Environment::PRODUCTION,
			'env'          => Environment::PRODUCTION,
			'products'     => array( 'EXPRESS_CHECKOUT' ),
			'requirements' => array(),
			'gateway'      => 'connection',
		),
		'ppcp_onboarding_sandbox_ppcp'       => array(
			'type'         => 'ppcp_onboarding',
			'classes'      => array( 'ppcp-onboarding-element' ),
			'screens'      => array(
				State::STATE_START,
			),
			'state_from'   => Environment::SANDBOX,
			'env'          => Environment::SANDBOX,
			'products'     => array( 'PPCP' ),
			'requirements' => array(),
			'gateway'      => 'connection',
			'description'  => __( 'Prior to accepting live payments, you can test payments on your WooCommerce platform in a safe PayPal sandbox environment.', 'woocommerce-paypal-payments' ),
		),
		'ppcp_onboarding_sandbox_express'    => array(
			'type'         => 'ppcp_onboarding',
			'classes'      => array( 'ppcp-onboarding-element' ),
			'screens'      => array(
				State::STATE_START,
			),
			'state_from'   => Environment::SANDBOX,
			'env'          => Environment::SANDBOX,
			'products'     => array( 'EXPRESS_CHECKOUT' ),
			'requirements' => array(),
			'gateway'      => 'connection',
			'description'  => __( 'Prior to accepting live payments, you can test payments on your WooCommerce platform in a safe PayPal sandbox environment.', 'woocommerce-paypal-payments' ),
		),

		'ppcp_disconnect_production'         => array(
			'title'        => __( 'Disconnect from PayPal', 'woocommerce-paypal-payments' ),
			'type'         => 'ppcp-text',
			'text'         => '<button type="button" class="button ppcp-disconnect production">' . esc_html__( 'Disconnect', 'woocommerce-paypal-payments' ) . '</button>',
			'screens'      => array(
				State::STATE_ONBOARDED,
			),
			'state_from'   => Environment::PRODUCTION,
			'env'          => Environment::PRODUCTION,
			'requirements' => array(),
			'gateway'      => 'connection',
			'description'  => __( 'Click to reset current credentials and use another account.', 'woocommerce-paypal-payments' ),
		),
		'ppcp_disconnect_sandbox'            => array(
			'title'        => __( 'Disconnect from PayPal Sandbox', 'woocommerce-paypal-payments' ),
			'type'         => 'ppcp-text',
			'text'         => $connected_to_paypal_markup,
			'screens'      => array(
				State::STATE_ONBOARDED,
			),
			'state_from'   => Environment::SANDBOX,
			'env'          => Environment::SANDBOX,
			'requirements' => array(),
			'gateway'      => 'connection',
			'description'  => __( 'Click to reset current credentials and use another account.', 'woocommerce-paypal-payments' ),
		),
		'toggle_manual_input'                => array(
			'type'         => 'ppcp-text',
			'text'         => '<button type="button" id="ppcp[toggle_manual_input]">' . __( 'Toggle to manual credential input', 'woocommerce-paypal-payments' ) . '</button>',
			'classes'      => array( 'ppcp-onboarding-element' ),
			'screens'      => array(
				State::STATE_START,
				State::STATE_ONBOARDED,
			),
			'requirements' => array(),
			'gateway'      => 'connection',
		),
		'ppcp_dcc_status'                    => array(
			'title'        => __( 'Advanced Credit & Debit Crad Payments', 'woocommerce-paypal-payments' ),
			'type'         => 'ppcp-text',
			'text'         => $dcc_status,
			'screens'      => array(
				State::STATE_ONBOARDED,
			),
			'requirements' => array(),
			'gateway'      => 'connection',
		),
		'ppcp_pui_status'                    => array(
			'title'        => __( 'Pay Upon Invoice', 'woocommerce-paypal-payments' ),
			'type'         => 'ppcp-text',
			'text'         => $pui_status,
			'screens'      => array(
				State::STATE_ONBOARDED,
			),
			'requirements' => array( 'pui_ready' ),
			'gateway'      => 'connection',
		),
		'error_label'                        => array(
			'type'         => 'ppcp-text',
			'text'         => '<label class="error" id="ppcp-form-errors-label"></label>',
			'classes'      => array( 'hide', 'ppcp-always-shown-element' ),
			'screens'      => array(
				State::STATE_START,
				State::STATE_ONBOARDED,
			),
			'requirements' => array(),
			'gateway'      => 'connection',
		),
		'sandbox_on'                         => array(
			'title'        => __( 'Sandbox', 'woocommerce-paypal-payments' ),
			'classes'      => array( 'ppcp-onboarding-element', 'ppcp-always-shown-element' ),
			'type'         => 'checkbox',
			'label'        => __( 'To test your WooCommerce installation, you can use the sandbox mode.', 'woocommerce-paypal-payments' ),
			'default'      => 0,
			'screens'      => array(
				State::STATE_START,
				State::STATE_ONBOARDED,
			),
			'requirements' => array(),
			'gateway'      => 'connection',
		),
		'merchant_email_production'          => array(
			'title'        => __( 'Live Email address', 'woocommerce-paypal-payments' ),
			'classes'      => array( State::STATE_ONBOARDED === $state->production_state() ? 'onboarded' : '', 'ppcp-always-shown-element' ),
			'type'         => 'text',
			'required'     => true,
			'desc_tip'     => true,
			'description'  => __( 'The email address of your PayPal account.', 'woocommerce-paypal-payments' ),
			'default'      => '',
			'screens'      => array(
				State::STATE_START,
				State::STATE_ONBOARDED,
			),
			'requirements' => array(),
			'gateway'      => 'connection',
		),
		'merchant_id_production'             => array(
			'title'        => __( 'Live Merchant Id', 'woocommerce-paypal-payments' ),
			'classes'      => array( State::STATE_ONBOARDED === $state->production_state() ? 'onboarded' : '', 'ppcp-always-shown-element' ),
			'type'         => 'ppcp-text-input',
			'desc_tip'     => true,
			'description'  => __( 'The merchant id of your account ', 'woocommerce-paypal-payments' ),
			'default'      => false,
			'screens'      => array(
				State::STATE_START,
				State::STATE_ONBOARDED,
			),
			'requirements' => array(),
			'gateway'      => 'connection',
		),
		'client_id_production'               => array(
			'title'        => __( 'Live Client Id', 'woocommerce-paypal-payments' ),
			'classes'      => array( State::STATE_ONBOARDED === $state->production_state() ? 'onboarded' : '', 'ppcp-always-shown-element' ),
			'type'         => 'ppcp-text-input',
			'desc_tip'     => true,
			'description'  => __( 'The client id of your api ', 'woocommerce-paypal-payments' ),
			'default'      => false,
			'screens'      => array(
				State::STATE_START,
				State::STATE_ONBOARDED,
			),
			'requirements' => array(),
			'gateway'      => 'connection',
		),
		'client_secret_production'           => array(
			'title'        => __( 'Live Secret Key', 'woocommerce-paypal-payments' ),
			'classes'      => array( State::STATE_ONBOARDED === $state->production_state() ? 'onboarded' : '', 'ppcp-always-shown-element' ),
			'type'         => 'ppcp-password',
			'desc_tip'     => true,
			'description'  => __( 'The secret key of your api', 'woocommerce-paypal-payments' ),
			'default'      => false,
			'screens'      => array(
				State::STATE_START,
				State::STATE_ONBOARDED,
			),
			'requirements' => array(),
			'gateway'      => 'connection',
		),

		'merchant_email_sandbox'             => array(
			'title'        => __( 'Sandbox Email address', 'woocommerce-paypal-payments' ),
			'classes'      => array( State::STATE_ONBOARDED === $state->sandbox_state() ? 'onboarded' : '', 'ppcp-always-shown-element' ),
			'type'         => 'text',
			'required'     => true,
			'desc_tip'     => true,
			'description'  => __( 'The email address of your PayPal account.', 'woocommerce-paypal-payments' ),
			'default'      => '',
			'screens'      => array(
				State::STATE_START,
				State::STATE_ONBOARDED,
			),
			'requirements' => array(),
			'gateway'      => 'connection',
		),
		'merchant_id_sandbox'                => array(
			'title'        => __( 'Sandbox Merchant Id', 'woocommerce-paypal-payments' ),
			'classes'      => array( State::STATE_ONBOARDED === $state->sandbox_state() ? 'onboarded' : '', 'ppcp-always-shown-element' ),
			'type'         => 'ppcp-text-input',
			'desc_tip'     => true,
			'description'  => __( 'The merchant id of your account ', 'woocommerce-paypal-payments' ),
			'default'      => false,
			'screens'      => array(
				State::STATE_START,
				State::STATE_ONBOARDED,
			),
			'requirements' => array(),
			'gateway'      => 'connection',
		),
		'client_id_sandbox'                  => array(
			'title'        => __( 'Sandbox Client Id', 'woocommerce-paypal-payments' ),
			'classes'      => array( State::STATE_ONBOARDED === $state->sandbox_state() ? 'onboarded' : '', 'ppcp-always-shown-element' ),
			'type'         => 'ppcp-text-input',
			'desc_tip'     => true,
			'description'  => __( 'The client id of your api ', 'woocommerce-paypal-payments' ),
			'default'      => false,
			'screens'      => array(
				State::STATE_START,
				State::STATE_ONBOARDED,
			),
			'requirements' => array(),
			'gateway'      => 'connection',
		),
		'client_secret_sandbox'              => array(
			'title'        => __( 'Sandbox Secret Key', 'woocommerce-paypal-payments' ),
			'classes'      => array( State::STATE_ONBOARDED === $state->sandbox_state() ? 'onboarded' : '', 'ppcp-always-shown-element' ),
			'type'         => 'ppcp-password',
			'desc_tip'     => true,
			'description'  => __( 'The secret key of your api', 'woocommerce-paypal-payments' ),
			'default'      => false,
			'screens'      => array(
				State::STATE_START,
				State::STATE_ONBOARDED,
			),
			'requirements' => array(),
			'gateway'      => 'connection',
		),
		'logging_enabled'                    => array(
			'title'        => __( 'Logging', 'woocommerce-paypal-payments' ),
			'type'         => 'checkbox',
			'desc_tip'     => true,
			'label'        => __( 'Enable logging. ', 'woocommerce-paypal-payments' ) .
				' <a href="' . admin_url( 'admin.php?page=wc-status&tab=logs' ) . '">' . __( 'View logs', 'woocommerce-paypal-payments' ) . '</a>',
			'description'  => __( 'Enable logging of unexpected behavior. This can also log private data and should only be enabled in a development or stage environment.', 'woocommerce-paypal-payments' ),
			'default'      => false,
			'screens'      => array(
				State::STATE_START,
				State::STATE_ONBOARDED,
			),
			'requirements' => array(),
			'gateway'      => 'connection',
		),
		'prefix'                             => array(
			'title'             => __( 'Invoice prefix', 'woocommerce-paypal-payments' ),
			'type'              => 'text',
			'desc_tip'          => true,
			'description'       => __( 'If you use your PayPal account with more than one installation, please use a distinct prefix to separate those installations. Please use only English letters and "-", "_" characters.', 'woocommerce-paypal-payments' ),
			'maxlength'         => 15,
			'custom_attributes' => array(
				'pattern' => '[a-zA-Z_-]+',
			),
			'default'           => ( static function (): string {
				$site_url = get_site_url( get_current_blog_id() );
				$hash = md5( $site_url );
				$letters = preg_replace( '~\d~', '', $hash ) ?? '';
				$prefix = substr( $letters, 0, 6 );
				return $prefix ? $prefix . '-' : '';
			} )(),
			'screens'           => array(
				State::STATE_START,
				State::STATE_ONBOARDED,
			),
			'requirements'      => array(),
			'gateway'           => 'connection',
		),
		'tracking_enabled'                   => array(
			'title'        => __( 'Tracking', 'woocommerce-paypal-payments' ),
			'type'         => 'checkbox',
			'desc_tip'     => true,
			'label'        => $container->get( 'wcgateway.settings.tracking-label' ),
			'description'  => __( 'Allows to send shipment tracking numbers to PayPal for PayPal transactions.', 'woocommerce-paypal-payments' ),
			'default'      => false,
			'screens'      => array(
				State::STATE_ONBOARDED,
			),
			'requirements' => array(),
			'gateway'      => 'connection',
			'input_class'  => $container->get( 'wcgateway.settings.should-disable-tracking-checkbox' ) ? array( 'ppcp-disabled-checkbox' ) : array(),
		),
	);

	return array_merge( $fields, $connection_fields );
};
