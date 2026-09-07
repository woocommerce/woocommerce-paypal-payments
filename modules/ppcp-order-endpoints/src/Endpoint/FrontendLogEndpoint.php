<?php

/**
 * Records a failure that a frontend handled and no server-side code ever saw.
 *
 * The caller names the tag its lines are grouped under, so one endpoint serves
 * every module's frontend rather than each growing its own.
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\OrderEndpoints\Endpoint;

use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\Button\Endpoint\EndpointInterface;
use WooCommerce\PayPalCommerce\Button\Exception\NonceValidationException;
class FrontendLogEndpoint implements EndpointInterface
{
    public const ENDPOINT = 'ppc-frontend-log';
    private const MAX_LINE_LENGTH = 1024;
    private const LEVELS = array('debug', 'info', 'warning', 'error');
    private \WooCommerce\PayPalCommerce\OrderEndpoints\Endpoint\RequestData $request_data;
    private LoggerInterface $logger;
    public function __construct(\WooCommerce\PayPalCommerce\OrderEndpoints\Endpoint\RequestData $request_data, LoggerInterface $logger)
    {
        $this->request_data = $request_data;
        $this->logger = $logger;
    }
    public static function nonce(): string
    {
        return self::ENDPOINT;
    }
    /**
     * Logs one report at the level it claims, `error` unless it names another.
     */
    public function handle_request(): void
    {
        try {
            $data = $this->request_data->read_request(self::nonce());
            /**
             * Disable front-end logging without disabling logging completely.
             */
            if (apply_filters('woocommerce_paypal_payments_frontend_log_enabled', \true)) {
                add_filter('woocommerce_paypal_payments_skip_new_request_log', '__return_true');
                $this->logger->log($this->level($data), $this->line($data));
            }
            wp_send_json_success();
        } catch (NonceValidationException $error) {
            // Fire-and-forget endpoint, response data is never parsed, no need to indicate failures.
            wp_send_json_success();
        }
    }
    private function level(array $data): string
    {
        $level = $this->string_field($data, 'level');
        return in_array($level, self::LEVELS, \true) ? $level : 'error';
    }
    /**
     * Shaped `[tag] event: detail`, capped.
     *
     * @param array<string, mixed> $data The request data.
     */
    private function line(array $data): string
    {
        $tag = $this->string_field($data, 'tag') ?: 'frontend';
        $event = $this->string_field($data, 'event') ?: 'unknown';
        $detail = $this->string_field($data, 'message');
        $line = sprintf('[%1$s] %2$s', $tag, $event);
        if ('' !== $detail) {
            $line .= ': ' . $detail;
        }
        return substr($line, 0, self::MAX_LINE_LENGTH);
    }
    /**
     * A reported field as a string.
     *
     * RequestData already ran every key and value through sanitize_text_field.
     *
     * @param array<string, mixed> $data The data to read from.
     * @param string               $key  The field to read.
     */
    private function string_field(array $data, string $key): string
    {
        $value = $data[$key] ?? '';
        if (!is_scalar($value)) {
            return '';
        }
        return (string) $value;
    }
}
