<?php
/**
 * Onboarding REST controller.
 *
 * @package WooCommerce\PayPalCommerce\Onboarding
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Onboarding;

use Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;

/**
 * Exposes and handles REST routes related to onboarding.
 */
class OnboardingRESTController {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	protected $rest_namespace = 'wc-paypal/v1';

	/**
	 * REST base.
	 *
	 * @var string
	 */
	protected $rest_base = 'onboarding';

	/**
	 * Module container with access to plugin services.
	 *
	 * @var ContainerInterface
	 */
	private $container = null;

	/**
	 * Used to temporarily store URL arguments to add to the return URL associated to a signup link.
	 *
	 * @var array
	 */
	private $return_url_args = array();


	/**
	 * OnboardingRESTController constructor.
	 *
	 * @param ContainerInterface $container Module container with access to plugin services.
	 */
	public function __construct( ContainerInterface $container ) {
		$this->container = $container;
	}

	/**
	 * Registers REST routes under 'wc-paypal/v1/onboarding'.
	 * Specifically:
	 * - `/onboarding/get-params`, which returns information useful to display an onboarding button.
	 * - `/onboarding/get-status`, which returns information about the current environment and its onboarding state.
	 * - `/onboarding/set-credentials`, which allows setting merchant/API credentials.
	 */
	public function register_routes() {
		register_rest_route(
			$this->rest_namespace,
			'/' . $this->rest_base . '/get-params',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'get_params' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		register_rest_route(
			$this->rest_namespace,
			'/' . $this->rest_base . '/get-status',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_status' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		register_rest_route(
			$this->rest_namespace,
			'/' . $this->rest_base . '/set-credentials',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'set_credentials' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
	}

	/**
	 * Validate the requester's permissions.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return bool
	 */
	public function check_permission( $request ) {
		return current_user_can( 'install_plugins' );
	}

	/**
	 * Callback for the `/onboarding/get-params` endpoint.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return array
	 */
	public function get_params( $request ) {
		$params = $request->get_json_params();

		$environment = ( isset( $params['environment'] ) && in_array( $params['environment'], array( 'production', 'sandbox' ), true ) ) ? $params['environment'] : 'sandbox';

		return array(
			'scriptURL'               => trailingslashit( $this->container->get( 'onboarding.url' ) ) . 'assets/js/onboarding.js',
			'scriptData'              => $this->container->get( 'onboarding.assets' )->get_script_data(),
			'environment'             => $environment,
			'onboardCompleteCallback' => 'ppcp_onboarding_' . $environment . 'Callback',
			'signupLink'              => $this->generate_signup_link( $environment, ( ! empty( $params['returnUrlArgs'] ) ? $params['returnUrlArgs'] : array() ) ),
		);
	}

	/**
	 * Callback for the `/onboarding/get-status` endpoint.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return array
	 */
	public function get_status( $request ) {
		$environment = $this->container->get( 'onboarding.environment' );
		$state       = $this->container->get( 'onboarding.state' );

		return array(
			'environment' => $environment->current_environment(),
			'onboarded'   => ( $state->current_state() >= State::STATE_ONBOARDED ),
			'state'       => State::get_state_name( $state->current_state() ),
			'sandbox'     => array(
				'state'     => State::get_state_name( $state->sandbox_state() ),
				'onboarded' => ( $state->sandbox_state() >= State::STATE_ONBOARDED ),
			),
			'production'  => array(
				'state'     => State::get_state_name( $state->production_state() ),
				'onboarded' => ( $state->production_state() >= State::STATE_ONBOARDED ),
			),
		);
	}

	/**
	 * Callback for the `/onboarding/set-credentials` endpoint.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return WP_Error|array
	 */
	public function set_credentials( $request ) {
		static $credential_keys = array(
			'merchant_id',
			'merchant_email',
			'client_id',
			'client_secret',
		);

		// Sanitize params.
		$params = array_filter( array_map( 'trim', $request->get_json_params() ) );

		// Validate 'environment'.
		if ( empty( $params['environment'] ) || ! in_array( $params['environment'], array( 'sandbox', 'production' ), true ) ) {
			return new \WP_Error(
				'woocommerce_paypal_payments_invalid_environment',
				sprintf(
					/* translators: placeholder is an arbitrary string. */
					__( 'Environment "%s" is invalid. Use "sandbox" or "production".', 'woocommerce-paypal-payments' ),
					isset( $params['environment'] ) ? $params['environment'] : ''
				),
				array( 'status' => 400 )
			);
		}

		// Validate the other fields.
		$missing_keys = array_values( array_diff( $credential_keys, array_keys( $params ) ) );
		if ( $missing_keys ) {
			return new \WP_Error(
				'woocommerce_paypal_payments_credentials_incomplete',
				sprintf(
					/* translators: placeholder is a comma-separated list of fields. */
					__( 'Credentials are incomplete. Missing fields: %s.', 'woocommerce-paypal-payments' ),
					implode( ', ', $missing_keys )
				),
				array(
					'missing_fields' => $missing_keys,
					'status'         => 400,
				)
			);
		}

		$settings     = $this->container->get( 'wcgateway.settings' );
		$skip_persist = true;

		// Enable gateway.
		if ( ! $settings->has( 'enabled' ) || ! $settings->get( 'enabled' ) ) {
			$settings->set( 'enabled', true );
			$skip_persist = false;
		}

		foreach ( WC()->payment_gateways->payment_gateways() as $gateway ) {
			if ( PayPalGateway::ID === $gateway->id || CreditCardGateway::ID === $gateway->id ) {
				$gateway->update_option( 'enabled', 'yes' );
				break;
			}
		}

		// Update settings.
		$sandbox_on = ( 'sandbox' === $params['environment'] );
		if ( ! $settings->has( 'sandbox_on' ) || ( (bool) $settings->get( 'sandbox_on' ) !== $sandbox_on ) ) {
			$settings->set( 'sandbox_on', $sandbox_on );
			$skip_persist = false;
		}

		foreach ( $credential_keys as $key ) {
			$value   = $params[ $key ];
			$env_key = $key . '_' . $params['environment'];

			if ( ! $settings->has( $key ) || ! $settings->has( $env_key ) || $settings->get( $key ) !== $value || $settings->get( $env_key ) !== $value ) {
				$settings->set( $key, $value );
				$settings->set( $env_key, $value );
				$skip_persist = false;
			}
		}

		if ( $skip_persist ) {
			return array();
		}

		$settings->set( 'products_dcc_enabled', null );

		if ( ! $settings->persist() ) {
			return new \WP_Error(
				'woocommerce_paypal_payments_credentials_not_saved',
				__( 'An error occurred while saving the credentials.', 'woocommerce-paypal-payments' ),
				array(
					'status' => 500,
				)
			);
		}

		$webhook_registrar = $this->container->get( 'webhook.registrar' );
		$webhook_registrar->unregister();
		$webhook_registrar->register();

		return array();
	}

	/**
	 * Appends URL parameters stored in this class to a given URL.
	 *
	 * @hooked woocommerce_paypal_payments_partner_config_override_return_url - 10
	 * @param string $url URL.
	 * @return string The URL with the stored URL parameters added to it.
	 */
	public function add_args_to_return_url( $url ) {
		return add_query_arg( $this->return_url_args, $url );
	}

	/**
	 * Generates a signup link for onboarding for a given environment and optionally adding certain URL arguments
	 * to the URL users are redirected after completing the onboarding flow.
	 *
	 * @param string $environment The environment to use. Either 'sandbox' or 'production'. Defaults to 'sandbox'.
	 * @param array  $url_args An array of URL arguments to add to the return URL via {@link add_query_arg()}.
	 * @return string
	 */
	private function generate_signup_link( $environment = 'sandbox', $url_args = array() ) {
		$this->return_url_args = ( ! empty( $url_args ) && is_array( $url_args ) ) ? $url_args : array();

		if ( $this->return_url_args ) {
			add_filter( 'woocommerce_paypal_payments_partner_config_override_return_url', array( $this, 'add_args_to_return_url' ) );
		}

		$link = $this->container->get( 'onboarding.render' )->get_signup_link( 'production' === $environment );

		if ( $this->return_url_args ) {
			remove_filter( 'woocommerce_paypal_payments_partner_config_override_return_url', array( $this, 'add_args_to_return_url' ) );
			$this->return_url_args = array();
		}

		return $link;
	}

}
