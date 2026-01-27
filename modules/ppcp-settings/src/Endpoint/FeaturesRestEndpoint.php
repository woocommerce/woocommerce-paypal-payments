<?php

/**
 * REST endpoint to manage features.
 *
 * Provides endpoints for retrieving features via WP REST API routes.
 *
 * @package WooCommerce\PayPalCommerce\Settings\Endpoint
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Settings\Endpoint;

use WP_REST_Server;
use WP_REST_Response;
use WooCommerce\PayPalCommerce\Settings\Data\Definition\FeaturesDefinition;
/**
 * REST controller for the features in the Overview tab.
 *
 * This API acts as the intermediary between the "external world" and our
 * internal data model. It's responsible for checking eligibility and
 * providing configuration data for features.
 */
class FeaturesRestEndpoint extends \WooCommerce\PayPalCommerce\Settings\Endpoint\RestEndpoint
{
    /**
     * The base path for this REST controller.
     *
     * @var string
     */
    protected $rest_base = 'features';
    /**
     * The features definition instance.
     *
     * @var FeaturesDefinition
     */
    protected FeaturesDefinition $features_definition;
    /**
     * The settings endpoint instance.
     *
     * @var SettingsRestEndpoint
     */
    protected \WooCommerce\PayPalCommerce\Settings\Endpoint\SettingsRestEndpoint $settings;
    /**
     * FeaturesRestEndpoint constructor.
     *
     * @param FeaturesDefinition   $features_definition The features definition instance.
     * @param SettingsRestEndpoint $settings The settings endpoint instance.
     */
    public function __construct(FeaturesDefinition $features_definition, \WooCommerce\PayPalCommerce\Settings\Endpoint\SettingsRestEndpoint $settings)
    {
        $this->features_definition = $features_definition;
        $this->settings = $settings;
    }
    /**
     * Registers the REST API routes for features management.
     */
    public function register_routes(): void
    {
        // GET /features - Get features list.
        register_rest_route(static::NAMESPACE, '/' . $this->rest_base, array(array('methods' => WP_REST_Server::READABLE, 'callback' => array($this, 'get_features'), 'permission_callback' => array($this, 'check_permission'))));
    }
    /**
     * Retrieves the current features.
     *
     * @return WP_REST_Response The response containing features data.
     */
    public function get_features(): WP_REST_Response
    {
        $features = array();
        foreach ($this->features_definition->get() as $id => $feature) {
            $features[] = array_merge(array('id' => $id), $feature);
        }
        return $this->return_success(array('features' => $features));
    }
}
