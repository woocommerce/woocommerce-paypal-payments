<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Tests\Integration\StoreSync;

use Mockery;
use Psr\Log\NullLogger;
use WC_Cache_Helper;
use WC_Tax;
use WP_REST_Request;
use WP_REST_Response;
use WooCommerce\PayPalCommerce\StoreSync\Endpoint\ReplaceCartEndpoint;
use WooCommerce\PayPalCommerce\StoreSync\Helper\PayPalOrderManager;
use WooCommerce\PayPalCommerce\StoreSync\Schema\PayPalCart;
use WooCommerce\PayPalCommerce\StoreSync\Session\AgenticSessionHandler;
use WooCommerce\PayPalCommerce\StoreSync\Validation\StoreValidation;
use WooCommerce\PayPalCommerce\Tests\Integration\IntegrationMockedTestCase;

/**
 * @covers \WooCommerce\PayPalCommerce\StoreSync\Endpoint\ReplaceCartEndpoint
 */
class ReplaceCartEndpointTest extends IntegrationMockedTestCase {

	private ReplaceCartEndpoint $endpoint;

	private AgenticSessionHandler $session_handler;

	private int $tax_rate_id = 0;

	/** @var array<string, mixed> */
	private array $original_options = array();

	public function setUp(): void {
		parent::setUp();

		$c = $this->getContainer();

		$this->session_handler = $c->get( 'agentic.session.handler' );

		$order_manager = Mockery::mock( PayPalOrderManager::class );
		$order_manager->allows( 'update_order' )->andReturn( true );

		$this->endpoint = new ReplaceCartEndpoint(
			$c->get( 'agentic.auth.provider' ),
			$this->session_handler,
			$c->get( 'agentic.helper.session-manager' ),
			$c->get( 'agentic.response.factory' ),
			$c->get( 'agentic.validation.processor' ),
			new NullLogger(),
			$order_manager,
			$c->get( 'agentic.store.data' )
		);

		$this->original_options = array();
		foreach ( array( 'woocommerce_calc_taxes', 'woocommerce_prices_include_tax', 'woocommerce_tax_based_on' ) as $name ) {
			$this->original_options[ $name ] = get_option( $name );
		}

		// 10.00 incl. 7.25% tax is 9.324 ex-tax: the rounded unit price loses a fraction of a cent.
		update_option( 'woocommerce_calc_taxes', 'yes' );
		update_option( 'woocommerce_prices_include_tax', 'yes' );
		update_option( 'woocommerce_tax_based_on', 'shipping' );

		$this->tax_rate_id = WC_Tax::_insert_tax_rate(
			array(
				'tax_rate_country'  => '',
				'tax_rate_state'    => '',
				'tax_rate'          => '7.2500',
				'tax_rate_name'     => 'PCP Test Tax',
				'tax_rate_priority' => '1',
				'tax_rate_compound' => 0,
				'tax_rate_shipping' => 0,
				'tax_rate_order'    => '1',
				'tax_rate_class'    => '',
			)
		);
		WC_Cache_Helper::get_transient_version( 'taxes', true );
	}

	public function tearDown(): void {
		WC_Tax::_delete_tax_rate( $this->tax_rate_id );
		WC_Cache_Helper::get_transient_version( 'taxes', true );

		foreach ( $this->original_options as $name => $value ) {
			update_option( $name, $value );
		}

		parent::tearDown();
	}

