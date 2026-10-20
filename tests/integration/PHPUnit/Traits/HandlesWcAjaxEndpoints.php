<?php
declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\Tests\Integration\Traits;

use WooCommerce\PayPalCommerce\Tests\Integration\Helper\PhpInputStreamStub;
use WooCommerce\PayPalCommerce\Tests\Integration\Helper\WpAjaxDieSignal;

/**
 * Dispatches simulated WC-AJAX requests against endpoint objects.
 *
 * The ppc-* order endpoints read a JSON body from php://input (via RequestData)
 * and respond with wp_send_json_success()/wp_send_json_error(), which dies.
 * This trait makes both work in CLI PHPUnit:
 * - the request body is served by PhpInputStreamStub (registered only around
 *   handle_request() and restored in a finally block),
 * - wp_doing_ajax is forced to true so wp_send_json() routes through wp_die(),
 *   whose 'wp_die_ajax_handler' filter is replaced with a handler throwing
 *   WpAjaxDieSignal, unwinding back to the test with the echoed JSON captured
 *   in an output buffer.
 */
trait HandlesWcAjaxEndpoints {

	/**
	 * Hooks added during a test via recordAction(), removed in tearDownHarnessHooks().
	 *
	 * @var array<int, array{0: string, 1: callable, 2: int}>
	 */
	private $harness_hooks = array();

	/**
	 * Dispatches a simulated WC-AJAX request against an endpoint object.
	 *
	 * @param object $endpoint An endpoint with handle_request(): void and a static nonce() method.
	 * @param array  $body     The JSON body. If the 'nonce' key is absent, a valid frontend
	 *                         nonce for the endpoint's action is generated. Pass 'nonce' => 'garbage'
	 *                         to exercise the invalid-nonce contract, or 'nonce' => null for
	 *                         the missing-nonce contract.
	 * @return array{success: bool, data: mixed, status: int|null, raw: string}
	 */
	protected function dispatchAjaxRequest( $endpoint, array $body ): array {
		if ( ! array_key_exists( 'nonce', $body ) ) {
			$body['nonce'] = $this->createFrontendNonce( $endpoint::nonce() );
		}

		$status      = null;
		$status_spy  = static function ( $header, $code ) use ( &$status ) {
			$status = (int) $code;
			return $header;
		};
		$die_handler = static function () {
			return static function () {
				throw new WpAjaxDieSignal( 'wp_die (ajax)' );
			};
		};

		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter( 'wp_die_ajax_handler', $die_handler, 99 );
		add_filter( 'status_header', $status_spy, 10, 2 );

		PhpInputStreamStub::$body = (string) wp_json_encode( $body );
		stream_wrapper_unregister( 'php' );
		stream_wrapper_register( 'php', PhpInputStreamStub::class );

		$ob_level  = ob_get_level();
		$raw       = '';
		$responded = false;
		ob_start();
		try {
			$endpoint->handle_request();
		} catch ( WpAjaxDieSignal $signal ) {
			$responded = true;
		} finally {
			while ( ob_get_level() > $ob_level ) {
				$raw = (string) ob_get_clean() . $raw;
			}
			stream_wrapper_restore( 'php' );
			remove_filter( 'wp_doing_ajax', '__return_true' );
			remove_filter( 'wp_die_ajax_handler', $die_handler, 99 );
			remove_filter( 'status_header', $status_spy, 10 );
		}

		if ( ! $responded ) {
			$this->fail( 'Endpoint returned without sending a JSON response. Output: ' . $raw );
		}

		$decoded = json_decode( $raw, true );
		$this->assertIsArray( $decoded, 'Endpoint response is not valid JSON: ' . $raw );
		$this->assertArrayHasKey( 'success', $decoded, 'Endpoint response has no success flag: ' . $raw );

		return array(
			'success' => (bool) $decoded['success'],
			'data'    => $decoded['data'] ?? null,
			'status'  => $status,
			'raw'     => $raw,
		);
	}

	/**
	 * Creates a nonce the way the v5/v6 frontend receives it: rendered for a guest
	 * with the logged-out uid forced to 0, mirroring RequestData::nonce_fix().
	 *
	 * @param string $action The nonce action (the endpoint name).
	 * @return string
	 */
	protected function createFrontendNonce( string $action ): string {
		$force_zero = static function (): int {
			return 0;
		};
		add_filter( 'nonce_user_logged_out', $force_zero, 100 );
		try {
			return wp_create_nonce( $action );
		} finally {
			remove_filter( 'nonce_user_logged_out', $force_zero, 100 );
		}
	}

	/**
	 * Ensures WC()->cart and WC()->session exist in CLI, empties the cart and
	 * clears the plugin session state and WC notices.
	 *
	 * @return void
	 */
	protected function resetWcFrontendState(): void {
		if ( null === WC()->cart || null === WC()->session ) {
			wc_load_cart();
		}
		if ( WC()->session instanceof \WC_Session_Handler && ! WC()->session->get_customer_unique_id() ) {
			// Mark the CLI process as having a guest session cookie, like every real
			// frontend request has: get_customer_unique_id() returns '' without it,
			// which would skip the session-bound custom_id / ownership-check paths.
			// Reflection instead of set_customer_session_cookie( true ) because
			// wc_setcookie() raises a headers-already-sent notice under PHPUnit.
			$has_cookie = new \ReflectionProperty( \WC_Session_Handler::class, '_has_cookie' );
			$has_cookie->setAccessible( true );
			$has_cookie->setValue( WC()->session, true );
		}
		WC()->cart->empty_cart();
		WC()->session->set( 'ppcp', null );
		WC()->session->set( 'chosen_payment_method', null );
		wc_clear_notices();
	}

	/**
	 * Records invocations of a WP action; removed again in tearDownHarnessHooks().
	 *
	 * @param string $hook The action hook name.
	 * @param int    $accepted_args How many arguments to record per invocation.
	 * @return \ArrayObject Each entry is the array of arguments of one invocation.
	 */
	protected function recordAction( string $hook, int $accepted_args = 2 ): \ArrayObject {
		$calls    = new \ArrayObject();
		$callback = static function ( ...$args ) use ( $calls ) {
			$calls[] = $args;
			return $args[0] ?? null;
		};
		add_action( $hook, $callback, 10, $accepted_args );
		$this->harness_hooks[] = array( $hook, $callback, 10 );

		return $calls;
	}

	/**
	 * Removes all hooks registered through recordAction().
	 *
	 * @return void
	 */
	protected function tearDownHarnessHooks(): void {
		foreach ( $this->harness_hooks as $hook_data ) {
			list( $hook, $callback, $priority ) = $hook_data;
			remove_filter( $hook, $callback, $priority );
		}
		$this->harness_hooks = array();
	}
}
