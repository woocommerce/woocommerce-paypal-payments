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
	 * @return void
	 */
	public function logRequestResponse( string $url, array $args, $response ) {
		$this->log( 'info', '--------------------------------------------------------------------' );
		$this->log( 'info', 'URL: ' . wc_print_r( $url, true ) );
		if ( isset( $args['method'] ) ) {
			$this->log( 'info', 'Method: ' . wc_print_r( $args['method'], true ) );
		}
		if ( isset( $args['body'] ) ) {
			$this->log( 'info', 'Request Body: ' . wc_print_r( $args['body'], true ) );
		}

		if ( ! is_wp_error( $response ) ) {
			if ( isset( $response['headers']->getAll()['paypal-debug-id'] ) ) {
				$this->log(
					'info',
					'Response Debug ID: ' . wc_print_r(
						$response['headers']->getAll()['paypal-debug-id'],
						true
					)
				);
			}
			if ( isset( $response['response'] ) ) {
				$this->log( 'info', 'Response: ' . wc_print_r( $response['response'], true ) );
			}
			if ( isset( $response['body'] ) ) {
				$this->log( 'info', 'Response Body: ' . wc_print_r( $response['body'], true ) );
			}
		} else {
			$this->log(
				'error',
				'WP Error: ' . $response->get_error_code() . ' ' . $response->get_error_message()
			);
		}
	}
}
