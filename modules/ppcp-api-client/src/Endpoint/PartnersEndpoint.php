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
     * PartnersEndpoint constructor.
     *
     * @param string              $host The host.
     * @param Bearer              $bearer The bearer.
     * @param LoggerInterface     $logger The logger.
     * @param SellerStatusFactory $seller_status_factory The seller status factory.
     * @param string              $partner_id The partner ID.
     * @param string              $merchant_id The merchant ID.
     * @param FailureRegistry     $failure_registry The API failure registry.
     */
    public function __construct(string $host, Bearer $bearer, LoggerInterface $logger, SellerStatusFactory $seller_status_factory, string $partner_id, string $merchant_id, FailureRegistry $failure_registry)
    {
        $this->host = $host;
        $this->bearer = $bearer;
        $this->logger = $logger;
        $this->seller_status_factory = $seller_status_factory;
        $this->partner_id = $partner_id;
        $this->merchant_id = $merchant_id;
        $this->failure_registry = $failure_registry;
    }
    /**
     * Returns the current seller status.
     *
     * @return SellerStatus
     * @throws RuntimeException When request could not be fulfilled.
     */
    public function seller_status(): SellerStatus
    {
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
        return $status;
    }
}
