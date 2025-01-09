<?php
/**
 * The settings UI switching Ajax endpoint.
 *
 * @package WooCommerce\PayPalCommerce\Settings\Endpoint
 */

declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\Settings\Ajax;

use Exception;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\Button\Endpoint\RequestData;

/**
 * Class SwitchSettingsUiEndpoint
 *
 * Note: This is an ajax handler, not a REST endpoint
 */
class SwitchSettingsUiEndpoint {

	public const ENDPOINT                      = 'ppcp-settings-switch-ui';
	public const OPTION_NAME_SHOULD_USE_OLD_UI = 'woocommerce_ppcp-settings-should-use-old-ui';

	/**
	 * The RequestData.
	 *
	 * @var RequestData
	 */
	protected RequestData $request_data;

	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	protected LoggerInterface $logger;

	/**
	 * SwitchSettingsUiEndpoint constructor.
	 *
	 * @param LoggerInterface $logger The logger.
	 * @param RequestData     $request_data The Request data.
	 */
	public function __construct(
		LoggerInterface $logger,
		RequestData $request_data
	) {
		$this->logger       = $logger;
		$this->request_data = $request_data;
	}

	/**
	 * Handles the request.
	 */
	public function handle_request(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'Not an admin.', 403 );
			return;
		}

		try {
			$this->request_data->read_request( $this->nonce() );
			update_option( self::OPTION_NAME_SHOULD_USE_OLD_UI, false );

			wp_send_json_success();
		} catch ( Exception $error ) {
			wp_send_json_error( array( 'message' => $error->getMessage() ), 500 );
		}
	}

	/**
	 * The nonce.
	 *
	 * @return string
	 */
	public static function nonce(): string {
		return self::ENDPOINT;
	}
}
