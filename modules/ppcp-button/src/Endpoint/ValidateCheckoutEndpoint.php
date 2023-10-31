<?php
/**
 * The endpoint for validating the checkout form.
 *
 * @package WooCommerce\PayPalCommerce\Button\Endpoint
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Button\Endpoint;

use Psr\Log\LoggerInterface;
use Throwable;
use WooCommerce\PayPalCommerce\Button\Exception\ValidationException;
use WooCommerce\PayPalCommerce\Button\Validation\CheckoutFormValidator;

/**
 * Class ValidateCheckoutEndpoint.
 */
class ValidateCheckoutEndpoint implements EndpointInterface {


	const ENDPOINT = 'ppc-validate-checkout';

	/**
	 * The Request Data Helper.
	 *
	 * @var RequestData
	 */
	private $request_data;

	/**
	 * The CheckoutFormValidator.
	 *
	 * @var CheckoutFormValidator
	 */
	private $checkout_form_validator;

	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * ValidateCheckoutEndpoint constructor.
	 *
	 * @param RequestData           $request_data The Request Data Helper.
	 * @param CheckoutFormValidator $checkout_form_validator    The CheckoutFormValidator.
	 * @param LoggerInterface       $logger The logger.
	 */
	public function __construct(
		RequestData $request_data,
		CheckoutFormValidator $checkout_form_validator,
		LoggerInterface $logger
	) {
		$this->request_data            = $request_data;
		$this->checkout_form_validator = $checkout_form_validator;
		$this->logger                  = $logger;
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

			$form_fields = $data['form'];

			$this->checkout_form_validator->validate( $form_fields );

			wp_send_json_success();

			return true;
		} catch ( ValidationException $exception ) {
			$response = array(
				'message' => $exception->getMessage(),
				'errors'  => $exception->errors(),
				'refresh' => isset( WC()->session->refresh_totals ),
			);

			unset( WC()->session->refresh_totals );

			wp_send_json_error( $response );
			return false;
		} catch ( Throwable $error ) {
			$this->logger->error( "Form validation execution failed. {$error->getMessage()} {$error->getFile()}:{$error->getLine()}" );

			wp_send_json_error(
				array(
					'message' => $error->getMessage(),
				)
			);
			return false;
		}
	}
}
