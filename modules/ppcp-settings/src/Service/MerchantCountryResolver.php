<?php

/**
 * Populates the merchant's PayPal-account country from the seller-status API.
 *
 * @package WooCommerce\PayPalCommerce\Settings\Service
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Settings\Service;

use Throwable;
use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PartnersEndpoint;
use WooCommerce\WooCommerce\Logging\Logger\NullLogger;
use WooCommerce\PayPalCommerce\Settings\Data\GeneralSettings;
/**
 * Resolves and stores the raw PayPal-account country (`merchant_country`), which
 * is only available from PartnersEndpoint::seller_status().
 *
 * The seller_status() is called directly, in-process (no loopback/cookies), so it
 * works in any request context. The seller-status failure back-off is never
 * cleared here: that throttle is the primary flood protection, and a hard retry
 * cap is the secondary safeguard, so a merchant whose country cannot be resolved
 * (e.g. a persistent 403) is not retried forever.
 *
 * Triggers must stay one-shot (connect, plugin upgrade). Hooking this to a
 * per-request event like `admin_init` would re-trigger the lookup on every page
 * load for an unresolvable merchant.
 */
class MerchantCountryResolver
{
    /**
     * ActionScheduler hook for the background retry.
     */
    public const RETRY_HOOK = 'woocommerce_paypal_payments_resolve_merchant_country';
    /**
     * Maximum deferred retries before giving up permanently.
     */
    private const MAX_ATTEMPTS = 3;
    /**
     * Base retry delay. Grows linearly per attempt and sits past the seller-status
     * back-off window (10 minutes), so a retry is not short-circuited by it.
     */
    private const RETRY_DELAY = 15 * MINUTE_IN_SECONDS;
    private GeneralSettings $settings;
    private PartnersEndpoint $partners_endpoint;
    private LoggerInterface $logger;
    public function __construct(GeneralSettings $settings, PartnersEndpoint $partners_endpoint, ?LoggerInterface $logger = null)
    {
        $this->settings = $settings;
        $this->partners_endpoint = $partners_endpoint;
        $this->logger = $logger ?: new NullLogger();
    }
    /**
     * One-shot entry point (connect + upgrade): resolve now, else schedule a retry.
     */
    public function ensure_country_resolved(): void
    {
        if (!$this->needs_resolution()) {
            return;
        }
        if ($this->resolve()) {
            return;
        }
        $this->schedule_retry(1);
    }
    /**
     * ActionScheduler retry handler.
     *
     * @param int $attempt Current attempt number (1-based).
     */
    public function handle_retry(int $attempt = 1): void
    {
        if (!$this->needs_resolution()) {
            return;
        }
        if ($this->resolve()) {
            return;
        }
        $this->schedule_retry($attempt + 1);
    }
    /**
     * Whether the connected merchant still has an empty country to resolve.
     *
     * Note: if any other details are extracted from the PayPal API in the
     * future we need to update the conditions in this method.
     */
    public function needs_resolution(): bool
    {
        if (!$this->settings->is_merchant_connected()) {
            return \false;
        }
        return '' === $this->settings->get_merchant_data()->merchant_country;
    }
    /**
     * Fetches the country from seller_status() and persists it. Respects the
     * back-off: a backed-off or failed call returns false without an API hit.
     *
     * @return bool True when a non-empty country was fetched and stored.
     */
    private function resolve(): bool
    {
        try {
            $country = $this->partners_endpoint->seller_status()->country();
        } catch (Throwable $exception) {
            $this->logger->warning('[MerchantDataResolver] Could not resolve merchant country: ' . $exception->getMessage());
            return \false;
        }
        if ('' === $country) {
            return \false;
        }
        $connection = $this->settings->get_merchant_data();
        $connection->merchant_country = $country;
        $this->settings->set_merchant_data($connection);
        $this->settings->save();
        return \true;
    }
    /**
     * Schedules one deferred retry, unless capped or one is already pending.
     *
     * @param int $attempt The attempt number to run.
     */
    private function schedule_retry(int $attempt): void
    {
        if ($attempt > self::MAX_ATTEMPTS) {
            $this->logger->warning('[MerchantDataResolver] Stop trying to schedule retry after max attempts.');
            return;
        }
        if (!function_exists('as_schedule_single_action')) {
            return;
        }
        $args = array('attempt' => $attempt);
        if (as_next_scheduled_action(self::RETRY_HOOK, $args)) {
            return;
        }
        as_schedule_single_action(time() + $attempt * self::RETRY_DELAY, self::RETRY_HOOK, $args);
    }
}
