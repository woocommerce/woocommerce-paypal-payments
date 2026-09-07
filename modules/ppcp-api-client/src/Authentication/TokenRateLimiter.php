<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\ApiClient\Authentication;

use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Helper\Cache;
use WP_Error;
/**
 * Circuit breaker / backoff guard for PayPal token-endpoint requests.
 *
 * Prevents the plugin from hammering `/v1/oauth2/token` after a failure.
 * On a failed token request it stores a short-lived "cool-down" in the cache,
 * so subsequent callers fail fast without performing another network request
 * until the cool-down expires.
 */
class TokenRateLimiter
{
    /**
     * Base backoff applied to the first failure (in seconds).
     */
    const BASE_BACKOFF_SECONDS = 30;
    /**
     * Maximum backoff / cool-down (in seconds). Caps the exponential growth and
     * any `Retry-After` value we honor.
     */
    const MAX_BACKOFF_SECONDS = 900;
    /**
     * Cool-down applied when the credentials are rejected (401 / invalid_client).
     * These normally do not self-heal until the merchant reconnects, so we wait longer.
     */
    const DEAD_CREDENTIALS_COOLDOWN = 1800;
    /**
     * Added to the cool-down when storing the state, so the failure counter
     * survives just past the cool-down to allow backoff to grow, while still
     * self-healing on a quiet site.
     */
    const STATE_TTL_PADDING = 300;
    /**
     * Suffix for the cool-down state cache key.
     */
    const STATE_KEY_SUFFIX = '-circuit-state';
    private Cache $cache;
    private LoggerInterface $logger;
    public function __construct(Cache $cache, LoggerInterface $logger)
    {
        $this->cache = $cache;
        $this->logger = $logger;
    }
    /**
     * Returns the number of seconds left in the current cool-down for the given
     * scope, or null when a token request is allowed.
     *
     * @param string $scope The token-type key (e.g. "bearer").
     */
    public function retry_after_seconds(string $scope): ?int
    {
        $state = $this->read_state($scope);
        if (!isset($state['retry_at'])) {
            return null;
        }
        $remaining = (int) $state['retry_at'] - time();
        return $remaining > 0 ? $remaining : null;
    }
    /**
     * Whether token requests for the given scope are currently blocked.
     *
     * @param string $scope The token-type key.
     */
    public function is_blocked(string $scope): bool
    {
        return $this->retry_after_seconds($scope) !== null;
    }
    /**
     * Records a failed token request and computes/stores the next cool-down.
     *
     * @param string         $scope The token-type key.
     * @param int            $status_code The HTTP status code (0 for WP_Error / network failure).
     * @param array|WP_Error $response The raw response, used to read Retry-After and the error body.
     * @return int The cool-down in seconds that was applied.
     */
    public function register_failure(string $scope, int $status_code, $response): int
    {
        $state = $this->read_state($scope);
        $count = isset($state['count']) ? (int) $state['count'] : 0;
        $retry_after = $this->parse_retry_after($response);
        if ($this->is_dead_credentials($status_code, $response)) {
            $cooldown = self::DEAD_CREDENTIALS_COOLDOWN;
            $reason = 'dead_credentials';
        } elseif (429 === $status_code && null !== $retry_after) {
            $cooldown = max(1, min($retry_after, self::MAX_BACKOFF_SECONDS));
            $reason = 'rate_limited_retry_after';
            ++$count;
        } elseif (429 === $status_code) {
            $cooldown = $this->backoff($count);
            $reason = 'rate_limited_backoff';
            ++$count;
        } elseif ($status_code >= 500) {
            $cooldown = $this->backoff($count);
            $reason = 'server_error';
            ++$count;
        } elseif (0 === $status_code) {
            $cooldown = $this->backoff($count);
            $reason = 'network_error';
            ++$count;
        } else {
            $cooldown = self::BASE_BACKOFF_SECONDS;
            $reason = 'client_error';
            ++$count;
        }
        $this->write_state($scope, time() + $cooldown, $count, $reason);
        if (429 === $status_code) {
            $this->logger->warning(sprintf('PayPal token endpoint returned 429 for "%1$s". Retry-After: %2$s. Pausing token requests for %3$d seconds.', $scope, null === $retry_after ? 'absent' : (string) $retry_after, $cooldown), array('scope' => $scope, 'status' => $status_code, 'retry_after' => $retry_after, 'cooldown' => $cooldown));
        } elseif ('dead_credentials' === $reason) {
            $this->logger->warning(sprintf('PayPal rejected the credentials for "%1$s" (status %2$d). Pausing token requests for %3$d seconds; the merchant likely needs to reconnect.', $scope, $status_code, $cooldown), array('scope' => $scope, 'status' => $status_code, 'cooldown' => $cooldown));
        }
        return $cooldown;
    }
    /**
     * Clears the cool-down state for the given scope after a successful request.
     *
     * @param string $scope The token-type key.
     */
    public function clear(string $scope): void
    {
        $this->cache->delete($scope . self::STATE_KEY_SUFFIX);
    }
    /**
     * Parses the `Retry-After` response header into seconds-from-now.
     *
     * Handles both the integer-seconds form and the HTTP-date form. Returns null
     * when the header is absent or unparsable.
     *
     * @param array|WP_Error $response The response.
     */
    private function parse_retry_after($response): ?int
    {
        $raw = wp_remote_retrieve_header($response, 'retry-after');
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        if (is_numeric($raw)) {
            return max(0, (int) $raw);
        }
        $timestamp = strtotime($raw);
        if (\false === $timestamp) {
            return null;
        }
        return max(0, $timestamp - time());
    }
    /**
     * Computes the exponential backoff (with jitter) for a given failure count.
     *
     * @param int $count The number of consecutive failures so far.
     */
    private function backoff(int $count): int
    {
        $exponent = min($count, 16);
        $base = (int) min(self::BASE_BACKOFF_SECONDS * 2 ** $exponent, self::MAX_BACKOFF_SECONDS);
        $jitter = random_int(0, (int) max(1, $base / 4));
        return (int) min(self::MAX_BACKOFF_SECONDS, $base + $jitter);
    }
    /**
     * Whether the failure indicates rejected credentials that will not self-heal.
     *
     * @param int            $status_code The HTTP status code.
     * @param array|WP_Error $response The response.
     */
    private function is_dead_credentials(int $status_code, $response): bool
    {
        if (401 === $status_code) {
            return \true;
        }
        $body = wp_remote_retrieve_body($response);
        if (!is_string($body) || '' === $body) {
            return \false;
        }
        $json = json_decode($body);
        return is_object($json) && isset($json->error) && 'invalid_client' === $json->error;
    }
    /**
     * Reads the stored cool-down state for the given scope.
     *
     * @param string $scope The token-type key.
     */
    private function read_state(string $scope): array
    {
        $value = $this->cache->get($scope . self::STATE_KEY_SUFFIX);
        return is_array($value) ? $value : array();
    }
    /**
     * Writes the cool-down state for the given scope.
     *
     * @param string $scope The token-type key.
     * @param int    $retry_at The timestamp at which requests are allowed again.
     * @param int    $count The consecutive failure count.
     * @param string $reason The failure reason (for diagnostics).
     */
    private function write_state(string $scope, int $retry_at, int $count, string $reason): void
    {
        $cooldown = max(0, $retry_at - time());
        $this->cache->set($scope . self::STATE_KEY_SUFFIX, array('retry_at' => $retry_at, 'count' => $count, 'reason' => $reason), $cooldown + self::STATE_TTL_PADDING);
    }
}
