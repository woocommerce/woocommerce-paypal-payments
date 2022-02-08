<?php
/**
 * Handles the Webhook VAULT.PAYMENT-TOKEN.DELETED
 *
 * @package WooCommerce\PayPalCommerce\Webhooks\Handler
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Webhooks\Handler;

class VaultPaymentTokenDeleted implements RequestHandler {

	/**
	 * @return array
	 */
	public function event_types(): array
	{
		return array(
			'VAULT.PAYMENT-TOKEN.DELETED',
		);
	}

	/**
	 * @param \WP_REST_Request $request
	 * @return bool
	 */
	public function responsible_for_request(\WP_REST_Request $request): bool
	{
		return in_array( $request['event_type'], $this->event_types(), true );
	}

	/**
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response
	 */
	public function handle_request(\WP_REST_Request $request): \WP_REST_Response
	{
		// TODO: Update subscription status to canceled
	}
}

