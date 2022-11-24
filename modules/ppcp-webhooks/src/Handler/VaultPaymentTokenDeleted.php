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

class VaultPaymentTokenDeleted implements RequestHandler
{
	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @param LoggerInterface $logger
	 */
	public function __construct(LoggerInterface $logger)
	{
		$this->logger = $logger;
	}

	public function event_types(): array
	{
		return array(
			'VAULT.PAYMENT-TOKEN.DELETED',
		);
	}

	public function responsible_for_request(WP_REST_Request $request): bool
	{
		return in_array( $request['event_type'], $this->event_types(), true );
	}

	public function handle_request(WP_REST_Request $request): WP_REST_Response {

		if(isset($request['resource']['id'])) {
			$token_id = wc_clean(wp_unslash($request['resource']['id'] ?? ''));

			global $wpdb;
			$token = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}woocommerce_payment_tokens WHERE token=%s",
					$token_id
				)
			);

			if(isset($token->token_id)) {
				WC_Payment_Tokens::delete( $token->token_id );
			}
		}

		$response['success'] = true;
		return new WP_REST_Response( $response );
	}
}
