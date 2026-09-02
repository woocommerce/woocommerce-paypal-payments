<?php
/**
 * Resolves the API host to use for the current connection state.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Helper
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Helper;

use WooCommerce\PayPalCommerce\WcGateway\Helper\ConnectionState;

/**
 * Class ApiHostResolver
 *
 * Narrows ConnectionState down to the one thing most API consumers actually
 * need - "what host do I use right now" - without handing them
 * ConnectionState's connect()/disconnect() capabilities, which are none of
 * an API consumer's concern.
 */
class ApiHostResolver {

	private ConnectionState $connection_state;

	public function __construct( ConnectionState $connection_state ) {
		$this->connection_state = $connection_state;
	}

	/**
	 * Returns the API host to use right now.
	 *
	 * Must be called fresh for every request, not resolved once and cached -
	 * the connection state can change mid-request (e.g. right after
	 * onboarding completes), and a cached value would keep pointing at the
	 * pre-connection host.
	 */
	public function host(): string {
		$environment = $this->connection_state->get_environment();

		// TEMPORARY diagnostic for the ngrok webhook-host investigation. Remove
		// alongside the other [ngrok-diag] lines. PR #4669 moved host
		// resolution to call time (this class), but the symptom persisted
		// after that fix shipped - this logs the actual ConnectionState/
		// Environment instance this resolver holds, to compare against the
		// object ids logged right after connect() mutates its own instance,
		// and confirm or rule out a DI-graph identity mismatch across modules.
		if ( getenv( 'NGROK_HOST' ) ) {
			file_put_contents( // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- temporary diagnostic.
				WP_CONTENT_DIR . '/ngrok-diag.log',
				sprintf(
					"[ngrok-diag] ApiHostResolver::host(): is_sandbox=%s is_connected=%s connection_state_obj=%d environment_obj=%d\n",
					var_export( $environment->is_sandbox(), true ),
					var_export( $this->connection_state->is_connected(), true ),
					spl_object_id( $this->connection_state ),
					spl_object_id( $environment )
				),
				FILE_APPEND
			);
		}

		if ( $environment->is_sandbox() ) {
			return $this->connection_state->is_connected() ? PAYPAL_SANDBOX_API_URL : CONNECT_WOO_SANDBOX_URL;
		}

		return $this->connection_state->is_connected() ? PAYPAL_API_URL : CONNECT_WOO_URL;
	}
}
