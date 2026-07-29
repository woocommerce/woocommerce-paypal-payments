<?php

/**
 * REST endpoint to manage PayPal Commerce Settings Tab.
 *
 * Provides endpoints for retrieving and updating PayPal settings (Settings Tab)
 * via WP REST API routes.
 *
 * @package WooCommerce\PayPalCommerce\Settings\Endpoint
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Settings\Endpoint;

use WooCommerce\PayPalCommerce\Settings\Data\Definition\FeaturesDefinition;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsModel;
/**
 * Class SettingsRestEndpoint
 *
 * Handles REST API endpoints for managing PayPal settings (Settings Tab).
 */
class SettingsRestEndpoint extends \WooCommerce\PayPalCommerce\Settings\Endpoint\RestEndpoint
{
    /**
     * The base path for this REST controller.
     *
     * @var string
     */
    protected $rest_base = 'settings';
    /**
     * The settings model instance.
     *
     * @var SettingsModel
     */
    private SettingsModel $settings;
    /**
     * Field mapping for request to profile transformation.
     *
     * @var array
     */
    private array $field_map = array('invoice_prefix' => array('js_name' => 'invoicePrefix'), 'brand_name' => array('js_name' => 'brandName'), 'soft_descriptor' => array('js_name' => 'softDescriptor'), 'subtotal_adjustment' => array('js_name' => 'subtotalAdjustment'), 'instant_payments_only' => array('js_name' => 'instantPaymentsOnly', 'sanitize' => 'to_boolean'), 'landing_page' => array('js_name' => 'landingPage'), 'button_language' => array('js_name' => 'buttonLanguage'), 'authorize_only' => array('js_name' => 'authorizeOnly', 'sanitize' => 'to_boolean'), 'capture_virtual_orders' => array('js_name' => 'captureVirtualOrders', 'sanitize' => 'to_boolean'), FeaturesDefinition::FEATURE_SAVE_PAYPAL_AND_VENMO => array('js_name' => 'savePaypalAndVenmo', 'sanitize' => 'to_boolean'), 'enable_contact_module' => array('js_name' => 'enableContactModule', 'sanitize' => 'to_boolean'), 'save_card_details' => array('js_name' => 'saveCardDetails', 'sanitize' => 'to_boolean'), 'enable_pay_now' => array('js_name' => 'enablePayNow', 'sanitize' => 'to_boolean'), 'enable_logging' => array('js_name' => 'enableLogging', 'sanitize' => 'to_boolean'), 'stay_updated' => array('js_name' => 'stayUpdated', 'sanitize' => 'to_boolean'), 'disabled_cards' => array('js_name' => 'disabledCards'), 'three_d_secure' => array('js_name' => 'threeDSecure', 'sanitize' => 'sanitize_text_field'), 'payment_level_processing' => array('js_name' => 'paymentLevelProcessing', 'sanitize' => 'to_boolean'), 'ships_from_postal_code' => array('js_name' => 'shipsFromPostalCode', 'sanitize' => 'sanitize_text_field'));
    /**
     * SettingsRestEndpoint constructor.
     *
     * @param SettingsModel $settings The settings model instance.
     */
    public function __construct(SettingsModel $settings)
    {
        $this->settings = $settings;
    }
    /**
     * Registers the REST API routes for settings management.
     */
    public function register_routes(): void
    {
        /**
         * GET wc/v3/wc_paypal/settings
         * POST wc/v3/wc_paypal/settings
         */
        register_rest_route(static::NAMESPACE, '/' . $this->rest_base, array(array('methods' => WP_REST_Server::READABLE, 'callback' => array($this, 'get_details'), 'permission_callback' => array($this, 'check_permission')), array('methods' => WP_REST_Server::CREATABLE, 'callback' => array($this, 'update_details'), 'permission_callback' => array($this, 'check_permission'))));
    }
    /**
     * Retrieves the current settings.
     *
     * @return WP_REST_Response The response containing settings data or error details.
     */
    public function get_details(): WP_REST_Response
    {
        $js_data = $this->sanitize_for_javascript($this->settings->to_array(), $this->field_map);
        return $this->return_success($js_data);
    }
    /**
     * Updates the settings with provided data.
     *
     * @param WP_REST_Request $request The request instance containing new settings.
     * @return WP_REST_Response The response containing updated settings or error details.
     */
    public function update_details(WP_REST_Request $request): WP_REST_Response
    {
        $wp_data = $this->sanitize_for_wordpress($request->get_params(), $this->field_map);
        $this->settings->from_array($wp_data);
        $this->settings->save();
        return $this->get_details();
    }
}
