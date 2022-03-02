<?php
/**
 * Handles the Webhook VAULT.PAYMENT-TOKEN.CREATED
 *
 * @package WooCommerce\PayPalCommerce\Webhooks\Handler
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Webhooks\Handler;

use Psr\Log\LoggerInterface;

class VaultCreditCardCreated implements RequestHandler
{
	/**
	 * @var LoggerInterface
	 */
	protected $logger;

	/**
	 * @var string
	 */
	protected $prefix;

	public function __construct(LoggerInterface $logger, string $prefix)
	{
		$this->logger = $logger;
		$this->prefix = $prefix;
	}

	public function event_types(): array
	{
		return array(
			'VAULT.CREDIT-CARD.CREATED',
		);
	}

	public function responsible_for_request(\WP_REST_Request $request): bool
	{
		return in_array( $request['event_type'], $this->event_types(), true );
	}

	public function handle_request(\WP_REST_Request $request): \WP_REST_Response
	{
		$message = 'VAULT.CREDIT-CARD.CREATED received.';
		$this->logger->log('info', $message);
		$response = array(
			'success' => true,
			'message' => $message,
		);

		return rest_ensure_response($response);
	}
}
