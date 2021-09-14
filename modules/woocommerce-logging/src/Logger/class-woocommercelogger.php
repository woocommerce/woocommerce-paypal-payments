<?php
/**
 * WooCommerce includes a logger interface, which is fully compatible to PSR-3,
 * but for some reason does not extend/implement it.
 *
 * This is a decorator that makes any WooCommerce Logger PSR-3-compatible
 *
 * @package WooCommerce\WooCommerce\Logging\Logger
 */

declare(strict_types=1);

namespace WooCommerce\WooCommerce\Logging\Logger;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use WP_Error;

/**
 * Class WooCommerceLogger
 */
class WooCommerceLogger implements LoggerInterface {


	use LoggerTrait;

	/**
	 * The WooCommerce logger.
	 *
	 * @var \WC_Logger_Interface
	 */
	private $wc_logger;

	/**
	 * The source (Plugin), which logs the message.
	 *
	 * @var string The source.
	 */
	private $source;

	/**
	 * WooCommerceLogger constructor.
	 *
	 * @param \WC_Logger_Interface $wc_logger The WooCommerce logger.
	 * @param string               $source                  The source.
	 */
	public function __construct( \WC_Logger_Interface $wc_logger, string $source ) {
		$this->wc_logger = $wc_logger;
		$this->source    = $source;
	}

	/**
	 * Logs a message.
	 *
	 * @param mixed  $level The logging level.
	 * @param string $message The message.
	 * @param array  $context The context.
	 */
	public function log( $level, $message, array $context = array() ) {
		if ( ! isset( $context['source'] ) ) {
			$context['source'] = $this->source;
		}
		$this->wc_logger->log( $level, $message, $context );
	}

	/**
	 * Logs request and response information.
	 *
	 * @param string         $url The request URL.
	 * @param array          $args The request arguments.
	 * @param array|WP_Error $response The response or WP_Error on failure.
	 * @param string         $host The host.
	 * @return void
	 */
	public function logRequestResponse( string $url, array $args, $response, string $host ) {

		if ( is_wp_error( $response ) ) {
			$this->error( $response->get_error_code() . ' ' . $response->get_error_message() );
			return;
		}

		$method = $args['method'] ?? '';
		$output = $method . ' ' . $url . "\n";
		if ( isset( $args['body'] ) ) {
			if ( ! in_array(
				$url,
				array(
					trailingslashit( $host ) . 'v1/oauth2/token/',
					trailingslashit( $host ) . 'v1/oauth2/token?grant_type=client_credentials',
				),
				true
			) ) {
				$output .= 'Request Body: ' . wc_print_r( $args['body'], true ) . "\n";
			}
		}

		if ( is_array( $response ) ) {
			if ( isset( $response['headers']->getAll()['paypal-debug-id'] ) ) {
				$output .= 'Response Debug ID: ' . $response['headers']->getAll()['paypal-debug-id'] . "\n";
			}
			if ( isset( $response['response'] ) ) {
				$output .= 'Response: ' . wc_print_r( $response['response'], true ) . "\n";

				if ( isset( $response['body'] )
					&& isset( $response['response']['code'] )
					&& ! in_array( $response['response']['code'], array( 200, 201, 202, 204 ), true ) ) {
					$output .= 'Response Body: ' . wc_print_r( $response['body'], true ) . "\n";
				}
			}
		}

		$this->info( $output );
	}
}
