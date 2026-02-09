<?php

/**
 * REST endpoint to refresh feature status.
 *
 * @package WooCommerce\PayPalCommerce\Settings\Endpoint
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Settings\Endpoint;

use WP_REST_Server;
use WP_REST_Response;
use WP_REST_Request;
use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Helper\Cache;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
/**
 * REST controller for refreshing feature status.
 */
class RefreshFeatureStatusEndpoint extends \WooCommerce\PayPalCommerce\Settings\Endpoint\RestEndpoint
{
    /**
     * The base path for this REST controller.
     *
     * @var string
     */
    protected $rest_base = 'refresh-features';
    /**
     * Cache timeout in seconds.
     *
     * @var int
     */
    private const TIMEOUT = 60;
    /**
     * Cache key for tracking request timeouts.
     *
     * @var string
     */
    private const CACHE_KEY = 'refresh_feature_status_timeout';
    /**
     * The settings.
     *
     * @var ContainerInterface
     */
    protected ContainerInterface $settings;
    /**
     * The cache.
     *
     * @var Cache
     */
    protected Cache $cache;
    /**
     * The logger.
     *
     * @var LoggerInterface
     */
    protected LoggerInterface $logger;
    /**
     * Constructor.
     *
     * @param ContainerInterface $settings The settings.
     * @param Cache              $cache    The cache.
     * @param LoggerInterface    $logger   The logger.
     */
    public function __construct(ContainerInterface $settings, Cache $cache, LoggerInterface $logger)
    {
        $this->settings = $settings;
        $this->cache = $cache;
        $this->logger = $logger;
    }
    /**
     * Configure REST API routes.
     */
    public function register_routes(): void
    {
        /**
         * POST /wp-json/wc/v3/wc_paypal/refresh-features
         */
        register_rest_route(static::NAMESPACE, '/' . $this->rest_base, array('methods' => WP_REST_Server::EDITABLE, 'callback' => array($this, 'refresh_status'), 'permission_callback' => array($this, 'check_permission')));
    }
    /**
     * Handles the refresh status request.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_REST_Response
     */
    public function refresh_status(WP_REST_Request $request): WP_REST_Response
    {
        $now = time();
        $last_request_time = $this->cache->get(self::CACHE_KEY) ?: 0;
        $seconds_missing = $last_request_time + self::TIMEOUT - $now;
        if ($seconds_missing > 0) {
            return $this->return_error(sprintf(
                // translators: %1$s is the number of seconds remaining.
                __('Wait %1$s seconds before trying again.', 'woocommerce-paypal-payments'),
                $seconds_missing
            ));
        }
        $this->cache->set(self::CACHE_KEY, $now, self::TIMEOUT);
        do_action('woocommerce_paypal_payments_clear_apm_product_status', $this->settings);
        $this->logger->info('Feature status refreshed successfully');
        return $this->return_success(array('message' => __('Feature status refreshed successfully.', 'woocommerce-paypal-payments')));
    }
}
