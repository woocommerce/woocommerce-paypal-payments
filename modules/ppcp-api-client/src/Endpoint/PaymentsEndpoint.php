<?php

/**
 * The payments endpoint.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Endpoint
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\ApiClient\Endpoint;

use WooCommerce\PayPalCommerce\ApiClient\Authentication\Bearer;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Authorization;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Capture;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Money;
use WooCommerce\PayPalCommerce\ApiClient\Entity\RefundCapture;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\ApiClient\Factory\AuthorizationFactory;
use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Factory\CaptureFactory;
/**
 * Class PaymentsEndpoint
 */
class PaymentsEndpoint
{
    use \WooCommerce\PayPalCommerce\ApiClient\Endpoint\RequestTrait;
    /**
     * The host.
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
     * The authorization factory.
     *
     * @var AuthorizationFactory
     */
    private $authorizations_factory;
    /**
     * The capture factory.
     *
     * @var CaptureFactory
     */
    private $capture_factory;
    /**
     * The logger.
     *
     * @var LoggerInterface
     */
    private $logger;
    /**
     * PaymentsEndpoint constructor.
     *
     * @param string               $host The host.
     * @param Bearer               $bearer The bearer.
     * @param AuthorizationFactory $authorization_factory The authorization factory.
     * @param CaptureFactory       $capture_factory The capture factory.
     * @param LoggerInterface      $logger The logger.
     */
    public function __construct(string $host, Bearer $bearer, AuthorizationFactory $authorization_factory, CaptureFactory $capture_factory, LoggerInterface $logger)
    {
        $this->host = $host;
        $this->bearer = $bearer;
        $this->authorizations_factory = $authorization_factory;
        $this->capture_factory = $capture_factory;
        $this->logger = $logger;
    }
    /**
     * Fetch an authorization by a given id.
     *
     * @param string $authorization_id The id.
     *
     * @return Authorization
     * @throws RuntimeException If the request fails.
     */
    public function authorization(string $authorization_id): Authorization
    {
        $bearer = $this->bearer->bearer();
        $url = trailingslashit($this->host) . 'v2/payments/authorizations/' . $authorization_id;
        $args = array('headers' => array('Authorization' => 'Bearer ' . $bearer->token(), 'Content-Type' => 'application/json', 'Prefer' => 'return=representation'));
        $response = $this->request($url, $args);
        $json = json_decode($response['body']);
        if (is_wp_error($response)) {
            $error = new RuntimeException(__('Could not get authorized payment info.', 'woocommerce-paypal-payments'));
            $this->logger->log('warning', $error->getMessage(), array('args' => $args, 'response' => $response));
            throw $error;
        }
        $status_code = (int) wp_remote_retrieve_response_code($response);
        if (200 !== $status_code) {
            $error = new PayPalApiException($json, $status_code);
            $this->logger->log('warning', $error->getMessage(), array('args' => $args, 'response' => $response));
            throw $error;
        }
        $authorization = $this->authorizations_factory->from_paypal_response($json);
        return $authorization;
    }
    /**
     * Capture an authorization by a given ID.
     *
     * @param string     $authorization_id The id.
     * @param Money|null $amount The amount to capture. If not specified, the whole authorized amount is captured.
     *
     * @return Capture
     * @throws RuntimeException If the request fails.
     * @throws PayPalApiException If the request fails.
     */
    public function capture(string $authorization_id, ?Money $amount = null): Capture
    {
        $bearer = $this->bearer->bearer();
        $url = trailingslashit($this->host) . 'v2/payments/authorizations/' . $authorization_id . '/capture';
        $data = array('final_capture' => \true);
        if ($amount) {
            $data['amount'] = $amount->to_array();
        }
        $args = array('method' => 'POST', 'headers' => array('Authorization' => 'Bearer ' . $bearer->token(), 'Content-Type' => 'application/json', 'Prefer' => 'return=representation'), 'body' => wp_json_encode($data, \JSON_FORCE_OBJECT));
        $response = $this->request($url, $args);
        $json = json_decode($response['body']);
        if (is_wp_error($response)) {
            throw new RuntimeException('Could not capture authorized payment.');
        }
        $status_code = (int) wp_remote_retrieve_response_code($response);
        if (201 !== $status_code) {
            throw new PayPalApiException($json, $status_code);
        }
        return $this->capture_factory->from_paypal_response($json);
    }
    /**
     * Reauthorizes an order.
     *
     * @param string     $authorization_id The id.
     * @param Money|null $amount The amount to capture. If not specified, the whole authorized amount is captured.
     *
     * @return string
     * @throws RuntimeException If the request fails.
     * @throws PayPalApiException If the request fails.
     */
    public function reauthorize(string $authorization_id, ?Money $amount = null): string
    {
        $bearer = $this->bearer->bearer();
        $url = trailingslashit($this->host) . 'v2/payments/authorizations/' . $authorization_id . '/reauthorize';
        $data = array();
        if ($amount) {
            $data['amount'] = $amount->to_array();
        }
        $args = array('method' => 'POST', 'headers' => array('Authorization' => 'Bearer ' . $bearer->token(), 'Content-Type' => 'application/json', 'Prefer' => 'return=representation'), 'body' => wp_json_encode($data, \JSON_FORCE_OBJECT));
        $response = $this->request($url, $args);
        $json = json_decode($response['body']);
        if (is_wp_error($response)) {
            throw new RuntimeException('Could not reauthorize authorized payment.');
        }
        $status_code = (int) wp_remote_retrieve_response_code($response);
        if (201 !== $status_code || !is_object($json)) {
            throw new PayPalApiException($json, $status_code);
        }
        return $json->id;
    }
    /**
     * Refunds a payment.
     *
     * @param RefundCapture $refund The refund to be processed.
     *
     * @return string Refund ID.
     * @throws RuntimeException If the request fails.
     * @throws PayPalApiException If the request fails.
     */
    public function refund(RefundCapture $refund): string
    {
        $bearer = $this->bearer->bearer();
        $url = trailingslashit($this->host) . 'v2/payments/captures/' . $refund->for_capture()->id() . '/refund';
        $args = array('method' => 'POST', 'headers' => array('Authorization' => 'Bearer ' . $bearer->token(), 'Content-Type' => 'application/json', 'Prefer' => 'return=representation'), 'body' => wp_json_encode($refund->to_array()));
        $response = $this->request($url, $args);
        if (is_wp_error($response)) {
            throw new RuntimeException('Could not refund payment.');
        }
        $status_code = (int) wp_remote_retrieve_response_code($response);
        $json = json_decode($response['body']);
        if (201 !== $status_code || !is_object($json)) {
            throw new PayPalApiException($json, $status_code);
        }
        return $json->id;
    }
    /**
     * Voids a transaction.
     *
     * @param Authorization $authorization The PayPal payment authorization to void.
     *
     * @return void
     * @throws RuntimeException If the request fails.
     * @throws PayPalApiException If the request fails.
     */
    public function void(Authorization $authorization): void
    {
        $bearer = $this->bearer->bearer();
        $url = trailingslashit($this->host) . 'v2/payments/authorizations/' . $authorization->id() . '/void';
        $args = array('method' => 'POST', 'headers' => array('Authorization' => 'Bearer ' . $bearer->token(), 'Content-Type' => 'application/json', 'Prefer' => 'return=representation'));
        $response = $this->request($url, $args);
        if (is_wp_error($response)) {
            throw new RuntimeException('Could not void transaction.');
        }
        $status_code = (int) wp_remote_retrieve_response_code($response);
        // Currently it can return body with 200 status, despite the docs saying that it should be 204 No content.
        // We don't care much about body, so just checking that it was successful.
        if ($status_code < 200 || $status_code > 299) {
            throw new PayPalApiException(null, $status_code);
        }
    }
}
