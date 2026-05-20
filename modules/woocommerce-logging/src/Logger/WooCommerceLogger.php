<?php

/**
 * WooCommerce includes a logger interface, which is fully compatible to PSR-3,
 * but for some reason does not extend/implement it.
 *
 * This is a decorator that makes any WooCommerce Logger PSR-3-compatible
 *
 * @package WooCommerce\WooCommerce\Logging\Logger
 */
declare (strict_types=1);
namespace WooCommerce\WooCommerce\Logging\Logger;

use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerTrait;
/**
 * Class WooCommerceLogger
 */
class WooCommerceLogger implements LoggerInterface
{
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
    private string $source;
    /**
     * Details that are output before the first real log message, to help
     * identify the request.
     *
     * @var string
     */
    private string $request_info;
    /**
     * A random prefix which is visible in every log message, to better
     * understand which messages belong to the same request.
     *
     * @var string
     */
    private string $prefix;
    /**
     * WooCommerceLogger constructor.
     *
     * @param \WC_Logger_Interface $wc_logger The WooCommerce logger.
     * @param string               $source    The source.
     */
    public function __construct(\WC_Logger_Interface $wc_logger, string $source)
    {
        $this->wc_logger = $wc_logger;
        $this->source = $source;
        $this->prefix = sprintf('#%s - ', wp_rand(1000, 9999));
        // phpcs:disable -- Intentionally not sanitized, for logging purposes.
        $method = wp_unslash($_SERVER['REQUEST_METHOD'] ?? 'CLI');
        $request_uri = wp_unslash($_SERVER['REQUEST_URI'] ?? '-');
        // phpcs:enable
        $request_path = wp_parse_url($request_uri, \PHP_URL_PATH);
        $this->request_info = "{$method} {$request_path}";
    }
    /**
     * Logs a message.
     *
     * @param mixed  $level   The logging level.
     * @param string $message The message.
     * @param array  $context The context.
     */
    public function log($level, $message, array $context = array())
    {
        if (!isset($context['source'])) {
            $context['source'] = $this->source;
        }
        if ($this->request_info) {
            $this->wc_logger->log('debug', "{$this->prefix}[New Request] {$this->request_info}", array('source' => $context['source']));
            $this->request_info = '';
        }
        $this->wc_logger->log($level, "{$this->prefix}{$message}", $context);
    }
}
