<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Tests\Integration\FraudProtection\Recaptcha;

use WC_Order;
use WC_Payment_Gateway;
use WC_Product_Simple;
use WP_REST_Request;
use WooCommerce\PayPalCommerce\FraudProtection\Recaptcha\RecaptchaIntegration;
use WooCommerce\PayPalCommerce\Tests\Integration\IntegrationMockedTestCase;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;

/**
 * Dispatches Store API order placement in-process through the REST server.
 *
 * @covers \WooCommerce\PayPalCommerce\FraudProtection\Recaptcha\Recaptcha
 */
class StoreApiCheckoutTest extends IntegrationMockedTestCase {

	private const MISSING_TOKEN_CODE   = 'ppcp_recaptcha_missing_token';
	private const UNPROTECTED_GATEWAY  = 'test-unprotected-gateway';
	private const BUYER_EMAIL          = 'buyer@example.com';
	private const RECAPTCHA_OPTION_KEY = 'woocommerce_' . RecaptchaIntegration::ID . '_settings';
	private const OPTION_ABSENT        = '__ppcp_option_absent__';

	/** @var WC_Product_Simple */
	private $product;

	/** @var WC_Order|null */
	private $pay_order;

	/** @var array<int|string, WC_Payment_Gateway> */
	private $original_gateways;

	/** @var callable */
	private $availability_filter;

	/** @var int */
	private $original_user_id;

	/** @var array<string, \WP_Hook> */
	private $original_filters = array();

	/** @var array<string, int> */
	private $original_actions = array();

	/** @var mixed */
	private $original_recaptcha_option;

	/** @var mixed */
	private $original_recaptcha_service;

	public function setUp(): void {
		parent::setUp();

		$this->snapshot_hooks();

		$this->original_user_id          = get_current_user_id();
		$this->original_recaptcha_option  = get_option( self::RECAPTCHA_OPTION_KEY, self::OPTION_ABSENT );
		$this->original_recaptcha_service = $this->recaptcha_integration()->settings;

		wp_set_current_user( 0 );

		update_option(
			self::RECAPTCHA_OPTION_KEY,
			array(
				'enabled'       => 'yes',
				'site_key_v3'   => 'site-key-v3',
				'secret_key_v3' => 'secret-key-v3',
				'site_key_v2'   => 'site-key-v2',
				'secret_key_v2' => 'secret-key-v2',
			)
		);
		$this->bootstrapModule();

		$this->enable_test_gateways();

		$this->product = new WC_Product_Simple();
		$this->product->set_name( 'reCAPTCHA test product' );
		$this->product->set_status( 'publish' );
		$this->product->set_regular_price( '10' );
		$this->product->set_virtual( true );
		$this->product->save();

		$this->reset_checkout_session();
		WC()->cart->add_to_cart( $this->product->get_id(), 1 );

		$GLOBALS['wp_rest_server'] = null;
		rest_get_server();
	}

	public function tearDown(): void {
		$GLOBALS['wp_rest_server'] = null;

		$this->reset_checkout_session();

		if ( $this->pay_order ) {
			$this->pay_order->delete( true );
		}
		$this->product->delete( true );

		remove_filter( 'woocommerce_available_payment_gateways', $this->availability_filter, PHP_INT_MAX );
		WC()->payment_gateways()->payment_gateways = $this->original_gateways;

		if ( self::OPTION_ABSENT === $this->original_recaptcha_option ) {
			delete_option( self::RECAPTCHA_OPTION_KEY );
		} else {
			update_option( self::RECAPTCHA_OPTION_KEY, $this->original_recaptcha_option );
		}
		$this->recaptcha_integration()->settings = $this->original_recaptcha_service;

		wp_set_current_user( $this->original_user_id );

		$this->restore_hooks();

		parent::tearDown();
	}

