<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Tests\Integration\StoreSync;

use WooCommerce\PayPalCommerce\StoreSync\Schema\PayPalCart;
use WooCommerce\PayPalCommerce\StoreSync\Session\AgenticSessionHandler;
use WooCommerce\PayPalCommerce\StoreSync\Session\AgenticWcSession;
use WooCommerce\PayPalCommerce\StoreSync\Validation\StoreValidation;
use WooCommerce\PayPalCommerce\Tests\Integration\IntegrationMockedTestCase;

/**
 * @covers \WooCommerce\PayPalCommerce\StoreSync\Session\AgenticWcSession
 */
class AgenticWcSessionTest extends IntegrationMockedTestCase {

	private AgenticSessionHandler $session_handler;

	/**
	 * Session keys created during a test, removed in tearDown().
	 *
	 * @var string[]
	 */
	private array $created_session_keys = array();

	public function setUp(): void {
		parent::setUp();

		$this->session_handler = $this->getContainer()->get( 'agentic.session.handler' );
	}

	public function tearDown(): void {
		global $wpdb;

		foreach ( $this->created_session_keys as $session_key ) {
			$wpdb->delete( "{$wpdb->prefix}woocommerce_sessions", array( 'session_key' => $session_key ) );
		}
		$this->created_session_keys = array();

		parent::tearDown();
	}

	private function make_cart(): PayPalCart {
		$data = array(
			'items'          => array(
				array(
					'variant_id' => 'DUMMY_SIMPLE_SKU_01',
					'quantity'   => 1,
				),
			),
			'payment_method' => array( 'type' => 'paypal' ),
		);

		return PayPalCart::from_array( $data, new StoreValidation() );
	}

	private function create_session( string $ec_token = 'test-ec-token' ): string {
		$session_key = $this->session_handler->create_cart_session( $this->make_cart(), $ec_token );

		$this->created_session_keys[] = $session_key;

		return $session_key;
	}

	private function read_session_row( string $session_key ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}woocommerce_sessions WHERE session_key = %s",
				$session_key
			)
		);
	}

	/**
	 * GIVEN a cart created and saved via create_cart_session()
	 * WHEN load_cart_session() is called with the returned session id
	 * THEN the loaded cart holds the same items as the original cart
	 * AND the loaded ec_token matches the one passed at creation
	 */
	public function test_create_then_load_round_trips_cart_and_token(): void {
		$ec_token    = 'test-ec-token-roundtrip';
		$cart        = $this->make_cart();
		$session_key = $this->session_handler->create_cart_session( $cart, $ec_token );

		$this->created_session_keys[] = $session_key;

		$loaded = $this->session_handler->load_cart_session( $session_key );

		$this->assertIsArray( $loaded, 'A session that was just created must be loadable' );
		$this->assertSame( $ec_token, $loaded['ec_token'] );
		$this->assertInstanceOf( PayPalCart::class, $loaded['cart'] );
		$this->assertSame( $cart->to_array()['items'], $loaded['cart']->to_array()['items'] );
	}

	/**
	 * GIVEN a cart session created via create_cart_session()
	 * WHEN the underlying session row is read directly from the database
	 * THEN its session_expiry is set to a point in the future
	 */
	public function test_create_cart_session_stores_a_future_expiry(): void {
		$session_key = $this->create_session();

		$row = $this->read_session_row( $session_key );

		$this->assertNotNull( $row, 'Session row must exist right after creation' );
		$this->assertGreaterThan( time(), (int) $row->session_expiry );
	}

	/**
	 * GIVEN a session row whose expiry was moved closer to now, while still in the future
	 * WHEN load_cart_session() is called for that session
	 * THEN the session loads successfully
	 * AND the persisted session_expiry column is left unchanged by the load
	 *
	 * Loading only recalculates the in-memory expiration via
	 * WC_Session::set_session_expiration(); nothing marks the session dirty, so
	 * save_data() has nothing to persist and the stored row is untouched.
	 */
	public function test_loading_a_session_does_not_change_its_persisted_expiry(): void {
		$session_key = $this->create_session();

		global $wpdb;
		$near_future_expiry = time() + 10;
		$wpdb->update(
			"{$wpdb->prefix}woocommerce_sessions",
			array( 'session_expiry' => $near_future_expiry ),
			array( 'session_key' => $session_key )
		);

		$loaded = $this->session_handler->load_cart_session( $session_key );
		$this->assertIsArray( $loaded, 'Session must still be loadable while its expiry is in the future' );

		$row = $this->read_session_row( $session_key );
		$this->assertSame( $near_future_expiry, (int) $row->session_expiry );
	}

	/**
	 * GIVEN a session row whose expiry is in the past
	 * WHEN load_cart_session() is called for that session
	 * THEN it returns null
	 * AND destroy_cart_session() for the same id returns false
	 */
	public function test_expired_session_is_invisible_to_load_and_destroy(): void {
		$session_key = $this->create_session();

		global $wpdb;
		$wpdb->update(
			"{$wpdb->prefix}woocommerce_sessions",
			array( 'session_expiry' => time() - 10 ),
			array( 'session_key' => $session_key )
		);

		$this->assertNull( $this->session_handler->load_cart_session( $session_key ) );
		$this->assertFalse( $this->session_handler->destroy_cart_session( $session_key ) );
	}

	/**
	 * GIVEN an existing cart session
	 * WHEN destroy_cart_session() is called for that session
	 * THEN it returns true
	 * AND a following load_cart_session() call returns null
	 * AND no row remains in the sessions table for that key
	 */
	public function test_destroy_cart_session_removes_the_session(): void {
		$session_key = $this->create_session();

		$this->assertTrue( $this->session_handler->destroy_cart_session( $session_key ) );
		$this->assertNull( $this->session_handler->load_cart_session( $session_key ) );
		$this->assertNull( $this->read_session_row( $session_key ) );
	}

	/**
	 * GIVEN a freshly constructed AgenticWcSession instance
	 * WHEN checking the hooks that WC_Session_Handler::init_hooks() would register
	 * THEN none of those hooks carry this instance as a callback
	 *
	 * The global WC()->session legitimately carries the same hook/method pairs, so
	 * each check must target this specific instance as the callable, not just the
	 * hook name.
	 */
	public function test_new_instance_is_not_attached_to_any_wc_session_hooks(): void {
		$session = new AgenticWcSession();

		$hooks = array(
			'woocommerce_set_cart_cookies' => 'set_customer_session_cookie',
			'wp'                           => 'maybe_set_customer_session_cookie',
			'template_redirect'            => 'destroy_session_if_empty',
			'shutdown'                     => 'save_data',
			'wp_logout'                    => 'destroy_session',
		);

		foreach ( $hooks as $hook => $method ) {
			$this->assertFalse(
				has_action( $hook, array( $session, $method ) ),
				"{$hook} must not carry this AgenticWcSession instance as a callback"
			);
		}
	}

	/**
	 * GIVEN a completed create/load cycle through AgenticSessionHandler
	 * WHEN checking the global WooCommerce session
	 * THEN WC()->session is not an AgenticWcSession instance
	 */
	public function test_agentic_session_never_replaces_the_global_wc_session(): void {
		$session_key = $this->create_session();
		$this->session_handler->load_cart_session( $session_key );

		$this->assertNotInstanceOf( AgenticWcSession::class, WC()->session );
	}
}
