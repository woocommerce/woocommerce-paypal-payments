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

	/**
	 * The connection state.
	 *
	 * @var ConnectionState
	 */
	private $connection_state;

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

		if ( $environment->is_sandbox() ) {
			return $this->connection_state->is_connected() ? PAYPAL_SANDBOX_API_URL : CONNECT_WOO_SANDBOX_URL;
		}

		return $this->connection_state->is_connected() ? PAYPAL_API_URL : CONNECT_WOO_URL;
	}
}
