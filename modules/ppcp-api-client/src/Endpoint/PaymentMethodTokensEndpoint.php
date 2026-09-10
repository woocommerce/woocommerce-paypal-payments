<?php

/**
 * The Payment Method Tokens endpoint.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Endpoint
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\ApiClient\Endpoint;

use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use stdClass;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\Bearer;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentSource;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
/**
 * Class PaymentMethodTokensEndpoint
 */
class PaymentMethodTokensEndpoint
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
     * The logger.
     *
     * @var LoggerInterface
     */
    private $logger;
    /**
     * PaymentMethodTokensEndpoint constructor.
     *
     * @param string          $host The host.
     * @param Bearer          $bearer The bearer.
     * @param LoggerInterface $logger The logger.
     */
    public function __construct(string $host, Bearer $bearer, LoggerInterface $logger)
    {
        $this->host = $host;
        $this->bearer = $bearer;
        $this->logger = $logger;
    }
    /**
     * Creates a setup token.
     *
     * @param PaymentSource $payment_source The payment source.
     * @param string        $customer_id PayPal customer ID.
     *
     * @return stdClass
     *
     * @throws RuntimeException When something when wrong with the request.
     * @throws PayPalApiException When something when wrong setting up the token.
     */
    public function setup_tokens(PaymentSource $payment_source, string $customer_id = ''): stdClass
    {
        $data = array('payment_source' => array($payment_source->name() => $payment_source->properties()));
        if ($customer_id) {
            $data['customer']['id'] = $customer_id;
        }
        $bearer = $this->bearer->bearer();
        $url = trailingslashit($this->host) . 'v3/vault/setup-tokens';
        $args = array('method' => 'POST', 'headers' => array('Authorization' => 'Bearer ' . $bearer->token(), 'Content-Type' => 'application/json', 'PayPal-Request-Id' => uniqid('ppcp-', \true)), 'body' => wp_json_encode($data));
        $response = $this->request($url, $args);
        if (is_wp_error($response) || !is_array($response)) {
            throw new RuntimeException('Not able to create setup token.');
        }
        $json = json_decode($response['body']);
        $status_code = (int) wp_remote_retrieve_response_code($response);
        if (!in_array($status_code, array(200, 201), \true)) {
            throw new PayPalApiException($json, $status_code);
        }
        return $json;
    }
    /**
     * Creates a payment token for the given payment source.
     *
     * @param PaymentSource $payment_source The payment source.
     * @param string        $customer_id PayPal customer ID.
     *
     * @return stdClass
     *
     * @throws RuntimeException When something when wrong with the request.
     * @throws PayPalApiException When something when wrong setting up the token.
     */
    public function create_payment_token(PaymentSource $payment_source, string $customer_id = ''): stdClass
    {
        $data = array('payment_source' => array($payment_source->name() => $payment_source->properties()));
        if ($customer_id) {
            $data['customer']['id'] = $customer_id;
        }
        $bearer = $this->bearer->bearer();
        $url = trailingslashit($this->host) . 'v3/vault/payment-tokens';
        $args = array('method' => 'POST', 'headers' => array('Authorization' => 'Bearer ' . $bearer->token(), 'Content-Type' => 'application/json', 'PayPal-Request-Id' => uniqid('ppcp-', \true)), 'body' => wp_json_encode($data));
        $response = $this->request($url, $args);
        if (is_wp_error($response) || !is_array($response)) {
            throw new RuntimeException('Not able to create setup token.');
        }
        $json = json_decode($response['body']);
        $status_code = (int) wp_remote_retrieve_response_code($response);
        if (!in_array($status_code, array(200, 201), \true)) {
            throw new PayPalApiException($json, $status_code);
        }
        return $json;
    }
}
