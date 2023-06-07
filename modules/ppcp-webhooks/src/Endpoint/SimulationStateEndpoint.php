<?php
/**
 * The endpoint for getting the current webhooks simulation state.
 *
 * @package WooCommerce\PayPalCommerce\Webhooks\Endpoint
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Webhooks\Endpoint;

use Exception;
use WooCommerce\PayPalCommerce\Button\Endpoint\RequestData;
use WooCommerce\PayPalCommerce\Webhooks\Status\WebhookSimulation;

/**
 * Class SimulationStateEndpoint
 */
class SimulationStateEndpoint {

	const ENDPOINT = 'ppc-webhooks-simulation-state';

	/**
	 * The simulation handler.
	 *
	 * @var WebhookSimulation
	 */
	private $simulation;

	/**
	 * SimulationStateEndpoint constructor.
	 *
	 * @param WebhookSimulation $simulation The simulation handler.
	 */
	public function __construct(
		WebhookSimulation $simulation
	) {
		$this->simulation = $simulation;
	}

	/**
	 * Returns the nonce for the endpoint.
	 *
	 * @return string
	 */
	public static function nonce(): string {
		return self::ENDPOINT;
	}

	/**
	 * Handles the incoming request.
	 */
	public function handle_request() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'Not admin.', 403 );
			return false;
		}

		try {
			$state = $this->simulation->get_state();

			wp_send_json_success(
				array(
					'state' => $state,
				)
			);
			return true;
		} catch ( Exception $error ) {
			wp_send_json_error( $error->getMessage(), 500 );
			return false;
		}
	}
}
