<?php
/**
 * Saves the form data to the WC customer and session.
 *
 * @package WooCommerce\PayPalCommerce\Button\Endpoint
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Button\Endpoint;

use Exception;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\Button\Helper\CheckoutFormSaver;

/**
 * Class SaveCheckoutFormEndpoint
 */
class SaveCheckoutFormEndpoint implements EndpointInterface {
	const ENDPOINT = 'ppc-save-checkout-form';

	/**
	 * The Request Data Helper.
	 *
	 * @var RequestData
	 */
	private $request_data;

	/**
	 * The checkout form saver.
	 *
	 * @var CheckoutFormSaver
	 */
	private $checkout_form_saver;

	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	protected $logger;

	/**
	 * SaveCheckoutFormEndpoint constructor.
	 *
	 * @param RequestData       $request_data The Request Data Helper.
	 * @param CheckoutFormSaver $checkout_form_saver The checkout form saver.
	 * @param LoggerInterface   $logger The logger.
	 */
	public function __construct(
		RequestData $request_data,
		CheckoutFormSaver $checkout_form_saver,
		LoggerInterface $logger
	) {

		$this->request_data        = $request_data;
		$this->checkout_form_saver = $checkout_form_saver;
		$this->logger              = $logger;
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

			$this->checkout_form_saver->save( $data['form'] );

			wp_send_json_success();
			return true;
		} catch ( Exception $error ) {
			$this->logger->error( 'Checkout form saving failed: ' . $error->getMessage() );

			wp_send_json_error(
				array(
					'message' => $error->getMessage(),
				)
			);
			return false;
		}
	}
}
