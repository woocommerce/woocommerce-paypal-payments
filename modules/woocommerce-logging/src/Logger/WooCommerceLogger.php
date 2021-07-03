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
}
