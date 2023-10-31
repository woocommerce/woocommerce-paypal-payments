<?php
/**
 * Handles the Webhook VAULT.PAYMENT-TOKEN.DELETED
 *
 * @package WooCommerce\PayPalCommerce\Webhooks\Handler
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Webhooks\Handler;

use Psr\Log\LoggerInterface;
use WC_Payment_Tokens;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Class VaultPaymentTokenDeleted
 */
class VaultPaymentTokenDeleted implements RequestHandler {
	use RequestHandlerTrait;

	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	private $logger;

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
	 *
	 * @return bool
	 */
	public function responsible_for_request( WP_REST_Request $request ): bool {
		return in_array( $request['event_type'], $this->event_types(), true );
	}

	/**
	 * Responsible for handling the request.
	 *
	 * @param WP_REST_Request $request The request.
	 *
	 * @return WP_REST_Response
	 */
	public function handle_request( WP_REST_Request $request ): WP_REST_Response {
		if ( ! is_null( $request['resource'] ) && isset( $request['resource']['id'] ) ) {
			$token_id = wc_clean( wp_unslash( $request['resource']['id'] ?? '' ) );

			/**
			 * Needed for database query.
			 *
			 * @psalm-suppress InvalidGlobal
			 */
			global $wpdb;

			$token = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}woocommerce_payment_tokens WHERE token=%s",
					$token_id
				)
			);

			if ( isset( $token->token_id ) ) {
				WC_Payment_Tokens::delete( $token->token_id );
			}
		}

		return $this->success_response();
	}
}
