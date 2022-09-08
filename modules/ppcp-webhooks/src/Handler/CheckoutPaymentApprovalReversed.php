<?php

namespace WooCommerce\PayPalCommerce\Webhooks\Handler;

use Psr\Log\LoggerInterface;
use WP_REST_Request;
use WP_REST_Response;

class CheckoutPaymentApprovalReversed implements RequestHandler
{
	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	protected $logger;

	/**
	 * @param LoggerInterface $logger The logger.
	 */
	public function __construct(LoggerInterface $logger)
	{
		$this->logger = $logger;
	}

	public function event_types(): array
	{
		return array(
			'CHECKOUT.PAYMENT-APPROVAL.REVERSED',
		);
	}

	public function responsible_for_request(WP_REST_Request $request): bool
	{
		return in_array( $request['event_type'], $this->event_types(), true );
	}

	public function handle_request(WP_REST_Request $request): WP_REST_Response
	{
		$this->logger->info(wc_print_r($request['resource'], true));

		$response['success'] = true;
		return new WP_REST_Response( $response );
	}
}
