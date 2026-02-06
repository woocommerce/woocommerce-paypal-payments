<?php

/**
 * The endpoint for starting webhooks simulation.
 *
 * @package WooCommerce\PayPalCommerce\Webhooks\Endpoint
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Webhooks\Endpoint;

use Exception;
use WooCommerce\PayPalCommerce\Button\Endpoint\RequestData;
use WooCommerce\PayPalCommerce\Webhooks\Status\WebhookSimulation;
/**
 * Class SimulateEndpoint
 */
class SimulateEndpoint
{
    const ENDPOINT = 'ppc-webhooks-simulate';
    /**
     * The simulation handler.
     *
     * @var WebhookSimulation
     */
    private $simulation;
    /**
     * The Request Data helper object.
     *
     * @var RequestData
     */
    private $request_data;
    /**
     * SimulateEndpoint constructor.
     *
     * @param WebhookSimulation $simulation The simulation handler.
     * @param RequestData       $request_data The Request Data helper object.
     */
    public function __construct(WebhookSimulation $simulation, RequestData $request_data)
    {
        $this->simulation = $simulation;
        $this->request_data = $request_data;
    }
    /**
     * Returns the nonce for the endpoint.
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
    public function handle_request()
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Not admin.', 403);
            return \false;
        }
        try {
            // Validate nonce.
            $this->request_data->read_request($this->nonce());
            $this->simulation->start();
            wp_send_json_success();
            return \true;
        } catch (Exception $error) {
            wp_send_json_error($error->getMessage(), 500);
            return \false;
        }
    }
}
