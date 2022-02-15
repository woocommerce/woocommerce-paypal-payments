<?php
/**
 * Handles the Webhook VAULT.PAYMENT-TOKEN.DELETED
 *
 * @package WooCommerce\PayPalCommerce\Webhooks\Handler
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Webhooks\Handler;

use Psr\Log\LoggerInterface;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Class VaultPaymentTokenDeleted
 */
class VaultPaymentTokenDeleted implements RequestHandler {

	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	protected $logger;

	/**
	 * VaultPaymentTokenDeleted constructor.
	 *
	 * @param LoggerInterface $logger The logger.
	 */
	public function __construct( LoggerInterface $logger ) {
		$this->logger = $logger;
	}

	/**
	 * The event types a handler handles.
	 *
	 * @return string[]
	 */
	public function event_types(): array {
		return array(
			'VAULT.PAYMENT-TOKEN.DELETED',
		);
	}

	/**
	 * Whether a handler is responsible for a given request or not.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return bool
	 */
	public function responsible_for_request( WP_REST_Request $request ): bool {
		return in_array( $request['event_type'], $this->event_types(), true );
	}

	/**
	 * Responsible for handling the request.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function handle_request( WP_REST_Request $request ): WP_REST_Response {
		$response = array( 'success' => false );

		$payment_id = null !== $request['resource'] && isset( $request['resource']['id'] )
			? $request['resource']['id']
			: null;

		if ( ! $payment_id ) {
			$message = sprintf(
			// translators: %s is the PayPal webhook Id.
				__(
					'No payment id for webhook event %s was found.',
					'woocommerce-paypal-payments'
				),
				null !== $request['id'] ? $request['id'] : ''
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

		$subscriptions = get_posts(
			array(
				'numberposts' => -1,
				'post_type'   => 'shop_subscription',
				'post_status' => 'wc-active',
				'meta_key'    => 'payment_token_id',
				'meta_value'  => $payment_id,
			)
		);

		if ( ! $subscriptions ) {
			$message = sprintf(
			// translators: %s is the PayPal payment ID.
				__( 'Subscriptions for PayPal payment ID %s not found.', 'woocommerce-paypal-payments' ),
				null !== $request['resource'] && isset( $request['resource']['id'] ) ? $request['resource']['id'] : ''
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

		foreach ( $subscriptions as $subs ) {
			if ( ! is_object( $subs ) ) {
				continue;
			}

			/**
			 * Function exist in Subscriptions plugin.
			 *
			 * @psalm-suppress UndefinedFunction
			 */
			$subscription = wcs_get_subscription( $subs->ID );
			if ( $subscription ) {
				$message = sprintf(
				// translators: %s is the PayPal billing ID.
					__( 'Automatic payment with billing ID %s was canceled on PayPal.', 'woocommerce-paypal-payments' ),
					$payment_id
				);

				$subscription->update_status( 'canceled', $message );
			}
		}

		$response['success'] = true;
		return rest_ensure_response( $response );
	}
}

