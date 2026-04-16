<?php

/**
 * REST endpoint to manage the common module.
 *
 * @package WooCommerce\PayPalCommerce\Settings\Endpoint
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Settings\Endpoint;

use Exception;
use WP_REST_Server;
use WP_REST_Response;
use WP_REST_Request;
use WooCommerce\PayPalCommerce\Settings\Data\GeneralSettings;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PartnersEndpoint;
/**
 * REST controller for "common" settings, which are used and modified by
 * multiple components. Those settings mainly define connection details.
 *
 * This API acts as the intermediary between the "external world" and our
 * internal data model.
 */
class CommonRestEndpoint extends \WooCommerce\PayPalCommerce\Settings\Endpoint\RestEndpoint
{
    /**
     * Full REST path to the merchant-details endpoint, relative to the namespace.
     */
    protected const SELLER_ACCOUNT_PATH = 'common/seller-account';
    /**
     * The base path for this REST controller.
     *
     * @var string
     */
    protected $rest_base = 'common';
    /**
     * The settings instance.
     *
     * @var GeneralSettings
     */
    protected GeneralSettings $settings;
    /**
     * The Partners-Endpoint instance to request seller details from PayPal's API.
     *
     * @var PartnersEndpoint
     */
    protected PartnersEndpoint $partners_endpoint;
    /**
     * Field mapping for request to profile transformation.
     *
     * @var array
     */
    private array $field_map = array(
        'use_sandbox' => array('js_name' => 'useSandbox', 'sanitize' => 'to_boolean'),
        'use_manual_connection' => array('js_name' => 'useManualConnection', 'sanitize' => 'to_boolean'),
        // TODO: Is this really a "read-and-write" field? If no, it should not be listed in this map!
        'webhooks' => array('js_name' => 'webhooks'),
    );
    /**
     * Map merchant details to JS names.
     *
     * @var array
     */
    private array $merchant_info_map = array('merchant_connected' => array('js_name' => 'isConnected'), 'sandbox_merchant' => array('js_name' => 'isSandbox'), 'merchant_id' => array('js_name' => 'id'), 'merchant_email' => array('js_name' => 'email'), 'seller_type' => array('js_name' => 'sellerType'), 'client_id' => array('js_name' => 'clientId'), 'client_secret' => array('js_name' => 'clientSecret'), 'is_send_only_country' => array('js_name' => 'isSendOnlyCountry'));
    /**
     * Map woo-settings to JS names.
     *
     * @var array
     */
    private array $woo_settings_map = array('country' => array('js_name' => 'storeCountry'), 'currency' => array('js_name' => 'storeCurrency'), 'own_brand_only' => array('js_name' => 'ownBrandOnly'));
    /**
     * Constructor.
     *
     * @param GeneralSettings  $settings          The settings instance.
     * @param PartnersEndpoint $partners_endpoint Partners-API to get merchant details from PayPal.
     */
    public function __construct(GeneralSettings $settings, PartnersEndpoint $partners_endpoint)
    {
        $this->settings = $settings;
        $this->partners_endpoint = $partners_endpoint;
    }
    /**
     * Returns the path to the "Get Seller Account Details" REST route.
     * This is an internal route which is consumed by the plugin itself during onboarding.
     *
     * @param bool $full_route Whether to return the full endpoint path or just the route name.
     * @return string The full path to the REST endpoint.
     */
    public static function seller_account_route(bool $full_route = \false): string
    {
        if ($full_route) {
            return '/' . static::NAMESPACE . '/' . self::SELLER_ACCOUNT_PATH;
        }
        return self::SELLER_ACCOUNT_PATH;
    }
    /**
     * Configure REST API routes.
     */
    public function register_routes(): void
    {
        /**
         * GET /wp-json/wc/v3/wc_paypal/common
         */
        register_rest_route(static::NAMESPACE, '/' . $this->rest_base, array('methods' => WP_REST_Server::READABLE, 'callback' => array($this, 'get_details'), 'permission_callback' => array($this, 'check_permission')));
        /**
         * POST /wp-json/wc/v3/wc_paypal/common
         * {
         *     // Fields mentioned in $field_map[]['js_name']
         * }
         */
        register_rest_route(static::NAMESPACE, '/' . $this->rest_base, array('methods' => WP_REST_Server::EDITABLE, 'callback' => array($this, 'update_details'), 'permission_callback' => array($this, 'check_permission')));
        /**
         * GET /wp-json/wc/v3/wc_paypal/common/merchant
         */
        register_rest_route(static::NAMESPACE, "/{$this->rest_base}/merchant", array('methods' => WP_REST_Server::READABLE, 'callback' => array($this, 'get_merchant_details'), 'permission_callback' => array($this, 'check_permission')));
        /**
         * GET /wp-json/wc/v3/wc_paypal/common/seller-account
         */
        register_rest_route(static::NAMESPACE, self::seller_account_route(), array('methods' => WP_REST_Server::READABLE, 'callback' => array($this, 'get_seller_account_info'), 'permission_callback' => array($this, 'check_permission')));
    }
    /**
     * Returns all common details from the DB.
     *
     * @return WP_REST_Response The common settings.
     */
    public function get_details(): WP_REST_Response
    {
        $js_data = $this->sanitize_for_javascript($this->settings->to_array(), $this->field_map);
        $extra_data = $this->add_woo_settings(array());
        $extra_data = $this->add_merchant_info($extra_data);
        return $this->return_success($js_data, $extra_data);
    }
    /**
     * Updates common details based on the request.
     *
     * @param WP_REST_Request $request Full data about the request.
     *
     * @return WP_REST_Response The new common settings.
     */
    public function update_details(WP_REST_Request $request): WP_REST_Response
    {
        $wp_data = $this->sanitize_for_wordpress($request->get_params(), $this->field_map);
        $this->settings->from_array($wp_data);
        $this->settings->save();
        return $this->get_details();
    }
    /**
     * Returns only the (read-only) merchant details from the DB.
     *
     * @return WP_REST_Response Merchant details.
     */
    public function get_merchant_details(): WP_REST_Response
    {
        $js_data = array();
        // No persistent data.
        $extra_data = $this->add_merchant_info(array());
        return $this->return_success($js_data, $extra_data);
    }
    /**
     * Requests details from the PayPal API.
     *
     * Used during onboarding to enrich the merchant details in the DB.
     *
     * @return WP_REST_Response Seller details, provided by PayPal's API.
     */
    public function get_seller_account_info(): WP_REST_Response
    {
        try {
            $seller_status = $this->partners_endpoint->seller_status();
            $seller_data = array('country' => $seller_status->country());
            return $this->return_success($seller_data);
        } catch (Exception $ex) {
            return $this->return_error($ex->getMessage());
        }
    }
    /**
     * Appends the "merchant" attribute to the extra_data collection, which
     * contains details about the merchant's PayPal account, like the merchant ID.
     *
     * @param array $extra_data Initial extra_data collection.
     *
     * @return array Updated extra_data collection.
     */
    protected function add_merchant_info(array $extra_data): array
    {
        $extra_data['merchant'] = $this->sanitize_for_javascript($this->settings->to_array(), $this->merchant_info_map);
        if ($this->settings->is_merchant_connected()) {
            $extra_data['features'] = apply_filters('woocommerce_paypal_payments_rest_common_merchant_features', array());
        }
        return $extra_data;
    }
    /**
     * Appends the "wooSettings" attribute to the extra_data collection to
     * provide WooCommerce store details, like the store country and currency.
     *
     * @param array $extra_data Initial extra_data collection.
     *
     * @return array Updated extra_data collection.
     */
    protected function add_woo_settings(array $extra_data): array
    {
        $extra_data['wooSettings'] = $this->sanitize_for_javascript($this->settings->get_woo_settings(), $this->woo_settings_map);
        return $extra_data;
    }
}
