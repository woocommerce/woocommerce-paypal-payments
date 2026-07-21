<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Session;

use Mockery;
use ReflectionProperty;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\StoreSync\Schema\PayPalCart;
use WooCommerce\PayPalCommerce\StoreSync\Validation\StoreValidation;

/**
 * @covers \WooCommerce\PayPalCommerce\StoreSync\Session\AgenticSessionHandler
 */
class AgenticSessionHandlerTest extends TestCase {

	/**
	 * Build a minimal PayPalCart from a single-item payload so tests have a
	 * real object to pass into update_cart_session().
	 *
	 * @return PayPalCart
	 */
	private function make_cart(): PayPalCart {
		$data = array(
			'items'          => array(
				array(
					'item_id'  => 'ITEM-001',
					'quantity' => 1,
					'price'    => array(
						'currency_code' => 'USD',
						'value'         => '10.00',
					),
				),
			),
			'payment_method' => array( 'type' => 'paypal' ),
		);

		return PayPalCart::from_array( $data, new StoreValidation() );
	}

	/**
	 * Build a partial mock of AgenticSessionHandler whose constructor is bypassed,
	 * and inject a mock AgenticWcSession via reflection.
	 *
	 * @param array  $existing_session   Data returned by load_cart_session().
	 * @param string $session_id         The session ID used in the stub.
	 * @return array{handler: AgenticSessionHandler, session: AgenticWcSession&\Mockery\MockInterface}
	 */
	private function make_handler_with_session( array $existing_session, string $session_id ): array {
		/** @var AgenticSessionHandler&\Mockery\MockInterface $handler */
		$handler = Mockery::mock( AgenticSessionHandler::class )->makePartial();

		$handler->allows( 'load_cart_session' )
			->with( $session_id )
			->andReturn( $existing_session );

		/** @var AgenticWcSession&\Mockery\MockInterface $session_mock */
		$session_mock = Mockery::mock( AgenticWcSession::class );
		$session_mock->allows( 'save_data' );

		$ref = new ReflectionProperty( AgenticSessionHandler::class, 'session' );
		$ref->setAccessible( true );
		$ref->setValue( $handler, $session_mock );

		return array(
			'handler' => $handler,
			'session' => $session_mock,
		);
	}

	/**
	 * GIVEN a cart session that already holds an ec_token
	 * WHEN update_cart_session() is called without supplying a new token (null)
	 * THEN the existing token is preserved in the data written to the session store
	 * AND save_data() is called to persist the change
	 */
	public function test_update_preserves_existing_token_when_no_new_token_given(): void {
		$session_id     = 't_session_preserve_token';
		$existing_token = 'EC-OLD-TOKEN-123';
		$cart           = $this->make_cart();

		$existing_session = array(
			'cart'     => $cart,
			'ec_token' => $existing_token,
			'created'  => 12345,
		);

		$parts   = $this->make_handler_with_session( $existing_session, $session_id );
		$handler = $parts['handler'];
		$session = $parts['session'];

		$session->shouldReceive( 'set' )
			->once()
			->withArgs(
				function ( $key, $data ) use ( $existing_token ) {
					return $key === 'ppcp_agentic'
						&& is_array( $data )
						&& $data['ec_token'] === $existing_token;
				}
			);

		$result = $handler->update_cart_session( $session_id, $cart );

		$this->assertTrue( $result );
	}

	/**
	 * GIVEN a cart session that already holds an ec_token
	 * WHEN update_cart_session() is called with a new token string
	 * THEN the new token replaces the old one in the data written to the session store
	 * AND the old token is no longer present in the persisted data
	 */
	public function test_update_replaces_token_when_new_token_is_provided(): void {
		$session_id    = 't_session_replace_token';
		$old_token     = 'EC-OLD-TOKEN-456';
		$new_token     = 'EC-NEW-TOKEN-789';
		$cart          = $this->make_cart();

		$existing_session = array(
			'cart'     => $cart,
			'ec_token' => $old_token,
			'created'  => 67890,
		);

		$parts   = $this->make_handler_with_session( $existing_session, $session_id );
		$handler = $parts['handler'];
		$session = $parts['session'];

		$session->shouldReceive( 'set' )
			->once()
			->withArgs(
				function ( $key, $data ) use ( $new_token, $old_token ) {
					return $key === 'ppcp_agentic'
						&& is_array( $data )
						&& $data['ec_token'] === $new_token
						&& $data['ec_token'] !== $old_token;
				}
			);

		$result = $handler->update_cart_session( $session_id, $cart, $new_token );

		$this->assertTrue( $result );
	}
}
