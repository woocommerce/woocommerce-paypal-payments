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
use WooCommerce\WooCommerce\Logging\Logger\NullLogger;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PartnersEndpointFactory;
use WooCommerce\PayPalCommerce\Settings\Data\GeneralSettings;
/**
 * Resolves and stores the raw PayPal-account country (`merchant_country`), only
 * available from PartnersEndpoint::seller_status().
 *
 * The seller-status back-off is deliberately never cleared here: it is the primary
 * flood protection and the retry cap is the backstop, so an unresolvable merchant
 * (e.g. a persistent 403) is not retried forever. Triggers must stay one-shot; a
 * per-request hook like `admin_init` would re-run the lookup on every page load.
 */
class MerchantDataResolver
{
    /**
     * ActionScheduler hook for the deferred resolution attempts.
     */
    public const RETRY_HOOK = 'woocommerce_paypal_payments_resolve_merchant_data';
    /**
     * Maximum deferred retries before giving up permanently.
     */
    private const MAX_ATTEMPTS = 3;
    /**
     * Base retry delay, grown linearly per attempt. Exceeds the 10-minute
     * seller-status back-off window so a retry is not short-circuited by it.
     */
    private const RETRY_DELAY = 15 * MINUTE_IN_SECONDS;
    private GeneralSettings $settings;
    private PartnersEndpointFactory $partners_endpoint_factory;
    private LoggerInterface $logger;
    public function __construct(GeneralSettings $settings, PartnersEndpointFactory $partners_endpoint_factory, ?LoggerInterface $logger = null)
    {
        $this->settings = $settings;
        $this->partners_endpoint_factory = $partners_endpoint_factory;
        $this->logger = $logger ?: new NullLogger();
    }
    /**
     * Inline entry point (upgrade/backfill): resolve now, else schedule a retry.
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
     */
    public function needs_resolution(): bool
    {
        if (!$this->settings->is_merchant_connected()) {
            return \false;
        }
        return '' === $this->settings->get_merchant_data()->merchant_country;
    }
    /**
     * Fetches the country from seller_status() and persists it. A backed-off or
     * failed call returns false without persisting.
     *
     * @return bool True when a non-empty country was fetched and stored.
     */
    private function resolve(): bool
    {
        $connection = $this->settings->get_merchant_data();
        try {
            $country = $this->partners_endpoint_factory->create($connection->is_sandbox, $connection->client_id, $connection->client_secret, $connection->merchant_id)->seller_status()->country();
        } catch (Throwable $exception) {
            $this->logger->warning('[MerchantDataResolver] Could not resolve merchant country: ' . $exception->getMessage());
            return \false;
        }
        if ('' === $country) {
            return \false;
        }
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
