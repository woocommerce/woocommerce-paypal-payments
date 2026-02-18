<?php

/**
 * Manages (generates, returns) Onboarding URL instances.
 *
 * @package WooCommerce\PayPalCommerce\Settings\Service
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Settings\Service;

use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Helper\Cache;
use WooCommerce\WooCommerce\Logging\Logger\NullLogger;
// TODO: Replace the OnboardingUrl with a new implementation for this module.
use WooCommerce\PayPalCommerce\Onboarding\Helper\OnboardingUrl;
/**
 * Manages (generates, returns) Onboarding URL instances.
 *
 * Those instances cannot be generated during boot time, as some details - like
 * the user-ID - are not available at that time. This manager allows accessing
 * Onboarding URL features at a later point.
 *
 * It's also a helper to transition from the legacy OnboardingURL to a new class
 * without having to re-write all token-related details just yet.
 */
class OnboardingUrlManager
{
    /**
     * Cache instance for onboarding token.
     *
     * @var Cache
     */
    private Cache $cache;
    /**
     * Logger instance, mainly used for debugging purposes.
     *
     * @var LoggerInterface
     */
    private LoggerInterface $logger;
    /**
     * Constructor.
     *
     * @param Cache            $cache  Cache instance for onboarding token.
     * @param ?LoggerInterface $logger The logger, for debugging purposes.
     */
    public function __construct(Cache $cache, ?LoggerInterface $logger = null)
    {
        $this->cache = $cache;
        $this->logger = $logger ?: new NullLogger();
    }
    /**
     * Returns a new Onboarding Url instance.
     *
     * @param string $cache_key_prefix The prefix for the cache entry.
     * @param int    $user_id          User ID to associate the link with.
     *
     * @return OnboardingUrl
     */
    public function get(string $cache_key_prefix, int $user_id): OnboardingUrl
    {
        return new OnboardingUrl($this->cache, $cache_key_prefix, $user_id);
    }
    /**
     * Validates the authentication token; if it's valid, the token is instantly
     * invalidated (deleted), so it cannot be validated again.
     *
     * @param string $token   The token to validate.
     * @param int    $user_id User ID who generated the token.
     *
     * @return bool True, if the token is valid. False otherwise.
     */
    public function validate_token_and_delete(string $token, int $user_id): bool
    {
        if ($user_id < 1 || strlen($token) < 10) {
            return \false;
        }
        $log_token = (string) substr($token, 0, 2) . '...' . (string) substr($token, -6);
        $this->logger->debug('Validating onboarding ppcpToken: ' . $log_token);
        if (OnboardingUrl::validate_token_and_delete($this->cache, $token, $user_id)) {
            $this->logger->info('Validated onboarding ppcpToken: ' . $log_token);
            return \true;
        }
        $this->logger->error('Failed to validate onboarding ppcpToken: ' . $log_token);
        return \false;
    }
}
