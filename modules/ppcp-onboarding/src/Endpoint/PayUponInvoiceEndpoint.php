<?php
/**
 * Handles the onboard with Pay Upon Invoice setting.
 *
 * @package WooCommerce\PayPalCommerce\Onboarding\Endpoint
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Onboarding\Endpoint;

use Exception;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\Button\Endpoint\EndpointInterface;
use WooCommerce\PayPalCommerce\Button\Endpoint\RequestData;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
use WooCommerce\PayPalCommerce\WcGateway\Exception\NotFoundException;

/**
 * Class PayUponInvoiceEndpoint
 */
class PayUponInvoiceEndpoint implements EndpointInterface {

	/**
	 * The settings.
	 *
	 * @var Settings
	 */
	protected $settings;

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
	 * PayUponInvoiceEndpoint constructor.
	 *
	 * @param Settings        $settings The settings.
	 * @param RequestData     $request_data The request data.
	 * @param LoggerInterface $logger The logger.
	 */
	public function __construct( Settings $settings, RequestData $request_data, LoggerInterface $logger ) {
		$this->settings     = $settings;
		$this->request_data = $request_data;
		$this->logger       = $logger;
	}

	/**
	 * The nonce.
	 *
	 * @return string
	 */
	public static function nonce(): string {
		return 'ppc-pui';
	}

	/**
	 * Handles the request.
	 *
	 * @return bool
	 * @throws NotFoundException When order not found or handling failed.
	 */
	public function handle_request(): bool {
		try {
			$data = $this->request_data->read_request( $this->nonce() );
			$this->settings->set( 'ppcp-onboarding-pui', $data['checked'] );
			$this->settings->persist();

		} catch ( Exception $exception ) {
			$this->logger->error( $exception->getMessage() );
		}

		wp_send_json_success(
			array(
				$this->settings->get( 'ppcp-onboarding-pui' ),
			)
		);

		return true;
	}
}

