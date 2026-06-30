<?php

/**
 * Simple transient-based rate limiter.
 *
 * @package WooCommerce\PayPalCommerce\SdkV6\Helper
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\SdkV6\Helper;

/**
 * Class RateLimiter
 */
class RateLimiter
{
    /**
     * The transient key prefix.
     *
     * @var string
     */
    private string $prefix;
    /**
     * The maximum number of requests allowed per window.
     *
     * @var int
     */
    private int $max_requests;
    /**
     * The time window in seconds.
     *
     * @var int
     */
    private int $window_seconds;
    /**
     * RateLimiter constructor.
     *
     * @param string $prefix The transient key prefix.
     * @param int    $max_requests The maximum requests per window.
     * @param int    $window_seconds The time window in seconds.
     */
    public function __construct(string $prefix, int $max_requests, int $window_seconds)
    {
        $this->prefix = $prefix;
        $this->max_requests = $max_requests;
        $this->window_seconds = $window_seconds;
    }
    /**
     * Checks if the current client has exceeded the rate limit.
     *
     * @return bool
     */
    public function is_limited(): bool
    {
        $key = $this->get_key();
        $count = (int) get_transient($key);
        return $count >= $this->max_requests;
    }
    /**
     * Records a request hit for the current client.
     *
     * @return void
     */
    public function hit(): void
    {
        $key = $this->get_key();
        $count = (int) get_transient($key);
        set_transient($key, $count + 1, $this->window_seconds);
    }
    /**
     * Generates the transient key for the current client.
     *
     * Uses WC session customer ID if available, falls back to IP address.
     *
     * @return string
     */
    private function get_key(): string
    {
        $identifier = '';
        if (function_exists('WC') && isset(WC()->session) && is_object(WC()->session)) {
            $customer_id = WC()->session->get_customer_id();
            if (!empty($customer_id)) {
                $identifier = $customer_id;
            }
        }
        if (empty($identifier)) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $raw_ip = wp_unslash($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
            $identifier = is_array($raw_ip) ? '0.0.0.0' : sanitize_text_field($raw_ip);
        }
        return $this->prefix . md5($identifier);
    }
}
