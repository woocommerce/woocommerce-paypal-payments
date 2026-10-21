<?php

declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\SavePaymentMethods\Service;

use WC_Session_Handler;
use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\when;

class SetupTokenBindingTest extends TestCase {

	private const SESSION_KEY = 'ppcp_setup_token_bindings';

	/** @var WC_Session_Handler */
	private $session;

	/** @var WC_Session_Handler|null */
	private $exposed_session;

	/** @var int */
	private $current_user_id = 5;

	/** @var SetupTokenBinding */
	private $sut;

	public function setUp(): void {
		parent::setUp();

		$this->session = $this->create_session( true );
		$this->sut     = new SetupTokenBinding();

		when( 'get_current_user_id' )->alias(
			function (): int {
				return $this->current_user_id;
			}
		);
		$this->exposed_session = $this->session;
		when( 'WC' )->alias(
			function (): object {
				return (object) array( 'session' => $this->exposed_session );
			}
		);
	}

	private function expose_session( ?WC_Session_Handler $session ): void {
		$this->exposed_session = $session;
	}

	/**
	 * In-memory session that records whether a session cookie was requested.
	 */
	private function create_session( bool $has_session ): WC_Session_Handler {
		return new class( $has_session ) extends WC_Session_Handler {
			public $data = array();
			public $cookie_set = false;
			private $has;

			public function __construct( bool $has ) {
				$this->has = $has;
			}

			public function get( $key, $default = null ) {
				return $this->data[ $key ] ?? $default;
			}

			public function set( $key, $value ): void {
				$this->data[ $key ] = $value;
			}

			public function has_session(): bool {
				return $this->has;
			}

			public function set_customer_session_cookie( $set ): void {
				$this->cookie_set = (bool) $set;
				$this->has        = true;
			}
		};
	}

	/**
	 * GIVEN a setup token created by the current customer
	 * WHEN the same customer exchanges it
	 * THEN the exchange is allowed
	 */
	public function test_token_is_accepted_for_the_customer_that_created_it(): void {
		$this->sut->bind( 'token-1' );

		$this->assertTrue( $this->sut->consume( 'token-1' ) );
	}

	/**
	 * GIVEN a setup token that was already exchanged once
	 * WHEN it is exchanged again
	 * THEN the exchange is refused
	 */
	public function test_token_cannot_be_exchanged_twice(): void {
		$this->sut->bind( 'token-1' );
		$this->sut->consume( 'token-1' );

		$this->assertFalse( $this->sut->consume( 'token-1' ) );
	}

	/**
	 * GIVEN a setup token created by one customer
	 * WHEN a different customer tries to exchange it
	 * THEN the exchange is refused
	 * AND the token cannot be used afterwards by its creator either
	 */
	public function test_token_is_refused_for_a_different_customer(): void {
		$this->current_user_id = 5;
		$this->sut->bind( 'token-1' );

		$this->current_user_id = 6;
		$this->assertFalse( $this->sut->consume( 'token-1' ) );

		$this->current_user_id = 5;
		$this->assertFalse( $this->sut->consume( 'token-1' ) );
	}

	/**
	 * GIVEN a guest (user id 0) created a setup token
	 * WHEN a logged-in customer in another session tries to exchange it
	 * THEN the exchange is refused
	 */
	public function test_guest_token_is_refused_for_a_logged_in_customer(): void {
		$this->current_user_id = 0;
		$this->sut->bind( 'token-1' );

		$this->current_user_id = 5;

		$this->assertFalse( $this->sut->consume( 'token-1' ) );
	}

	/**
	 * GIVEN a setup token that was never created through this site
	 * WHEN it is exchanged
	 * THEN the exchange is refused
	 */
	public function test_unknown_token_is_refused(): void {
		$this->sut->bind( 'token-1' );

		$this->assertFalse( $this->sut->consume( 'other-token' ) );
	}

	/**
	 * GIVEN a setup token whose validity window has passed
	 * WHEN the creator exchanges it
	 * THEN the exchange is refused
	 */
	public function test_expired_token_is_refused(): void {
		$this->session->data[ self::SESSION_KEY ] = array(
			hash( 'sha256', 'token-1' ) => array(
				'user_id' => 5,
				'expires' => time() - 1,
			),
		);

		$this->assertFalse( $this->sut->consume( 'token-1' ) );
	}

	/**
	 * GIVEN a binding that has not expired yet
	 * WHEN the creator exchanges it
	 * THEN the exchange is allowed
	 */
	public function test_unexpired_token_is_accepted(): void {
		$this->session->data[ self::SESSION_KEY ] = array(
			hash( 'sha256', 'token-1' ) => array(
				'user_id' => 5,
				'expires' => time() + 60,
			),
		);

		$this->assertTrue( $this->sut->consume( 'token-1' ) );
	}

	/**
	 * GIVEN an expired binding is stored in the session
	 * WHEN a new token is bound
	 * THEN the expired binding is dropped and the new token is still accepted
	 */
	public function test_binding_a_new_token_keeps_other_valid_tokens_and_drops_expired_ones(): void {
		$this->session->data[ self::SESSION_KEY ] = array(
			hash( 'sha256', 'old' ) => array(
				'user_id' => 5,
				'expires' => time() - 1,
			),
		);

		$this->sut->bind( 'token-1' );
		$this->sut->bind( 'token-2' );

		$this->assertArrayNotHasKey( hash( 'sha256', 'old' ), $this->session->data[ self::SESSION_KEY ] );
		$this->assertTrue( $this->sut->consume( 'token-1' ) );
		$this->assertTrue( $this->sut->consume( 'token-2' ) );
	}

	/**
	 * GIVEN an empty token id
	 * WHEN it is bound and exchanged
	 * THEN the exchange is refused and nothing is stored
	 */
	public function test_empty_token_is_refused(): void {
		$this->sut->bind( '' );

		$this->assertFalse( $this->sut->consume( '' ) );
		$this->assertArrayNotHasKey( self::SESSION_KEY, $this->session->data );
	}

	/**
	 * GIVEN WooCommerce has no session available
	 * WHEN a token is bound and exchanged
	 * THEN binding does nothing and the exchange is refused
	 */
	public function test_without_a_woocommerce_session_exchange_is_refused(): void {
		$this->expose_session( null );

		$this->sut->bind( 'token-1' );

		$this->assertFalse( $this->sut->consume( 'token-1' ) );
		$this->assertSame( array(), $this->session->data );
	}

	/**
	 * GIVEN a visitor whose session has no cookie yet
	 * WHEN a token is bound
	 * THEN a session cookie is requested so the binding survives until the exchange
	 */
	public function test_binding_requests_a_session_cookie_when_visitor_has_none(): void {
		$session = $this->create_session( false );
		$this->expose_session( $session );

		$this->sut->bind( 'token-1' );

		$this->assertTrue( $session->cookie_set );
	}

	/**
	 * GIVEN a visitor whose session already has a cookie
	 * WHEN a token is bound
	 * THEN no new cookie is requested
	 */
	public function test_binding_does_not_request_a_cookie_when_session_exists(): void {
		$this->sut->bind( 'token-1' );

		$this->assertFalse( $this->session->cookie_set );
	}
}
