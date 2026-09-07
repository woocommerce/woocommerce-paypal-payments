<?php
/**
 * WooCommerce includes a logger interface, which is fully compatible to PSR-3,
 * but for some reason does not extend/implement it.
 *
 * This is a decorator that makes any WooCommerce Logger PSR-3-compatible
 *
 * @package WooCommerce\WooCommerce\Logging\Logger
 */

declare( strict_types = 1 );

namespace WooCommerce\WooCommerce\Logging\Logger;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use WC_Logger_Interface;

/**
 * Class WooCommerceLogger
 */
class WooCommerceLogger implements LoggerInterface {

	use LoggerTrait;

	/**
	 * The WooCommerce logger.
	 */
	private WC_Logger_Interface $wc_logger;

	/**
	 * The source (Plugin), which logs the message.
	 */
	private string $source;

	/**
	 * The method of the current request.
	 */
	private string $request_method;

	/**
	 * The URI of the current request.
	 */
	private string $request_uri;

	/**
	 * Whether the request was already announced in the log.
	 */
	private bool $request_logged = false;

	/**
	 * A random prefix which is visible in every log message, to better
	 * understand which messages belong to the same request.
	 */
	private static string $prefix = '';

	/**
	 * WooCommerceLogger constructor.
	 *
	 * @param \WC_Logger_Interface $wc_logger The WooCommerce logger.
	 * @param string               $source    The source.
	 */
	public function __construct( \WC_Logger_Interface $wc_logger, string $source ) {
		$this->wc_logger = $wc_logger;
		$this->source    = $source;

		// phpcs:disable -- Intentionally not sanitized, for logging purposes.
		$method = wp_unslash( $_SERVER['REQUEST_METHOD'] ?? 'CLI' );
		$uri    = wp_unslash( $_SERVER['REQUEST_URI'] ?? '-' );
		// phpcs:enable

		$this->request_method = is_string( $method ) ? $method : 'CLI';
		$this->request_uri    = is_string( $uri ) ? $uri : '-';
	}

	/**
	 * Logs a message.
	 *
	 * @param mixed  $level   The logging level.
	 * @param string $message The message.
	 * @param array  $context The context.
	 */
	public function log( $level, $message, array $context = array() ) {
		if ( ! isset( $context['source'] ) ) {
			$context['source'] = $this->source;
		}

		if ( ! self::$prefix ) {
			self::$prefix = self::request_prefix();
		}

		if ( ! $this->request_logged ) {
			$this->log_new_request( $context['source'] );
		}

		$prefix = self::$prefix;

		$this->wc_logger->log( $level, "{$prefix}$message", $context );
	}

	/**
	 * A random ID for the current request, tagged with the kind of request it
	 * is, so that lines of one kind can be told apart at a glance.
	 */
	private static function request_prefix(): string {
		$id = wp_rand( 1000, 9999 );

		if ( wp_doing_cron() ) {
			return "cron-$id - ";
		}

		if ( function_exists( 'wp_is_serving_rest_request' ) && wp_is_serving_rest_request() ) {
			return "rest-$id - ";
		}

		// WooCommerce defines DOING_AJAX as well, so this comes first.
		if ( defined( 'WC_DOING_AJAX' ) && \WC_DOING_AJAX ) {
			return "wc-$id - ";
		}

		if ( wp_doing_ajax() ) {
			return "ajax-$id - ";
		}

		return "#$id - ";
	}

	/**
	 * Announces the current request, once, before the first message of that
	 * request.
	 */
	private function log_new_request( string $source ): void {
		$this->request_logged = true;

		/**
		 * Skips the announcement, for a request whose log is a single message
		 * that names everything the announcement would.
		 *
		 * @param bool $bail Whether to skip the announcement.
		 */
		$bail = apply_filters( 'woocommerce_paypal_payments_skip_new_request_log', false );

		if ( true === $bail ) {
			return;
		}

		$prefix       = self::$prefix;
		$request_path = wp_parse_url( $this->request_uri, PHP_URL_PATH );

		$this->wc_logger->log(
			'debug',
			"{$prefix}[New Request] $this->request_method $request_path",
			array( 'source' => $source )
		);
	}
}
