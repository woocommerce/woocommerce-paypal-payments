<?php
/**
 * Handles the Webhook VAULT.PAYMENT-TOKEN.DELETED
 *
 * @package WooCommerce\PayPalCommerce\Webhooks\Handler
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Webhooks\Handler;

use Psr\Log\LoggerInterface;

class VaultPaymentTokenDeleted implements RequestHandler {

	/**
	 * @var LoggerInterface
	 */
	protected $logger;

	public function __construct(LoggerInterface $logger)
	{
		$this->logger = $logger;
	}

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
		$response   = array( 'success' => false );

		$payment_id = $request['resource']['id'] ?? null;
		if(!$payment_id) {
			$message = sprintf(
			// translators: %s is the PayPal webhook Id.
				__(
					'No payment id for webhook event %s was found.',
					'woocommerce-paypal-payments'
				),
				isset( $request['id'] ) ? $request['id'] : ''
			);

			$this->logger->log(
				'warning',
				$message,
				array(
					'request' => $request,
				)
			);
			$response['message'] = $message;
			return rest_ensure_response( $response );
		}

		$orders = wc_get_orders(array(
			'limit'        => -1,
			'meta_key'     => 'payment_token_id',
			'meta_value' => $payment_id,
		));
		if ( ! $orders ) {
			$message = sprintf(
			// translators: %s is the PayPal payment Id.
				__( 'Orders for PayPal payment id %s not found.', 'woocommerce-paypal-payments' ),
				isset( $request['resource']['id'] ) ? $request['resource']['id'] : ''
			);
			$this->logger->log(
				'warning',
				$message,
				array(
					'request' => $request,
				)
			);

			$response['message'] = $message;
			return rest_ensure_response( $response );
		}

		foreach ($orders as $order) {
			$order->update_status(
				'canceled',
				__( "Automatic payment with billing ID:{$payment_id} was canceled on PayPal.", 'woocommerce-paypal-payments' )
			);
		}

		$response['success'] = true;
		return rest_ensure_response( $response );
	}
}

