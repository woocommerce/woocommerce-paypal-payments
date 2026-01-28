<?php

/**
 * The endpoint to log entries from frontend.
 *
 * @package WooCommerce\PayPalCommerce\Button\Endpoint
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Axo\Endpoint;

use Exception;
use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\Button\Endpoint\EndpointInterface;
use WooCommerce\PayPalCommerce\Button\Endpoint\RequestData;
/**
 * Class FrontendLoggerEndpoint
 */
class FrontendLogger implements EndpointInterface
{
    const ENDPOINT = 'ppc-frontend-logger';
    /**
     * The request data helper.
     *
     * @var RequestData
     */
    private $request_data;
    /**
     * The logger.
     *
     * @var LoggerInterface
     */
    private $logger;
    /**
     * FrontendLoggerEndpoint constructor.
     *
     * @param RequestData     $request_data The request data helper.
     * @param LoggerInterface $logger The logger.
     */
    public function __construct(RequestData $request_data, LoggerInterface $logger)
    {
        $this->request_data = $request_data;
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
     * Handles the request.
     *
     * @return bool
     * @throws Exception On Error.
     */
    public function handle_request(): bool
    {
        $data = $this->request_data->read_request($this->nonce());
        $level = $data['log']['level'] ?? 'info';
        switch ($level) {
            case 'error':
                $this->logger->error('[AXO] ' . $data['log']['message']);
                break;
            default:
                $this->logger->info('[AXO] ' . $data['log']['message']);
                break;
        }
        wp_send_json_success();
        return \true;
    }
}