	/**
	 * Building the REST server fires `rest_api_init`, and WooCommerce then registers
	 * process-wide hooks. They are removed again so later tests start from the same hooks.
	 */
	private function snapshot_hooks(): void {
		global $wp_filter, $wp_actions;

		$this->original_filters = array();
		foreach ( $wp_filter as $hook_name => $hook ) {
			$this->original_filters[ $hook_name ] = clone $hook;
		}
		$this->original_actions = $wp_actions;
	}

	private function restore_hooks(): void {
		global $wp_filter, $wp_actions;

		$wp_filter  = $this->original_filters;
		$wp_actions = $this->original_actions;
	}

	/**
	 * GIVEN reCAPTCHA is active and the cart holds a product
	 * WHEN a guest places the order with a protected payment method and no reCAPTCHA token
	 * THEN the order is rejected because the token is missing
	 * AND this holds for every route spelling, body encoding and batch delivery
	 *
	 * @dataProvider checkout_request_variants_provider
	 */
	public function test_checkout_without_token_is_rejected_for_protected_payment_method( string $route, string $encoding ): void {
		[ $status, $body ] = $this->send( $route, $this->checkout_payload( PayPalGateway::ID ), $encoding );

		$this->assertSame( 400, $status );
		$this->assertSame( self::MISSING_TOKEN_CODE, $body['code'] ?? null );
	}

	public function checkout_request_variants_provider(): array {
		return array(
			'versioned route with JSON body'          => array( '/wc/store/v1/checkout', 'json' ),
			'unversioned route alias'                 => array( '/wc/store/checkout', 'json' ),
			'mixed-case route'                        => array( '/WC/STORE/V1/checkout', 'json' ),
			'form-encoded body params'                => array( '/wc/store/v1/checkout', 'form' ),
			'checkout as sub-request of a batch call' => array( '/wc/store/v1/checkout', 'batch' ),
		);
	}

	/**
	 * GIVEN an unpaid guest order exists
	 * WHEN the guest pays for it with a protected payment method and no reCAPTCHA token
	 * THEN the payment is rejected because the token is missing
	 *
	 * @dataProvider pay_for_order_routes_provider
	 */
	public function test_pay_for_order_without_token_is_rejected_for_protected_payment_method( string $route ): void {
		$this->pay_order = $this->create_unpaid_guest_order();

		[ $status, $body ] = $this->send(
			str_replace( '{order_id}', (string) $this->pay_order->get_id(), $route ),
			$this->checkout_payload( PayPalGateway::ID ),
			'json',
			array(
				'key'           => $this->pay_order->get_order_key(),
				'billing_email' => self::BUYER_EMAIL,
			)
		);

		$this->assertSame( 400, $status );
		$this->assertSame( self::MISSING_TOKEN_CODE, $body['code'] ?? null );
	}

	public function pay_for_order_routes_provider(): array {
		return array(
			'versioned route'   => array( '/wc/store/v1/checkout/{order_id}' ),
			'unversioned alias' => array( '/wc/store/checkout/{order_id}' ),
		);
	}

	/**
	 * GIVEN reCAPTCHA is active and the cart holds a product
	 * WHEN a guest places the order with a payment method that reCAPTCHA does not protect and no token
	 * THEN the order is accepted
	 */
	public function test_checkout_without_token_is_accepted_for_unprotected_payment_method(): void {
		[ $status, $body ] = $this->send(
			'/wc/store/v1/checkout',
			$this->checkout_payload( self::UNPROTECTED_GATEWAY ),
			'json'
		);

		$this->assertSame( 200, $status );
		$this->assertSame( self::UNPROTECTED_GATEWAY, $body['payment_method'] ?? null );
	}