	/**
	 * GIVEN prices include tax and a 7.25% tax rate applies
	 * AND a cart session was created with a known ec_token
	 * WHEN replace_cart() is called with a valid replacement payload
	 * THEN the response status is 200
	 * AND payment_method.token equals the ec_token from the original session (not the request body)
	 * AND id matches the cart session id
	 * AND status is INCOMPLETE
	 * AND validation_status is VALID with no validation_issues
	 * AND totals contains subtotal, shipping, tax and total with currency_code and value
	 * AND available_shipping_options has at least one entry with exactly one selected option
	 * AND customer and shipping_address keys exist in the response
	 * AND the sum of item price × quantity equals the subtotal (in cents)
	 * AND subtotal + shipping + tax - discount equals the total (in cents)
	 */
	public function test_replace_cart_preserves_ec_token_in_response(): void {
		$ec_token = 'test-preserved-session-ec-token';

		// Data for create cart.
		$cart_data_1 = array(
			'items'          => array(
				array(
					'variant_id' => 'DUMMY_SIMPLE_SKU_01',
					'quantity'   => 1,
				),
			),
			'payment_method' => array( 'type' => 'paypal' ),
		);
		// Replacement payload — no token in payment_method; token must come from session.
		$cart_data_2 = array(
			'items'            => array(
				array(
					'variant_id' => 'DUMMY_SIMPLE_SKU_01',
					'quantity'   => 2,
				),
			),
			'shipping_address' => array(
				'address_line_1' => '456 Fictional St',
				'admin_area_2'   => 'San Jose',
				'postal_code'    => '90210',
				'country_code'   => 'US',
			),
			'payment_method'   => array( 'type' => 'paypal' ),
		);

		$initial_cart = PayPalCart::from_array( $cart_data_1, new StoreValidation() );

		$cart_id = $this->session_handler->create_cart_session( $initial_cart, $ec_token );

		$body = (string) json_encode( $cart_data_2 );

		$request = new WP_REST_Request( 'PUT' );
		$request->set_param( 'cart_id', $cart_id );
		$request->set_body( $body );
		$request->set_header( 'Content-Type', 'application/json' );

		$response = $this->endpoint->replace_cart( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();

		$this->assertSame(
			$ec_token,
			$data['payment_method']['token'] ?? null,
			'Replace cart response must include the preserved ec_token from the session'
		);

		$this->assertSame( $cart_id, $data['id'] ?? null, 'Response must echo the cart id' );

		$this->assertSame( 'INCOMPLETE', $data['status'] ?? null );

		$this->assertSame( 'VALID', $data['validation_status'] ?? null );
		$this->assertIsArray( $data['validation_issues'] ?? null );
		$this->assertEmpty( $data['validation_issues'] );

		$this->assertArrayHasKey( 'totals', $data, 'Response must include calculated totals' );
		foreach ( array( 'subtotal', 'shipping', 'tax', 'total' ) as $key ) {
			$this->assertArrayHasKey( $key, $data['totals'], "totals must include $key" );
			$this->assertNotEmpty( $data['totals'][ $key ]['currency_code'] ?? '' );
			$this->assertNotEmpty( $data['totals'][ $key ]['value'] ?? '' );
		}

		$shipping_options = $data['available_shipping_options'] ?? array();
		$this->assertIsArray( $shipping_options );
		$this->assertGreaterThanOrEqual( 1, count( $shipping_options ), 'At least one shipping option must be present' );
		$selected = array_filter( $shipping_options, fn( $opt ) => ( $opt['is_selected'] ?? false ) === true );
		$this->assertCount( 1, $selected, 'Exactly one shipping option must be selected' );

		$this->assertArrayHasKey( 'customer', $data, 'Response must include customer' );
		$this->assertArrayHasKey( 'shipping_address', $data, 'Response must include shipping_address' );

		$items_subtotal_cents = 0;
		foreach ( $data['items'] as $item ) {
			$price    = (float) ( $item['price']['value'] ?? 0 );
			$quantity = (int) ( $item['quantity'] ?? 0 );
			$items_subtotal_cents += (int) round( $price * 100 ) * $quantity;
		}
		$subtotal_cents = (int) round( (float) ( $data['totals']['subtotal']['value'] ?? 0 ) * 100 );
		$this->assertSame( $subtotal_cents, $items_subtotal_cents, 'Sum of item price × quantity must equal totals.subtotal' );

		$to_cents = static fn( string $key ): int => (int) round( (float) ( $data['totals'][ $key ]['value'] ?? 0 ) * 100 );

		$this->assertSame(
			$to_cents( 'total' ),
			$to_cents( 'subtotal' ) + $to_cents( 'shipping' ) + $to_cents( 'tax' ) - $to_cents( 'discount' ),
			'subtotal + shipping + tax - discount must equal totals.total'
		);
	}
}
