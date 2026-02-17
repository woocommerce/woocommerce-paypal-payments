<?php

/**
 * Controls the endpoint for refreshing feature status.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Endpoint
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\WcGateway\Endpoint;

use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Helper\Cache;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
/**
 * Class RefreshFeatureStatusEndpoint
 */
class RefreshFeatureStatusEndpoint
{
    const ENDPOINT = 'ppc-refresh-feature-status';
    const CACHE_KEY = 'refresh_feature_status_timeout';
    const TIMEOUT = 60;
    /**
     * The settings.
     *
     * @var ContainerInterface
     */
    protected $settings;
    /**
     * The cache.
     *
     * @var Cache
     */
    protected $cache;
    /**
     * The logger.
     *
     * @var LoggerInterface
     */
    protected $logger;
    /**
     * RefreshFeatureStatusEndpoint constructor.
     *
     * @param ContainerInterface $settings The settings.
     * @param Cache              $cache The cache.
     * @param LoggerInterface    $logger The logger.
     */
    public function __construct(ContainerInterface $settings, Cache $cache, LoggerInterface $logger)
    {
        $this->settings = $settings;
        $this->cache = $cache;
        $this->logger = $logger;
    }
    /**
     * Returns the nonce.
     *
     * @return string
     */
    public static function nonce(): string
    {
        return self::ENDPOINT;
    }
    /**
     * Handles the incoming request.
     */
    public function handle_request(): void
    {
        $now = time();
        $last_request_time = $this->cache->get(self::CACHE_KEY) ?: 0;
        $seconds_missing = $last_request_time + self::TIMEOUT - $now;
        if (!$this->verify_nonce()) {
            wp_send_json_error(array('message' => __('Expired request.', 'woocommerce-paypal-payments')));
        }
        if ($seconds_missing > 0) {
            wp_send_json_error(array('message' => sprintf(
                // translators: %1$s is the number of seconds remaining.
                __('Wait %1$s seconds before trying again.', 'woocommerce-paypal-payments'),
                $seconds_missing
            )));
        }
        $this->cache->set(self::CACHE_KEY, $now, self::TIMEOUT);
        do_action('woocommerce_paypal_payments_clear_apm_product_status', $this->settings);
        wp_send_json_success();
    }
    /**
     * Verifies the nonce.
     *
     * @return bool
     */
    private function verify_nonce(): bool
    {
        $json = json_decode(file_get_contents('php://input') ?: '', \true);
        return wp_verify_nonce($json['nonce'] ?? '', self::nonce()) !== \false;
    }
}