	/**
	 * @param array<string, mixed> $payload
	 * @param array<string, string> $query
	 * @return array{0: int, 1: array<string, mixed>}
	 */
	private function send( string $route, array $payload, string $encoding, array $query = array() ): array {
		$nonce = wp_create_nonce( 'wc_store_api' );

		if ( 'batch' === $encoding ) {
			$request = new WP_REST_Request( 'POST', '/batch/v1' );
			$request->set_header( 'Content-Type', 'application/json' );
			$request->set_body(
				(string) wp_json_encode(
					array(
						'requests' => array(
							array(
								'method'  => 'POST',
								'path'    => $query ? $route . '?' . http_build_query( $query ) : $route,
								'headers' => array( 'Nonce' => $nonce ),
								'body'    => $payload,
							),
						),
					)
				)
			);

			$sub_response = rest_do_request( $request )->get_data()['responses'][0];

			return array( (int) $sub_response['status'], (array) $sub_response['body'] );
		}

		$request = new WP_REST_Request( 'POST', $route );
		$request->set_header( 'Nonce', $nonce );
		$request->set_query_params( $query );

		if ( 'form' === $encoding ) {
			$request->set_header( 'Content-Type', 'application/x-www-form-urlencoded' );
			$request->set_body_params( $payload );
		} else {
			$request->set_header( 'Content-Type', 'application/json' );
			$request->set_body( (string) wp_json_encode( $payload ) );
		}

		$response = rest_do_request( $request );

		return array( $response->get_status(), (array) $response->get_data() );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function checkout_payload( string $payment_method ): array {
		return array(
			'billing_address' => array(
				'first_name' => 'Jane',
				'last_name'  => 'Doe',
				'company'    => '',
				'address_1'  => '123 Main Street',
				'address_2'  => '',
				'city'       => 'Beverly Hills',
				'state'      => 'CA',
				'postcode'   => '90210',
				'country'    => 'US',
				'email'      => self::BUYER_EMAIL,
				'phone'      => '',
			),
			'payment_method'  => $payment_method,
		);
	}

	private function create_unpaid_guest_order(): WC_Order {
		$order = wc_create_order( array( 'status' => 'pending' ) );
		$order->add_product( $this->product, 1 );
		$order->set_billing_email( self::BUYER_EMAIL );
		$order->calculate_totals();
		$order->save();

		return $order;
	}

	private function recaptcha_integration(): RecaptchaIntegration {
		$integration = $this->getContainer()->get( 'fraud-protection.recaptcha.integration' );
		assert( $integration instanceof RecaptchaIntegration );

		return $integration;
	}

	private function reset_checkout_session(): void {
		WC()->cart->empty_cart();

		$draft_order_id = (int) WC()->session->get( 'store_api_draft_order', 0 );
		if ( $draft_order_id ) {
			$draft_order = wc_get_order( $draft_order_id );
			if ( $draft_order ) {
				$draft_order->delete( true );
			}
		}
		WC()->session->set( 'store_api_draft_order', null );
	}

	/**
	 * Replaces the store gateways with one protected and one unprotected gateway that are always available.
	 */
	private function enable_test_gateways(): void {
		$gateways = array(
			PayPalGateway::ID           => new StoreApiTestGateway( PayPalGateway::ID ),
			self::UNPROTECTED_GATEWAY   => new StoreApiTestGateway( self::UNPROTECTED_GATEWAY ),
		);

		$wc_gateways              = WC()->payment_gateways();
		$this->original_gateways  = $wc_gateways->payment_gateways;
		$wc_gateways->payment_gateways = array_values( $gateways );

		$this->availability_filter = static function ( $available ) use ( $gateways ) {
			return array_merge( (array) $available, $gateways );
		};
		add_filter( 'woocommerce_available_payment_gateways', $this->availability_filter, PHP_INT_MAX );
	}
}

class StoreApiTestGateway extends WC_Payment_Gateway {

	public function __construct( string $id ) {
		$this->id           = $id;
		$this->method_title = $id;
		$this->title        = $id;
		$this->enabled      = 'yes';
		$this->has_fields   = false;
	}

	public function process_payment( $order_id ) {
		return array(
			'result'   => 'success',
			'redirect' => '',
		);
	}
}
