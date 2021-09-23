<?php
/**
 * The endpoint for deleting payment tokens.
 *
 * @package WooCommerce\PayPalCommerce\Vaulting\Endpoint
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Vaulting\Endpoint;

use Exception;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\Button\Endpoint\RequestData;
use WooCommerce\PayPalCommerce\Vaulting\PaymentTokenRepository;

/**
 * Class DeletePayment
 */
class DeletePaymentTokenEndpoint {

	const ENDPOINT = 'ppc-vaulting-delete';

	/**
	 *  The repository.
	 *
	 * @var PaymentTokenRepository
	 */
	protected $repository;

	/**
	 * The request data.
	 *
	 * @var RequestData
	 */
	protected $request_data;

	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	protected $logger;

	/**
	 * DeletePaymentTokenEndpoint constructor.
	 *
	 * @param PaymentTokenRepository $repository The repository.
	 * @param RequestData            $request_data The request data.
	 * @param LoggerInterface        $logger The logger.
	 */
	public function __construct( PaymentTokenRepository $repository, RequestData $request_data, LoggerInterface $logger ) {
		$this->repository   = $repository;
		$this->request_data = $request_data;
		$this->logger       = $logger;
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
		try {
			$data = $this->request_data->read_request( $this->nonce() );

			$tokens = $this->repository->all_for_user_id( get_current_user_id() );
			if ( $tokens ) {
				foreach ( $tokens as $token ) {
					if ( isset( $data['token'] ) && $token->id() === $data['token'] ) {
						if ( $this->repository->delete_token( get_current_user_id(), $token ) ) {
							wp_send_json_success();
							return true;
						}

						wp_send_json_error( 'Could not delete payment token.' );
						return false;
					}
				}
			}
		} catch ( Exception $error ) {
			$this->logger->error( 'Failed to delete payment: ' . $error->getMessage() );
			wp_send_json_error( $error->getMessage(), 403 );
			return false;
		}
	}
}

