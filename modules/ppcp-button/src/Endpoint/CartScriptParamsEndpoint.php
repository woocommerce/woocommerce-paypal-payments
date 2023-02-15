<?php
/**
 * The endpoint for returning the PayPal SDK Script parameters for the current cart.
 *
 * @package WooCommerce\PayPalCommerce\Button\Endpoint
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Button\Endpoint;

use Psr\Log\LoggerInterface;
use Throwable;
use WooCommerce\PayPalCommerce\Button\Assets\SmartButton;

/**
 * Class CartScriptParamsEndpoint.
 */
class CartScriptParamsEndpoint implements EndpointInterface {


	const ENDPOINT = 'ppc-cart-script-params';

	/**
	 * The SmartButton.
	 *
	 * @var SmartButton
	 */
	private $smart_button;

	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * CartScriptParamsEndpoint constructor.
	 *
	 * @param SmartButton     $smart_button he SmartButton.
	 * @param LoggerInterface $logger The logger.
	 */
	public function __construct(
		SmartButton $smart_button,
		LoggerInterface $logger
	) {
		$this->smart_button = $smart_button;
		$this->logger       = $logger;
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
			$script_data = $this->smart_button->script_data();

			wp_send_json_success( $script_data['url_params'] );

			return true;
		} catch ( Throwable $error ) {
			$this->logger->error( "CartScriptParamsEndpoint execution failed. {$error->getMessage()} {$error->getFile()}:{$error->getLine()}" );

			wp_send_json_error();
			return false;
		}
	}
}
