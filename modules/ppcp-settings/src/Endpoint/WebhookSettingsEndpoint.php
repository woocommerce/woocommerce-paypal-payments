<?php

/**
 * REST endpoint to manage the onboarding module.
 *
 * @package WooCommerce\PayPalCommerce\Settings\Endpoint
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Settings\Endpoint;

use Throwable;
use WP_REST_Response;
use WP_REST_Server;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\WebhookEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Webhook;
use WooCommerce\PayPalCommerce\Webhooks\Status\WebhookSimulation;
use WooCommerce\PayPalCommerce\Webhooks\WebhookRegistrar;
use stdClass;
/**
 * Class WebhookSettingsEndpoint
 *
 * Note: Endpoint for webhook related requests
 */
class WebhookSettingsEndpoint extends \WooCommerce\PayPalCommerce\Settings\Endpoint\RestEndpoint
{
    /**
     * Endpoint base to fetch webhook settings and resubscribe
     *
     * @var string
     */
    protected $rest_base = 'webhooks';
    /**
     * Application webhook endpoint
     *
     * @var WebhookEndpoint
     */
    private WebhookEndpoint $webhook_endpoint;
    /**
     * A service that allows resubscribing webhooks
     *
     * @var WebhookRegistrar
     */
    private WebhookRegistrar $webhook_registrar;
    /**
     * A service that allows webhook simulations
     *
     * @var WebhookSimulation
     */
    private WebhookSimulation $webhook_simulation;
    /**
     * WebhookSettingsEndpoint constructor.
     *
     * @param WebhookEndpoint   $webhook_endpoint   A list of subscribed webhooks and a webhook
     *                                              endpoint URL.
     * @param WebhookRegistrar  $webhook_registrar  A service that allows resubscribing webhooks.
     * @param WebhookSimulation $webhook_simulation A service that allows webhook simulations.
     */
    public function __construct(WebhookEndpoint $webhook_endpoint, WebhookRegistrar $webhook_registrar, WebhookSimulation $webhook_simulation)
    {
        $this->webhook_endpoint = $webhook_endpoint;
        $this->webhook_registrar = $webhook_registrar;
        $this->webhook_simulation = $webhook_simulation;
    }
    /**
     * Configure REST API routes.
     */
    public function register_routes(): void
    {
        /**
         * GET /wp-json/wc/v3/wc_paypal/webhooks
         * POST /wp-json/wc/v3/wc_paypal/webhooks
         */
        register_rest_route(static::NAMESPACE, '/' . $this->rest_base, array(array('methods' => WP_REST_Server::READABLE, 'callback' => array($this, 'get_webhooks'), 'permission_callback' => array($this, 'check_permission')), array('methods' => WP_REST_Server::CREATABLE, 'callback' => array($this, 'resubscribe_webhooks'), 'permission_callback' => array($this, 'check_permission'))));
        /**
         * GET /wp-json/wc/v3/wc_paypal/webhooks/simulate
         * POST /wp-json/wc/v3/wc_paypal/webhooks/simulate
         */
        register_rest_route(static::NAMESPACE, '/' . $this->rest_base . '/simulate', array(array('methods' => WP_REST_Server::READABLE, 'callback' => array($this, 'check_simulated_webhook_state'), 'permission_callback' => array($this, 'check_permission')), array('methods' => WP_REST_Server::CREATABLE, 'callback' => array($this, 'simulate_webhooks_start'), 'permission_callback' => array($this, 'check_permission'))));
    }
    /**
     * Returns a webhook endpoint URL and list of subscribed webhooks
     *
     * @return WP_REST_Response
     */
    public function get_webhooks(): WP_REST_Response
    {
        $webhooks = $this->get_webhook_data();
        if (!$webhooks) {
            return $this->return_error('No webhooks found.');
        }
        try {
            $webhook_url = $webhooks->url();
            $webhook_events = array_map(static fn(stdClass $webhooks) => strtolower($webhooks->name), $webhooks->event_types());
        } catch (Throwable $error) {
            return $this->return_error($error->getMessage());
        }
        return $this->return_success(array('url' => $webhook_url, 'events' => $webhook_events));
    }
    /**
     * Re-subscribes webhooks and returns webhooks
     *
     * @return WP_REST_Response
     */
    public function resubscribe_webhooks(): WP_REST_Response
    {
        if (!$this->webhook_registrar->register()) {
            return $this->return_error('Webhook subscription failed.');
        }
        return $this->get_webhooks();
    }
    /**
     * Starts webhook simulation
     *
     * @return WP_REST_Response
     */
    public function simulate_webhooks_start(): WP_REST_Response
    {
        try {
            $this->webhook_simulation->start();
            return $this->return_success(array());
        } catch (\Exception $error) {
            return $this->return_error($error->getMessage());
        }
    }
    /**
     * Checks webhook simulation state
     *
     * @return WP_REST_Response
     */
    public function check_simulated_webhook_state(): WP_REST_Response
    {
        try {
            $state = $this->webhook_simulation->get_state();
            return $this->return_success(array('state' => $state));
        } catch (\Exception $error) {
            return $this->return_error($error->getMessage());
        }
    }
    /**
     * Retrieves the Webhooks API response object.
     *
     * @return Webhook|null The webhook data instance, or null.
     */
    private function get_webhook_data(): ?Webhook
    {
        try {
            $api_response = $this->webhook_endpoint->list();
            return $api_response[0] ?? null;
        } catch (Throwable $error) {
            return null;
        }
    }
}
