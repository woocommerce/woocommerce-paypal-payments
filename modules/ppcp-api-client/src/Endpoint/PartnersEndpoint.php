<?php

/**
 * The Partners Endpoint.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Endpoint
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\ApiClient\Endpoint;

use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\Bearer;
use WooCommerce\PayPalCommerce\ApiClient\Entity\SellerStatus;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\ApiClient\Factory\SellerStatusFactory;
use WooCommerce\PayPalCommerce\ApiClient\Helper\Cache;
use WooCommerce\PayPalCommerce\ApiClient\Helper\FailureRegistry;
/**
 * Class PartnersEndpoint
 */
class PartnersEndpoint
{
    use \WooCommerce\PayPalCommerce\ApiClient\Endpoint\RequestTrait;
    /**
     * The Host URL.
     *
     * @var string
     */
    private $host;
    /**
     * The bearer.
     *
     * @var Bearer
     */
    private $bearer;
    /**
     * The logger.
     *
     * @var LoggerInterface
     */
    private $logger;
    /**
     * The seller status factory.
     *
     * @var SellerStatusFactory
     */
    private $seller_status_factory;
    /**
     * The partner ID.
     *
     * @var string
     */
    private $partner_id;
    /**
     * The merchant ID.
     *
     * @var string
     */
    private $merchant_id;
    /**
     * The failure registry.
     *
     * @var FailureRegistry
     */
    private $failure_registry;
    /**
     * The cache for seller status responses.
     *
     * @var Cache
     */
    private Cache $cache;
    /**
     * Cache lifetime for seller status responses, in seconds.
     */
    public const SELLER_STATUS_CACHE_TTL = 600;
    // 10 minutes.
    /**
     * Cache key for the seller status response.
     */
    public const SELLER_STATUS_CACHE_KEY = 'seller_status';
    /**
     * PartnersEndpoint constructor.
     *
     * @param string              $host The host.
     * @param Bearer              $bearer The bearer.
     * @param LoggerInterface     $logger The logger.
     * @param SellerStatusFactory $seller_status_factory The seller status factory.
     * @param string              $partner_id The partner ID.
     * @param string              $merchant_id The merchant ID.
     * @param FailureRegistry     $failure_registry The API failure registry.
     * @param Cache               $cache The cache for seller status responses.
     */
    public function __construct(string $host, Bearer $bearer, LoggerInterface $logger, SellerStatusFactory $seller_status_factory, string $partner_id, string $merchant_id, FailureRegistry $failure_registry, Cache $cache)
    {
        $this->host = $host;
        $this->bearer = $bearer;
        $this->logger = $logger;
        $this->seller_status_factory = $seller_status_factory;
        $this->partner_id = $partner_id;
        $this->merchant_id = $merchant_id;
        $this->failure_registry = $failure_registry;
        $this->cache = $cache;
    }
    /**
     * Returns the current seller status.
     *
     * Uses a transient cache to avoid redundant API calls. The cached response
     * is returned for up to 10 minutes before a fresh request is made.
     *
     * @return SellerStatus
     * @throws RuntimeException When request could not be fulfilled.
     */
    public function seller_status(): SellerStatus
    {
        $cached = $this->cache->get(self::SELLER_STATUS_CACHE_KEY);
        if ($cached instanceof SellerStatus) {
            return $cached;
        }
        $url = trailingslashit($this->host) . 'v1/customer/partners/' . $this->partner_id . '/merchant-integrations/' . $this->merchant_id;
        $bearer = $this->bearer->bearer();
        $args = array('method' => 'GET', 'headers' => array('Authorization' => 'Bearer ' . $bearer->token(), 'Content-Type' => 'application/json'));
        $response = $this->request($url, $args);
        if (is_wp_error($response)) {
            $error = new RuntimeException(__('Could not fetch sellers status.', 'woocommerce-paypal-payments'));
            $this->logger->log('warning', $error->getMessage(), array('args' => $args, 'response' => $response));
            throw $error;
        }
        $json = json_decode(wp_remote_retrieve_body($response));
        $status_code = (int) wp_remote_retrieve_response_code($response);
        if (200 !== $status_code) {
            $error = new PayPalApiException($json, $status_code);
            $this->logger->log('warning', $error->getMessage(), array('args' => $args, 'response' => $response));
            // Register the failure on api failure registry.
            $this->failure_registry->add_failure(FailureRegistry::SELLER_STATUS_KEY);
            throw $error;
        }
        $this->failure_registry->clear_failures(FailureRegistry::SELLER_STATUS_KEY);
        $status = $this->seller_status_factory->from_paypal_response($json);
        $this->cache->set(self::SELLER_STATUS_CACHE_KEY, $status, self::SELLER_STATUS_CACHE_TTL);
        return $status;
    }
    /**
     * Clears the cached seller status response, forcing a fresh API call
     * on the next invocation of seller_status().
     *
     * @return void
     */
    public function clear_seller_status_cache(): void
    {
        $this->cache->delete(self::SELLER_STATUS_CACHE_KEY);
    }
}
