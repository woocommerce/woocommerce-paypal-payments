<?php
/**
 * The endpoint for starting vaulting of PayPal account (for free trial).
 *
 * @package WooCommerce\PayPalCommerce\Button\Endpoint
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Button\Endpoint;

use Exception;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PaymentTokenEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;

/**
 * Class StartPayPalVaultingEndpoint.
 */
class StartPayPalVaultingEndpoint implements EndpointInterface {


	const ENDPOINT = 'ppc-vault-paypal';

	/**
	 * The Request Data Helper.
	 *
	 * @var RequestData
	 */
	private $request_data;

	/**
	 * The PaymentTokenEndpoint.
	 *
	 * @var PaymentTokenEndpoint
	 */
	private $payment_token_endpoint;

	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * StartPayPalVaultingEndpoint constructor.
	 *
	 * @param RequestData          $request_data The Request Data Helper.
	 * @param PaymentTokenEndpoint $payment_token_endpoint The PaymentTokenEndpoint.
	 * @param LoggerInterface      $logger The logger.
	 */
	public function __construct(
		RequestData $request_data,
		PaymentTokenEndpoint $payment_token_endpoint,
		LoggerInterface $logger
	) {
		$this->request_data           = $request_data;
		$this->payment_token_endpoint = $payment_token_endpoint;
		$this->logger                 = $logger;
	}

	/**
	 * Returns the nonce.
	 *
	 * @return string
	 */
	public static function nonce(): string {
		return self::ENDPOINT;
	}

	/**
	 * Handles the request.
	 *
	 * @return bool
	 */
	public function handle_request(): bool {
		try {
			$data = $this->request_data->read_request( $this->nonce() );

			$user_id = get_current_user_id();

			$return_url = $data['return_url'];
			$cancel_url = add_query_arg( array( 'ppcp_vault' => 'cancel' ), $return_url );

			$links = $this->payment_token_endpoint->start_paypal_token_creation(
				$user_id,
				$return_url,
				$cancel_url
			);

			wp_send_json_success(
				array(
					'approve_link' => $links->approve_link(),
				)
			);

			return true;
		} catch ( Exception $error ) {
			$this->logger->error( 'Failed to start PayPal vaulting: ' . $error->getMessage() );

			wp_send_json_error(
				array(
					'name'    => is_a( $error, PayPalApiException::class ) ? $error->name() : '',
					'message' => $error->getMessage(),
				)
			);
			return false;
		}
	}
}
