<?php
/**
 * The endpoint for getting the Pay Later messaging config for the configurator.
 *
 * @package WooCommerce\PayPalCommerce\PayLaterConfigurator\Endpoint
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\PayLaterConfigurator\Endpoint;

use Psr\Log\LoggerInterface;
use Throwable;
use WooCommerce\PayPalCommerce\PayLaterConfigurator\Factory\ConfigFactory;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;

/**
 * Class GetConfig.
 */
class GetConfig {
	const ENDPOINT = 'ppc-get-message-config';

	/**
	 * The settings.
	 *
	 * @var Settings
	 */
	protected $settings;

	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * GetConfig constructor.
	 *
	 * @param Settings        $settings The settings.
	 * @param LoggerInterface $logger The logger.
	 */
	public function __construct( Settings $settings, LoggerInterface $logger ) {
		$this->settings = $settings;
		$this->logger   = $logger;
	}

	/**
	 * Returns the nonce.
	 */
	public static function nonce(): string {
		return self::ENDPOINT;
	}

	/**
	 * Handles the request.
	 */
	public function handle_request(): bool {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			$this->logger->error( 'User does not have permission: manage_woocommerce' );
			wp_send_json_error( 'Not admin.', 403 );
			return false;
		}

		try {
			$input = file_get_contents( 'php://input' );

			if ( false === $input ) {
				$this->logger->error( 'Failed to get input data.' );
				wp_send_json_error( 'Failed to get input data.', 400 );
				return false;
			}

			$data = json_decode( $input, true );

			if ( json_last_error() !== JSON_ERROR_NONE ) {
				$this->logger->error( 'Failed to decode JSON: ' . json_last_error_msg() );
				wp_send_json_error( 'Failed to decode JSON.', 400 );
				return false;
			}

			if ( ! isset( $data['nonce'] ) || ! wp_verify_nonce( $data['nonce'], self::ENDPOINT ) ) {
				$this->logger->error( 'Invalid nonce' );
				wp_send_json_error( 'Invalid nonce.', 403 );
				return false;
			}

			$config_factory = new ConfigFactory();
			$config         = $config_factory->from_settings( $this->settings );
			wp_send_json_success( $config );
			return true;
		} catch ( Throwable $error ) {
			$this->logger->error( "GetConfig execution failed. {$error->getMessage()} {$error->getFile()}:{$error->getLine()}" );

			wp_send_json_error( 'An error occurred while fetching the configuration.' );
			return false;
		}
	}
}
