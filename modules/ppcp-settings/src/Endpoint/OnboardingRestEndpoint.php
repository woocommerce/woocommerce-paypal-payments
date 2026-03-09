<?php

/**
 * REST endpoint to manage the onboarding module.
 *
 * @package WooCommerce\PayPalCommerce\Settings\Endpoint
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Settings\Endpoint;

use WP_REST_Server;
use WP_REST_Response;
use WP_REST_Request;
use WooCommerce\PayPalCommerce\Settings\Data\OnboardingProfile;
/**
 * REST controller for the onboarding module.
 *
 * This API acts as the intermediary between the "external world" and our
 * internal data model.
 */
class OnboardingRestEndpoint extends \WooCommerce\PayPalCommerce\Settings\Endpoint\RestEndpoint
{
    /**
     * The base path for this REST controller.
     *
     * @var string
     */
    protected $rest_base = 'onboarding';
    /**
     * The settings instance.
     *
     * @var OnboardingProfile
     */
    protected OnboardingProfile $profile;
    /**
     * Field mapping for request to profile transformation.
     *
     * @var array
     */
    private array $field_map = array('completed' => array('js_name' => 'completed', 'sanitize' => 'to_boolean'), 'step' => array('js_name' => 'step', 'sanitize' => 'to_number'), 'is_casual_seller' => array('js_name' => 'isCasualSeller', 'sanitize' => 'to_boolean'), 'accept_card_payments' => array('js_name' => 'areOptionalPaymentMethodsEnabled', 'sanitize' => 'to_boolean'), 'products' => array('js_name' => 'products'), 'gateways_synced' => array('js_name' => 'gatewaysSynced', 'sanitize' => 'to_boolean'), 'gateways_refreshed' => array('js_name' => 'gatewaysRefreshed', 'sanitize' => 'to_boolean'));
    /**
     * Map the internal flags to JS names.
     *
     * @var array
     */
    private array $flag_map = array('can_use_casual_selling' => array('js_name' => 'canUseCasualSelling'), 'can_use_vaulting' => array('js_name' => 'canUseVaulting'), 'can_use_card_payments' => array('js_name' => 'canUseCardPayments'), 'can_use_subscriptions' => array('js_name' => 'canUseSubscriptions'), 'should_skip_payment_methods' => array('js_name' => 'shouldSkipPaymentMethods'), 'can_use_fastlane' => array('js_name' => 'canUseFastlane'), 'can_use_pay_later' => array('js_name' => 'canUsePayLater'));
    /**
     * Constructor.
     *
     * @param OnboardingProfile $profile The settings instance.
     */
    public function __construct(OnboardingProfile $profile)
    {
        $this->profile = $profile;
        $this->field_map['products']['sanitize'] = static fn($list) => array_map('sanitize_text_field', $list);
    }
    /**
     * Configure REST API routes.
     */
    public function register_routes(): void
    {
        /**
         * GET /wp-json/wc/v3/wc_paypal/onboarding
         */
        register_rest_route(static::NAMESPACE, '/' . $this->rest_base, array('methods' => WP_REST_Server::READABLE, 'callback' => array($this, 'get_details'), 'permission_callback' => array($this, 'check_permission')));
        /**
         * POST /wp-json/wc/v3/wc_paypal/onboarding
         * {
         *     // Fields mentioned in $field_map[]['js_name']
         * }
         */
        register_rest_route(static::NAMESPACE, '/' . $this->rest_base, array('methods' => WP_REST_Server::EDITABLE, 'callback' => array($this, 'update_details'), 'permission_callback' => array($this, 'check_permission')));
    }
    /**
     * Returns all details of the current onboarding wizard progress.
     *
     * @return WP_REST_Response The current state of the onboarding wizard.
     */
    public function get_details(): WP_REST_Response
    {
        $js_data = $this->sanitize_for_javascript($this->profile->to_array(), $this->field_map);
        $js_flags = $this->sanitize_for_javascript($this->profile->get_flags(), $this->flag_map);
        return $this->return_success($js_data, array('flags' => $js_flags));
    }
    /**
     * Updates onboarding details based on the request.
     *
     * @param WP_REST_Request $request Full data about the request.
     *
     * @return WP_REST_Response The updated state of the onboarding wizard.
     */
    public function update_details(WP_REST_Request $request): WP_REST_Response
    {
        $wp_data = $this->sanitize_for_wordpress($request->get_params(), $this->field_map);
        $this->profile->from_array($wp_data);
        $this->profile->save();
        return $this->get_details();
    }
}
